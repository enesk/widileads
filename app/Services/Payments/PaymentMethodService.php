<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Constants\PaymentMethodStatus;
use App\Constants\PaymentMethodType;
use App\Constants\WalletOwnerType;
use App\Events\Postpaid\PostpaidDowngradeRequested;
use App\Exceptions\PaymentMethodNotAllowedException;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserStripeData;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Mandate;
use Stripe\SetupIntent;
use Stripe\StripeClient;

/**
 * Hinterlegt, bestaetigt und widerruft die Zahlungsmittel eines
 * Postpaid-Kaeufers (LP-POSTPAID-005).
 *
 * Diese Klasse ist die einzige Stelle, die `payment_methods` schreibt. Sie
 * bewegt kein Geld: Sie richtet den Weg ein, auf dem spaeter eingezogen wird
 * (LP-POSTPAID-008).
 *
 * Weder IBAN noch Kartennummer beruehren diese Anwendung. Der Kaeufer gibt sie
 * in Stripe Elements ein, also im Browser direkt an Stripe; hier kommen nur
 * Kennungen sowie die vier letzten Stellen zur Wiedererkennung an. Deshalb der
 * Umweg ueber den SetupIntent statt eines eigenen Formulars.
 *
 * Der Stripe-Kunde ist derselbe wie im vorhandenen Checkout von SaaSykit
 * (`user_stripe_data`, verknuepft mit dem Mandanten). Ein zweiter Kunde je
 * Mandant waere die teuerste Art, sich das Leben schwer zu machen: Karten,
 * Mandate und Rechnungen laegen dann an zwei Stellen.
 *
 * SEPA und Karte sind bewusst gleichwertig angeboten und die Karte ist das
 * sicherere Mittel: Eine Lastschrift kann acht Wochen nach der Gutschrift
 * zurueckgegeben werden, eine Kartenzahlung scheitert sofort oder gar nicht.
 * Vorausgewaehlt wird trotzdem keines von beiden -- die Wahl gehoert dem
 * Kaeufer.
 */
class PaymentMethodService
{
    /**
     * Startet das Hinterlegen eines Zahlungsmittels und gibt zurueck, was
     * Stripe Elements im Browser braucht.
     *
     * `usage => off_session` ist der Kern des Ganzen: Eingezogen wird spaeter
     * durch den Scheduler, wenn niemand am Bildschirm sitzt. Ohne diese Angabe
     * verlangt Stripe beim Einzug eine erneute Bestaetigung des Kaeufers -- bei
     * Karten eine 3DS-Abfrage, die off-session zwangslaeufig scheitert.
     *
     * @return array{setup_intent_id: string, client_secret: string, publishable_key: string, type: string, mandate: array{creditor_name: string, creditor_id: string, text: string}|null}
     */
    public function createSetupIntent(Wallet $wallet, PaymentMethodType $type, ?User $user = null): array
    {
        $this->guardBuyerWallet($wallet);

        $customerId = $this->findOrCreateStripeCustomer($wallet, $user);

        $intent = $this->client()->setupIntents->create([
            'customer' => $customerId,
            // Genau ein Typ je SetupIntent: Der Kaeufer hat sich vorher
            // entschieden, und das Mandat unterscheidet sich. Eine Liste mit
            // beiden wuerde die Wahl in Stripe Elements wiederholen.
            'payment_method_types' => [$type->value],
            'usage' => 'off_session',
            // Die Kennung des Wallets wandert mit, damit die Bestaetigung
            // (confirm) und jeder spaetere Webhook den SetupIntent ohne
            // Ratespiel dem richtigen Geldtopf zuordnen koennen.
            'metadata' => [
                'wallet_id' => (string) $wallet->getKey(),
                'purpose' => 'postpaid_payment_method',
            ],
        ]);

        return [
            'setup_intent_id' => (string) $intent->id,
            'client_secret' => (string) $intent->client_secret,
            'publishable_key' => (string) config('services.stripe.publishable_key'),
            'type' => $type->value,
            'mandate' => $type->requiresMandate() ? $this->mandate() : null,
        ];
    }

    /**
     * Uebernimmt das bei Stripe entstandene Zahlungsmittel in die eigene
     * Tabelle und macht es zum Standardmittel.
     *
     * Gelesen wird ausschliesslich bei Stripe: Was der Browser meldet, kommt
     * vom Kaeufer und ist damit kein Beleg. Insbesondere entscheidet der Stand
     * des SetupIntent bei Stripe, ob gespeichert wird -- nicht ein Ergebnis,
     * das der Browser mitschickt.
     *
     * Bei SEPA muss Stripe ein Mandat geliefert haben. Das Mandat entsteht dort
     * nur, wenn der Kaeufer den Mandatstext in Stripe Elements bestaetigt hat;
     * seine Kennung ist damit der Nachweis der Einwilligung. Fehlt es, wird
     * nichts gespeichert -- eine Lastschrift ohne belegtes Mandat waere
     * angreifbar.
     */
    public function confirm(Wallet $wallet, string $setupIntentId, ?string $mandateIp = null): PaymentMethod
    {
        $this->guardBuyerWallet($wallet);

        $intent = $this->client()->setupIntents->retrieve($setupIntentId, [
            'expand' => ['payment_method', 'mandate'],
        ]);

        $this->guardIntentBelongsToWallet($intent, $wallet);

        if ($intent->status !== SetupIntent::STATUS_SUCCEEDED) {
            throw PaymentMethodNotAllowedException::setupNotCompleted((string) $intent->status);
        }

        $stripeMethod = $intent->payment_method;

        if (! $stripeMethod instanceof \Stripe\PaymentMethod) {
            throw PaymentMethodNotAllowedException::setupNotCompleted((string) $intent->status);
        }

        $type = PaymentMethodType::tryFrom((string) $stripeMethod->type);

        if (! $type instanceof PaymentMethodType) {
            throw PaymentMethodNotAllowedException::setupNotCompleted((string) $stripeMethod->type);
        }

        $mandateId = $this->mandateIdOf($intent);

        if ($type->requiresMandate() && $mandateId === null) {
            throw PaymentMethodNotAllowedException::mandateMissing();
        }

        $attributes = [
            'wallet_id' => $wallet->getKey(),
            'type' => $type,
            'provider' => 'stripe',
            'provider_customer_id' => (string) $intent->customer,
            'provider_payment_method_id' => (string) $stripeMethod->id,
            'provider_mandate_id' => $mandateId,
            'last4' => $this->last4Of($stripeMethod, $type),
            'brand' => $type === PaymentMethodType::CARD ? ($stripeMethod->card->brand ?? null) : null,
            'status' => PaymentMethodStatus::ACTIVE,
        ];

        // Der Mandatsnachweis wird nur bei SEPA gefuehrt und nur beim ersten
        // Mal geschrieben: Er belegt einen Zeitpunkt, und ein Beleg, der sich
        // bei jedem Aufruf erneuert, belegt nichts.
        if ($type->requiresMandate()) {
            $attributes['mandate_accepted_at'] = now();
            $attributes['mandate_ip'] = $mandateIp ?? request()->ip();
        }

        return DB::transaction(function () use ($wallet, $attributes): PaymentMethod {
            /** @var PaymentMethod $method */
            $method = PaymentMethod::query()->updateOrCreate(
                ['provider_payment_method_id' => $attributes['provider_payment_method_id']],
                $attributes,
            );

            $this->makeDefault($wallet, $method);

            return $method->refresh();
        });
    }

    /**
     * Haengt das Zahlungsmittel bei Stripe ab und legt die Zeile still.
     *
     * Das letzte einsatzbereite Mittel eines Postpaid-Wallets bleibt stehen,
     * solange das Verfahren laeuft oder ein Betrag offen ist: Sonst entsteht
     * eine Forderung ohne Weg zum Geld, und der Kaeufer koennte durch einen
     * Klick im Portal seine Zahlungspflicht abbestellen.
     *
     * Der Abhaengevorgang bei Stripe darf scheitern, ohne dass die eigene Zeile
     * aktiv bleibt: Wenn das Mittel dort schon weg ist, ist das Ergebnis
     * dasselbe. Andernfalls stuende im Portal ein Mittel, das der Kaeufer nicht
     * mehr loswird.
     */
    public function revoke(PaymentMethod $method): PaymentMethod
    {
        $wallet = $method->wallet;

        if (! $wallet instanceof Wallet) {
            throw PaymentMethodNotAllowedException::notABuyerWallet();
        }

        $this->guardRemovable($wallet, $method);

        try {
            $this->client()->paymentMethods->detach($method->provider_payment_method_id);
        } catch (ApiErrorException $exception) {
            Log::warning('Zahlungsmittel: Abhaengen bei Stripe fehlgeschlagen, Zeile wird trotzdem stillgelegt.', [
                'payment_method_id' => $method->getKey(),
                'provider_payment_method_id' => $method->provider_payment_method_id,
                'reason' => $exception->getMessage(),
            ]);
        }

        return $this->markInactive($method, PaymentMethodStatus::REVOKED, requestDowngrade: false);
    }

    /**
     * Legt ein Zahlungsmittel still, weil Stripe es gemeldet hat -- abgehaengt
     * im Dashboard, Mandat vom Kaeufer oder von der Bank widerrufen
     * (StripePostpaidWebhookController).
     *
     * Anders als beim Widerruf im Portal gibt es hier nichts abzulehnen: Der
     * Weg zum Geld ist bereits weg, ob es der Plattform passt oder nicht. War
     * es das Standardmittel eines Postpaid-Wallets, ist damit die Grundlage des
     * Verfahrens entfallen und die Rueckstufung wird angestossen
     * (LP-POSTPAID-009).
     */
    public function markRevokedByProvider(PaymentMethod $method, PaymentMethodStatus $status = PaymentMethodStatus::REVOKED): PaymentMethod
    {
        return $this->markInactive($method, $status, requestDowngrade: true);
    }

    /**
     * Das Zahlungsmittel zu einer Stripe-Kennung, oder null, wenn es hier nie
     * hinterlegt war. Ein unbekanntes Mittel ist im Webhook kein Fehler: Das
     * Konto wird auch fuer Abonnements und Einmalkaeufe genutzt, deren
     * Zahlungsmittel diese Tabelle nicht kennt.
     */
    public function findByProviderId(string $providerPaymentMethodId): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('provider_payment_method_id', $providerPaymentMethodId)
            ->first();
    }

    /**
     * Das Zahlungsmittel zu einer Mandatskennung.
     */
    public function findByMandateId(string $mandateId): ?PaymentMethod
    {
        return PaymentMethod::query()
            ->where('provider_mandate_id', $mandateId)
            ->first();
    }

    /**
     * Glaeubiger, Glaeubiger-ID und Mandatstext fuer die Anzeige vor der
     * Bestaetigung (LP-POSTPAID-005).
     *
     * Der Text ist Inhalt des Mandats und keine Beigabe: Ohne ihn weiss der
     * Kaeufer nicht, wem er eine Abbuchungserlaubnis erteilt. Deshalb steht er
     * im Portal ueber der Bestaetigung und nicht in einer Fussnote.
     *
     * Die Glaeubiger-ID kommt aus der Konfiguration. Ist keine eigene
     * hinterlegt, wird die von Stripe genutzte genannt: Stripe tritt als
     * Zahlungsdienstleister mit eigener Glaeubiger-Identifikationsnummer auf,
     * eine eigene Nummer von der Bundesbank ist fuer den Einzug nicht
     * erforderlich.
     *
     * @return array{creditor_name: string, creditor_id: string, text: string}
     */
    public function mandate(): array
    {
        $creditorName = (string) config('wallet.postpaid.creditor_name');
        $creditorId = $this->creditorId();

        return [
            'creditor_name' => $creditorName,
            'creditor_id' => $creditorId,
            'text' => __('marketplace.wallet.payment_methods.mandate.text', [
                'creditor' => $creditorName,
                'creditor_id' => $creditorId,
            ]),
        ];
    }

    /**
     * Die wirksame Glaeubiger-Identifikationsnummer.
     */
    public function creditorId(): string
    {
        $configured = (string) config('wallet.postpaid.creditor_id');

        return $configured !== ''
            ? $configured
            : (string) config('wallet.postpaid.creditor_id_fallback');
    }

    /**
     * Macht ein Mittel zum Standardmittel und nimmt allen anderen dieses
     * Kennzeichen.
     *
     * Die Eindeutigkeit sichert dieser Weg und nicht die Datenbank: MariaDB
     * kennt keinen partiellen Unique-Index, und ein voller ueber
     * (wallet_id, is_default) wuerde auch mehrere `false`-Zeilen verbieten.
     * Deshalb unter Transaktion und immer zuerst abraeumen, dann setzen.
     */
    public function makeDefault(Wallet $wallet, PaymentMethod $method): PaymentMethod
    {
        if ((int) $method->wallet_id !== (int) $wallet->getKey()) {
            throw PaymentMethodNotAllowedException::foreignMethod($method);
        }

        DB::transaction(function () use ($wallet, $method): void {
            PaymentMethod::query()
                ->where('wallet_id', $wallet->getKey())
                ->whereKeyNot($method->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $method->forceFill(['is_default' => true])->save();
        });

        return $method;
    }

    /**
     * Legt die Zeile still und stoesst bei Bedarf die Rueckstufung an.
     */
    private function markInactive(PaymentMethod $method, PaymentMethodStatus $status, bool $requestDowngrade): PaymentMethod
    {
        $wasDefault = (bool) $method->is_default;
        $wallet = $method->wallet;

        DB::transaction(function () use ($method, $status): void {
            $method->forceFill([
                'status' => $status,
                // Ein stillgelegtes Mittel bleibt nicht Standardmittel: Der
                // Einzug sucht sein Mittel ueber dieses Kennzeichen und wuerde
                // sonst auf ein abgehaengtes treffen.
                'is_default' => false,
            ])->save();
        });

        if ($requestDowngrade && $wasDefault && $wallet instanceof Wallet && $wallet->isPostpaid()) {
            PostpaidDowngradeRequested::dispatch(
                (int) $wallet->getKey(),
                PostpaidDowngradeRequested::REASON_PAYMENT_METHOD_REVOKED,
                (int) $method->getKey(),
            );
        }

        return $method;
    }

    /**
     * Darf dieses Mittel weg?
     */
    private function guardRemovable(Wallet $wallet, PaymentMethod $method): void
    {
        if (! $method->status->isUsable()) {
            // Ein bereits stillgelegtes Mittel noch einmal zu widerrufen ist
            // harmlos und soll nicht an der Sperre unten haengen bleiben.
            return;
        }

        $remaining = PaymentMethod::query()
            ->where('wallet_id', $wallet->getKey())
            ->whereKeyNot($method->getKey())
            ->active()
            ->exists();

        if ($remaining) {
            return;
        }

        $openAmount = max(0, -(int) $wallet->balance_cents);

        if ($wallet->isPostpaid() || $openAmount > 0) {
            throw PaymentMethodNotAllowedException::lastMethodOfPostpaidWallet($openAmount);
        }
    }

    /**
     * Zahlungsmittel gehoeren an das Kauf-Wallet: Nur dort wird eingezogen.
     */
    private function guardBuyerWallet(Wallet $wallet): void
    {
        if ($wallet->owner_type !== WalletOwnerType::BUYER) {
            throw PaymentMethodNotAllowedException::notABuyerWallet();
        }
    }

    /**
     * Gehoert dieser SetupIntent zu diesem Wallet? Ohne diese Pruefung liesse
     * sich mit einer fremden Kennung ein fremdes Zahlungsmittel an das eigene
     * Wallet haengen -- und damit auf fremde Rechnung kaufen.
     */
    private function guardIntentBelongsToWallet(SetupIntent $intent, Wallet $wallet): void
    {
        $walletId = $intent->metadata->wallet_id ?? null;

        if ($walletId === null || (int) $walletId !== (int) $wallet->getKey()) {
            throw PaymentMethodNotAllowedException::setupIntentMismatch();
        }
    }

    /**
     * Die Mandatskennung eines SetupIntent. Sie steht je nach Abruf als
     * Kennung oder als aufgeloestes Objekt da.
     */
    private function mandateIdOf(SetupIntent $intent): ?string
    {
        $mandate = $intent->mandate;

        if (is_string($mandate) && $mandate !== '') {
            return $mandate;
        }

        if ($mandate instanceof Mandate) {
            return (string) $mandate->id;
        }

        return null;
    }

    /**
     * Die vier letzten Stellen zur Wiedererkennung: bei SEPA die der IBAN, bei
     * der Karte die der Kartennummer. Mehr braucht das Portal nicht, und mehr
     * soll hier auch nicht liegen.
     */
    private function last4Of(\Stripe\PaymentMethod $method, PaymentMethodType $type): string
    {
        $last4 = $type === PaymentMethodType::SEPA_DEBIT
            ? ($method->sepa_debit->last4 ?? null)
            : ($method->card->last4 ?? null);

        // Leer statt Ausnahme: Ein hinterlegtes Zahlungsmittel ohne Endziffern
        // ist im Portal schlecht wiederzuerkennen, aber voll einziehbar. Daran
        // soll das Hinterlegen nicht scheitern.
        return (string) ($last4 ?? '');
    }

    /**
     * Der Stripe-Kunde des Mandanten -- derselbe wie im vorhandenen Checkout.
     *
     * Die Zuordnung liegt in `user_stripe_data` am Mandanten (so legt sie
     * App\Services\PaymentProviders\Stripe\StripeProvider an). Fehlt sie, weil
     * der Kaeufer noch nie gezahlt hat, entsteht der Kunde hier -- mit
     * derselben Zuordnung, damit ein spaeterer Checkout ihn wiederfindet.
     */
    private function findOrCreateStripeCustomer(Wallet $wallet, ?User $user): string
    {
        $tenant = $wallet->owner;

        if (! $tenant instanceof Tenant) {
            throw PaymentMethodNotAllowedException::notABuyerWallet();
        }

        // Gelesen wird auf der Tabelle und nicht ueber Tenant::stripeData():
        // Die Beziehung dort ist ohne Typangabe deklariert, ein Zugriff auf
        // stripe_customer_id waere damit ein Griff ins Ungetypte.
        $stripeData = UserStripeData::query()
            ->where('tenant_id', $tenant->getKey())
            ->first();

        if ($stripeData instanceof UserStripeData && filled($stripeData->stripe_customer_id)) {
            return (string) $stripeData->stripe_customer_id;
        }

        $owner = $user instanceof User ? $user : $tenant->users()->first();

        $customer = $this->client()->customers->create([
            'email' => $owner instanceof User ? $owner->email : null,
            'name' => $owner instanceof User ? $owner->name : $tenant->name,
            'metadata' => [
                'tenant_uuid' => (string) $tenant->uuid,
            ],
        ]);

        if ($stripeData instanceof UserStripeData) {
            $stripeData->stripe_customer_id = $customer->id;
            $stripeData->save();
        } else {
            UserStripeData::query()->create([
                'stripe_customer_id' => $customer->id,
                'tenant_id' => $tenant->getKey(),
                'user_id' => $owner instanceof User ? $owner->getKey() : null,
            ]);
        }

        return (string) $customer->id;
    }

    /**
     * Der Stripe-Mandant. Wie im StripeProvider je Aufruf gebaut -- der Client
     * haelt keinen Zustand, und der geheime Schluessel kann sich im
     * Admin-Panel geaendert haben.
     */
    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret_key'));
    }
}

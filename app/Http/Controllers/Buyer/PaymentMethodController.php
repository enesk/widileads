<?php

declare(strict_types=1);

namespace App\Http\Controllers\Buyer;

use App\Constants\PaymentMethodType;
use App\Exceptions\PaymentMethodNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hinterlegt und entfernt die Zahlungsmittel eines Kaeufers
 * (LP-POSTPAID-005).
 *
 * Drei Schritte, weil Stripe Elements dazwischen im Browser laeuft: Der
 * Kaeufer holt sich einen SetupIntent (`setupIntent`), gibt IBAN oder
 * Kartennummer direkt bei Stripe ein, und meldet sich mit der Kennung des
 * SetupIntent zurueck (`confirm`). Gespeichert wird erst im dritten Schritt,
 * und zwar nach dem Stand bei Stripe -- nicht nach dem, was der Browser
 * behauptet.
 *
 * Die Antworten sind JSON: Die Seite dahinter (LP-POSTPAID-010) arbeitet mit
 * Stripe Elements und damit ohnehin im Browser. Nur das Entfernen kommt auch
 * als gewoehnliches Formular vor und antwortet dann mit einer Weiterleitung.
 *
 * Der Workspace steht wie bei der Aufladung im Rumpf und wird gegen die
 * Mitgliedschaft geprueft: Eine fremde Kennung ist hier kein Sonderfall,
 * sondern der Versuch, ein Zahlungsmittel an fremde Rechnung zu haengen.
 */
class PaymentMethodController extends Controller
{
    public function __construct(private PaymentMethodService $paymentMethods) {}

    /**
     * Schritt 1: SetupIntent anlegen und zurueckgeben, was Stripe Elements
     * braucht -- samt Mandatstext, wenn es eine Lastschrift werden soll.
     */
    public function setupIntent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PaymentMethodType::values())],
            'tenant' => ['required', 'string', Rule::exists('tenants', 'uuid')],
        ]);

        $wallet = Wallet::forBuyer($this->tenantOfUser($request, (string) $data['tenant']));
        $type = PaymentMethodType::from((string) $data['type']);

        try {
            $setup = $this->paymentMethods->createSetupIntent($wallet, $type, $this->user($request));
        } catch (ApiErrorException $exception) {
            return $this->providerUnavailable($exception);
        } catch (PaymentMethodNotAllowedException $exception) {
            return $this->refused($exception);
        }

        return response()->json($setup);
    }

    /**
     * Schritt 2: Das bei Stripe entstandene Zahlungsmittel uebernehmen.
     *
     * Die Mandatseinwilligung ist Pflicht, sobald der Kaeufer eine Lastschrift
     * hinterlegt. Sie wird hier geprueft und nicht nur im Browser: Ein
     * Haekchen im Formular ist keine Zusicherung. Der zweite, unabhaengige
     * Nachweis ist die Mandatskennung von Stripe -- die entsteht dort nur,
     * wenn der Kaeufer den Mandatstext in Stripe Elements bestaetigt hat, und
     * ohne sie speichert der Dienst nichts.
     */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'setup_intent_id' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(PaymentMethodType::values())],
            'tenant' => ['required', 'string', Rule::exists('tenants', 'uuid')],
            'mandate_accepted' => ['nullable', 'boolean'],
        ]);

        $wallet = Wallet::forBuyer($this->tenantOfUser($request, (string) $data['tenant']));
        $type = PaymentMethodType::from((string) $data['type']);

        if ($type->requiresMandate() && ! $request->boolean('mandate_accepted')) {
            throw ValidationException::withMessages([
                'mandate_accepted' => __('marketplace.wallet.payment_methods.errors.mandate_required'),
            ]);
        }

        try {
            $method = $this->paymentMethods->confirm(
                $wallet,
                (string) $data['setup_intent_id'],
                $request->ip(),
            );
        } catch (ApiErrorException $exception) {
            return $this->providerUnavailable($exception);
        } catch (PaymentMethodNotAllowedException $exception) {
            return $this->refused($exception);
        }

        return response()->json([
            'payment_method' => $this->present($method),
            'message' => __('marketplace.wallet.payment_methods.added'),
        ]);
    }

    /**
     * Schritt 3 (spaeter): Zahlungsmittel entfernen.
     *
     * Das letzte einsatzbereite Mittel eines Postpaid-Kaeufers bleibt stehen,
     * solange das Verfahren laeuft oder ein Betrag offen ist -- der Dienst
     * lehnt das mit 422 und Hinweis ab.
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'tenant' => ['required', 'string', Rule::exists('tenants', 'uuid')],
        ]);

        $wallet = Wallet::forBuyer($this->tenantOfUser($request, (string) $data['tenant']));

        // Ein fremdes Zahlungsmittel ist hier nicht "nicht erlaubt", sondern
        // unbekannt: Der Kaeufer soll nicht erfahren, dass es existiert.
        if ((int) $paymentMethod->wallet_id !== (int) $wallet->getKey()) {
            abort(404);
        }

        try {
            $this->paymentMethods->revoke($paymentMethod);
        } catch (PaymentMethodNotAllowedException $exception) {
            if ($request->expectsJson()) {
                return $this->refused($exception);
            }

            return back()->withErrors(['payment_method' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('marketplace.wallet.payment_methods.removed'),
            ]);
        }

        return back()->with('status', __('marketplace.wallet.payment_methods.removed'));
    }

    /**
     * Was das Portal von einem Zahlungsmittel anzeigen darf. Mehr gibt es hier
     * auch nicht -- die vollstaendigen Daten liegen bei Stripe.
     *
     * @return array<string, mixed>
     */
    private function present(PaymentMethod $method): array
    {
        return [
            'id' => $method->getKey(),
            'type' => $method->type->value,
            'label' => $method->label(),
            'last4' => $method->last4,
            'brand' => $method->brand,
            'is_default' => $method->is_default,
            'status' => $method->status->value,
            'mandate_accepted_at' => $method->mandate_accepted_at?->toIso8601String(),
        ];
    }

    /**
     * Der Anbieter ist nicht erreichbar oder lehnt ab. Die Ursache gehoert ins
     * Log, der Kaeufer bekommt einen Satz, mit dem er etwas anfangen kann.
     */
    private function providerUnavailable(ApiErrorException $exception): JsonResponse
    {
        logger()->error('Zahlungsmittel: Stripe hat den Vorgang abgelehnt.', [
            'reason' => $exception->getMessage(),
        ]);

        return response()->json([
            'message' => __('marketplace.wallet.payment_methods.errors.provider_unavailable'),
        ], Response::HTTP_BAD_GATEWAY);
    }

    /**
     * Eine abgelehnte Aktion mit dem Statuscode, den die Ausnahme selbst
     * nennt.
     */
    private function refused(PaymentMethodNotAllowedException $exception): JsonResponse
    {
        $status = $exception->getCode();

        return response()->json([
            'message' => $exception->getMessage(),
        ], is_int($status) && $status >= 400 ? $status : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    /**
     * Der Workspace, fuer den ein Zahlungsmittel hinterlegt wird -- und zwar
     * nur einer, dem der angemeldete Benutzer angehoert.
     */
    private function tenantOfUser(Request $request, string $uuid): Tenant
    {
        $tenant = $this->user($request)->tenants()->where('tenants.uuid', $uuid)->first();

        if (! $tenant instanceof Tenant) {
            abort(403);
        }

        return $tenant;
    }
}

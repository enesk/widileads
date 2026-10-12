<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\OnboardingStep;
use App\Constants\TenantType;
use App\Constants\WalletTransactionType;
use App\Models\CallAttempt;
use App\Models\LeadPurchase;
use App\Models\Tenant;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * LP-ONBOARD-002: Hakt das Onboarding fuer Bestandskaeufer nachtraeglich ab.
 *
 * Jeder Kaeufer-Mandant mit mindestens einem gekauften Lead bekommt die drei
 * Pflichtschritte des Wizards und `first_purchase` als erledigt eingetragen.
 * Begruendung: Wer schon gekauft hat, hat Branchen, Region und Zahlungsweise
 * laengst hinterlegt -- ihn in einen Einrichtungs-Wizard zu schicken, waere
 * eine Sperre ohne Anlass.
 *
 * Dazu kommen zwei Haken, die nicht pauschal gelten, sondern je Workspace
 * einen Nachweis in den Daten brauchen (LP-ONBOARD-048):
 * - `first_topup`, wenn im Kaeufer-Wallet mindestens eine Buchung vom Typ
 *   `topup` steht. Das Wallet haengt an `wallets.owner_type`/`owner_id` und
 *   nicht an `tenant_id`, der Weg dorthin ist Tenant::buyerWallet(). Eine
 *   Eroeffnungsbuchung ist ein eigener Buchungstyp (`opening_balance`) und
 *   faellt damit sauber heraus.
 * - `first_call`, wenn es zum Workspace einen `call_attempts`-Eintrag mit
 *   `duration_seconds > 0` gibt -- genau die Schwelle, die MarkFirstCall
 *   auswertet. `outcome` wird bewusst nicht zusaetzlich geprueft: zwei
 *   verschiedene Nachweise fuer denselben Haken laufen auseinander.
 *
 * Offen bleiben die beiden Touren (`tour_marketplace_seen`,
 * `tour_lead_detail_seen`): Ein Haken bei einer Tour, die niemand gesehen hat,
 * nimmt dem Bestandskunden die Erklaerung weg -- und anders als bei Aufladung
 * und Anruf gibt es dafuer auch keinen Nachweis in den Daten.
 *
 * Mit `first_call` gilt der Workspace als aktiviert, und die
 * Aktivierungskarte im Dashboard wuerde dann einmalig ihren Abschlusszustand
 * zeigen ("Du bist startklar."). Der Bestandskunde saehe also weiterhin eine
 * Karte, nur mit anderem Text. Deshalb verbraucht der Lauf diesen Zustand
 * gleich mit, ueber OnboardingService::markActivationCardSeen() --
 * ereignislos und idempotent wie alles hier.
 *
 * Der Lauf ist beliebig oft wiederholbar: Er geht ueber
 * OnboardingService::backfill(), und das ist idempotent. Dieser Weg loest
 * bewusst keine Onboarding-Ereignisse aus -- sonst bekaeme jeder
 * Bestandskunde die Abschlussmails der Mailstrecke (LP-ONBOARD-014)
 * nachgeschickt, Jahre nach dem ersten Kauf.
 *
 * Laeuft nicht automatisch. Kein Eintrag im Scheduler, keine Migration ruft
 * ihn auf -- er wird nach dem Deploy einmal von Hand gestartet, zusammen mit
 * `onboarding:suppress-mails` (LP-ONBOARD-039). Das Nachtragen hier reicht
 * naemlich nicht: Wer den Wizard spaeter trotzdem durchlaeuft, loest die
 * Strecke aus, und die Begruessung hat keine Abbruchregel.
 */
class OnboardingBackfill extends Command
{
    protected $signature = 'onboarding:backfill
        {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Hakt Wizard-Schritte, ersten Kauf und -- wo nachweisbar -- erste Aufladung und ersten Anruf fuer Bestandskaeufer ab.';

    public function __construct(private readonly OnboardingService $onboarding)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tenantIds = LeadPurchase::query()
            ->distinct()
            ->pluck('buyer_tenant_id')
            ->all();

        if ($tenantIds === []) {
            $this->info('Kein Kaeufer mit gekauften Leads gefunden. Es gibt nichts nachzutragen.');

            return self::SUCCESS;
        }

        $touched = 0;
        $written = 0;

        Tenant::query()
            ->whereIn('id', $tenantIds)
            ->where('type', TenantType::BUYER)
            ->orderBy('id')
            ->each(function (Tenant $workspace) use ($dryRun, &$touched, &$written): void {
                $steps = $this->stepsFor($workspace);

                $willActivate = in_array(OnboardingStep::FIRST_CALL, $steps, strict: true)
                    || $this->onboarding->isActivated($workspace);

                $silencesCard = $willActivate && $workspace->onboarding_card_done_seen_at === null;

                if ($dryRun) {
                    $completed = $this->onboarding->completedSteps($workspace);
                    $new = count(array_filter(
                        $steps,
                        static fn (OnboardingStep $step): bool => ! in_array($step, $completed, strict: true),
                    ));
                } else {
                    $new = $this->onboarding->backfill($workspace, $steps);

                    if ($willActivate) {
                        $this->onboarding->markActivationCardSeen($workspace);
                    }

                    // backfill() wirft den gemerkten Fortschritt vor dem
                    // Stilllegen weg, markActivationCardSeen() tut es nicht.
                    $this->onboarding->forgetProgress($workspace);
                }

                $touched++;
                $written += $new;

                $this->line(sprintf(
                    '%s (#%d): %d %s%s',
                    $workspace->name,
                    $workspace->getKey(),
                    $new,
                    $dryRun ? 'Schritte waeren nachzutragen' : 'Schritte nachgetragen',
                    $silencesCard ? ($dryRun ? ' -- Karte wird stillgelegt' : ' -- Karte stillgelegt') : '',
                ));
            });

        $this->info(sprintf(
            '%d Kaeufer geprueft, %d Schritte %s.',
            $touched,
            $written,
            $dryRun ? 'offen' : 'nachgetragen',
        ));

        return self::SUCCESS;
    }

    /**
     * Die Schritte, die fuer genau diesen Workspace nachzutragen sind.
     *
     * Bewusst eine Methode fuer beide Pfade: Liefe die Vorschau auf einer
     * anderen Liste als der Schreiblauf, zeigte `--dry-run` eine Zahl, die
     * nachher nicht stimmt.
     *
     * @return list<OnboardingStep>
     */
    private function stepsFor(Tenant $workspace): array
    {
        $steps = [...OnboardingStep::wizardSteps(), OnboardingStep::FIRST_PURCHASE];

        if ($this->hasTopup($workspace)) {
            $steps[] = OnboardingStep::FIRST_TOPUP;
        }

        if ($this->hasCompletedCall($workspace)) {
            $steps[] = OnboardingStep::FIRST_CALL;
        }

        return $steps;
    }

    /**
     * Gibt es im Kaeufer-Wallet des Workspaces eine echte Aufladung?
     */
    private function hasTopup(Tenant $workspace): bool
    {
        return $workspace->buyerWallet()
            ->whereHas(
                'transactions',
                static fn (Builder $query): Builder => $query->where('type', WalletTransactionType::TOPUP),
            )
            ->exists();
    }

    /**
     * Hat der Workspace mindestens einmal wirklich gesprochen?
     *
     * Dieselbe Schwelle wie MarkFirstCall (MIN_DURATION_SECONDS = 1).
     */
    private function hasCompletedCall(Tenant $workspace): bool
    {
        return CallAttempt::query()
            ->where('tenant_id', $workspace->getKey())
            ->where('duration_seconds', '>', 0)
            ->exists();
    }
}

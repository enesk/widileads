<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\LeadContactStatus;
use App\Constants\LeadResolutionReason;
use App\Models\Lead;
use App\Models\Scopes\TenantScopes;
use App\Services\LeadResolver;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * FB-084: Schliesst Leads ab, deren Frist zur Erreichbarkeitspruefung abgelaufen ist.
 *
 * Wer den Lead sieben Tage lang (config('lead_calls.deadline_days')) nicht ans
 * Telefon bekommen hat, ohne dass die Versuche ausgeschoepft waren, hatte
 * Gelegenheit genug -- der Lead wird abgerechnet (billable / deadline). Die
 * Gutschrift ist dem Fall vorbehalten, in dem der Kaeufer die Versuche
 * tatsaechlich unternommen hat (three_attempts, FB-083).
 *
 * Entschieden wird ueber LeadResolver::resolve(); die Sperre dort haelt den
 * Lauf gegen einen gleichzeitig eintreffenden Twilio-Rueckruf sauber.
 */
class ResolveExpiredLeads extends Command
{
    protected $signature = 'leads:resolve-expired';

    protected $description = 'Resolve leads whose contact deadline has elapsed as billable.';

    public function __construct(private readonly LeadResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $resolved = 0;

        // chunkById statt get()/all(): Der Lauf kann bei einer Fristwelle
        // tausende Leads umfassen und laedt sie sonst allesamt in den Speicher.
        // Da der Lauf `contact_status` selbst veraendert, fallen die bereits
        // bearbeiteten Zeilen aus der Menge -- ein Offset-Paging wuerde dabei
        // Zeilen ueberspringen, die Sortierung nach id nicht.
        $this->expiredLeads()->chunkById(200, function ($leads) use (&$resolved): void {
            foreach ($leads as $lead) {
                if ($this->resolver->resolve($lead, LeadResolutionReason::DEADLINE)) {
                    $resolved++;
                }
            }
        });

        Log::info('Fristablauf abgearbeitet.', ['resolved' => $resolved]);

        $this->info(sprintf('%d Lead(s) nach Fristablauf abgeschlossen.', $resolved));

        return self::SUCCESS;
    }

    /**
     * Die faelligen Leads: offen, noch nicht in einem Endzustand, Frist
     * abgelaufen, vom Regelwerk erfasst.
     *
     * @return Builder<Lead>
     */
    private function expiredLeads(): Builder
    {
        return self::expiredLeadsQuery((string) config('lead_calls.enforce_from'));
    }

    /**
     * Die Trefferbedingung des Fristablaufs, abfragbar ohne den Lauf.
     *
     * Ohne Mandanten-Scopes, weil der Lauf ohne Kaeuferkontext auf der Konsole
     * stattfindet -- mit Scope faende er nichts.
     *
     * Der Cutover aus config('lead_calls.enforce_from') schuetzt Bestandsleads
     * wie im LeadResolver: Wer vor der Einfuehrung ausgeliefert wurde, hatte
     * nie die Gelegenheit, ueber das Portal anzurufen.
     *
     * Die Klammer auf `lead_state` haelt Leads heraus, deren Reklamation
     * anerkannt wurde (Ticket #92): Der LeadComplaintService wechselt sie nach
     * `ungueltig`/`unerreichbar` und gibt das Geld zurueck, laesst
     * `contact_status` aber auf `open` stehen. Ohne die Klammer wuerden sie
     * nachts als `billable`/`deadline` abgeschlossen und waeren in jeder
     * Auswertung auf der falschen Seite gezaehlt. Welche Zustaende das sind,
     * steht im LeadResolver -- `erreicht` gehoert ausdruecklich nicht dazu.
     *
     * Ticket #94: Die Methode ist statisch und nimmt Cutover und Stichtag als
     * Parameter, damit `leads:cutover-status` den Trockenlauf gegen genau diese
     * Bedingung rechnet, statt sie abzuschreiben. Wird hier etwas geaendert,
     * aendert sich der Trockenlauf mit.
     *
     * @param  string|null  $enforceFrom  Cutover; leer heisst Regelwerk aus
     * @param  Carbon|null  $at  Stichtag des Laufs; Vorgabe ist jetzt
     * @return Builder<Lead>
     */
    public static function expiredLeadsQuery(?string $enforceFrom, ?Carbon $at = null): Builder
    {
        $query = Lead::query()
            ->withoutGlobalScopes(TenantScopes::names())
            ->where('contact_status', LeadContactStatus::OPEN)
            ->whereNotIn('lead_state', LeadResolver::undecidableStates())
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', $at ?? now());

        // Ohne Cutover ist das Regelwerk aus und der Lauf findet nichts --
        // besser als der Gegenfall, in dem eine fehlende Zeile in der `.env`
        // den gesamten Bestand erfasst.
        if ($enforceFrom === null || $enforceFrom === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', Carbon::parse($enforceFrom));
    }
}

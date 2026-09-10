<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\FunnelFieldKey;
use App\Constants\LeadState;
use App\Constants\LeadTransitionReason;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Services\LeadStateService;
use Illuminate\Database\Seeder;

/**
 * Testleads fuer den Funnel "Sichere dein Tier jetzt ab".
 *
 * Aufruf:
 *
 *     php artisan db:seed --class=TierAbsicherungLeadsSeeder
 *
 * Setzt TierAbsicherungFunnelSeeder voraus -- ohne den Funnel gibt es nichts,
 * woran ein Lead haengen koennte.
 *
 * Bewusst nicht Teil des DatabaseSeeder: Der Bestand dient dem Ausprobieren von
 * Marktplatz, Kauf und Reklamation, nicht einer frischen Installation.
 *
 * Drei Punkte, die den Aufbau erklaeren:
 *
 * - **Der Zustandswechsel laeuft ueber LeadStateService::transition().** Ein
 *   direkt gesetztes `lead_state` waere kuerzer, liesse aber das
 *   Zustandsprotokoll leer -- und die Architekturregel aus FB-042 schlaegt
 *   darauf an.
 * - **Die Antworten sind durchgerechnet, nicht zufaellig.** Ein Bestand, der
 *   sich bei jedem Lauf anders verhaelt, taugt nicht zum Nachstellen eines
 *   Fehlers.
 * - **Die Postleitzahlen streuen ueber Nord, Sued und Mitte.** Sonst waere im
 *   Marktplatz nie zu sehen, dass die Regionsfilter der Kaufkriterien greifen.
 *
 * Der Seeder ist wiederholbar: Er erkennt seine eigenen Leads an der Kampagne
 * und legt sie kein zweites Mal an.
 */
class TierAbsicherungLeadsSeeder extends Seeder
{
    private const FUNNEL_SLUG = 'tier-absicherung';

    /** Erkennungsmerkmal des eigenen Bestands. */
    private const CAMPAIGN = 'tier-absicherung-test';

    public function __construct(private readonly LeadStateService $leadStates) {}

    public function run(): void
    {
        $funnel = $this->funnel();

        if ($funnel === null) {
            $this->command?->warn('Funnel "'.self::FUNNEL_SLUG.'" fehlt - erst TierAbsicherungFunnelSeeder laufen lassen.');

            return;
        }

        $existing = Lead::query()
            ->withoutGlobalScopes()
            ->where('funnel_id', $funnel->getKey())
            ->where('utm_campaign', self::CAMPAIGN)
            ->count();

        if ($existing > 0) {
            $this->command?->info($existing.' Testleads sind bereits vorhanden - nichts zu tun.');

            return;
        }

        $version = $funnel->currentVersion ?? $funnel->versions()->latest('id')->first();
        $created = 0;

        foreach ($this->records() as $index => $record) {
            $lead = Lead::query()->create([
                'tenant_id' => $funnel->tenant_id,
                'funnel_id' => $funnel->getKey(),
                'funnel_version_id' => $version?->getKey(),
                'score' => $this->scoreFor($record),
                'price_at_creation' => config('funnel.lead.default_price'),
                'phone_e164' => $record[FunnelFieldKey::TELEFON->value],
                'email_normalized' => $record[FunnelFieldKey::EMAIL->value],
                'postal_code' => $record[FunnelFieldKey::PLZ->value],
                'utm_source' => 'seeder',
                'utm_medium' => 'test',
                'utm_campaign' => self::CAMPAIGN,
                // Ueber die letzten Tage verteilt, damit Sortierung und
                // Zeitfilter etwas zu zeigen haben.
                'created_at' => now()->subHours(3 * $index + 1),
            ]);

            foreach ($record as $fieldKey => $value) {
                LeadAnswer::query()->create([
                    'lead_id' => $lead->getKey(),
                    'field_key' => $fieldKey,
                    'value' => $value,
                ]);
            }

            // Ohne das Neuladen bliebe lead_state null: Die Spalte steht in
            // $guarded, ihren Eingangswert setzt der Datenbank-Standard.
            $this->leadStates->transition(
                $lead->refresh(),
                LeadState::VERFUEGBAR,
                LeadTransitionReason::SCREENING_PASSED,
            );

            $created++;
        }

        $this->command?->info($created.' Testleads fuer "'.$funnel->name.'" angelegt, alle im Zustand verfuegbar.');
    }

    /**
     * Der Betreiber-Mandant, dem der Funnel gehoert. Ohne das Abschalten der
     * Scopes laeuft der Seeder je nach Aufrufweg gegen den falschen Mandanten.
     */
    private function funnel(): ?Funnel
    {
        return Funnel::query()
            ->withoutGlobalScopes()
            ->where('slug', self::FUNNEL_SLUG)
            ->whereHas('tenant', static fn ($query) => $query->where('type', TenantType::OPERATOR->value))
            ->orderBy('id')
            ->first();
    }

    /**
     * Acht Leads, die den Fragebogen abdecken: Hund, Katze und Pferd, gesund
     * und vorerkrankt, Norden, Sueden und Mitte.
     *
     * @return list<array<string, string>>
     */
    private function records(): array
    {
        return [
            $this->record('Lena Voss', 'hund', 'Labrador', '3 Jahre', 'gesund', 34, '22301', 1),
            $this->record('Jonas Brandt', 'katze', 'Britisch Kurzhaar', '7 Jahre', 'vorerkrankungen', 41, '20355', 2),
            $this->record('Mira Kern', 'pferd', 'Haflinger', '11 Jahre', 'gesund', 52, '24103', 3),
            $this->record('Tobias Hoffmann', 'hund', 'Mischling', '1 Jahr', 'gesund', 29, '80331', 4),
            $this->record('Sarah Ludwig', 'katze', 'Maine Coon', '5 Jahre', 'gesund', 37, '81667', 5),
            $this->record('Kerem Demir', 'hund', 'Franzoesische Bulldogge', '6 Jahre', 'vorerkrankungen', 45, '90402', 6),
            $this->record('Anke Sauer', 'pferd', 'Islaender', '14 Jahre', 'vorerkrankungen', 58, '50667', 7),
            $this->record('Paul Roth', 'hund', 'Border Collie', '2 Jahre', 'gesund', 31, '60311', 8),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function record(
        string $name,
        string $tierart,
        string $rasse,
        string $tierAlter,
        string $gesundheitszustand,
        int $halterAlter,
        string $plz,
        int $index,
    ): array {
        [$vorname, $nachname] = explode(' ', $name, 2);

        return [
            'tierart' => $tierart,
            'rasse' => $rasse,
            'tier_alter' => $tierAlter,
            'gesundheitszustand' => $gesundheitszustand,
            'halter_alter' => (string) $halterAlter,
            FunnelFieldKey::NAME->value => $name,
            FunnelFieldKey::EMAIL->value => sprintf('%s.%s@example.test', mb_strtolower($vorname), mb_strtolower($nachname)),
            FunnelFieldKey::TELEFON->value => '+4915'.str_pad((string) (3000000 + $index), 8, '0', STR_PAD_LEFT),
            FunnelFieldKey::PLZ->value => $plz,
        ];
    }

    /**
     * Punktzahl aus den Antwortoptionen der Vorlage. Die Werte stehen so in
     * database/templates/tier-absicherung.json; sie hier zu wiederholen ist der
     * Preis dafuer, den Bestand ohne die Runtime aufbauen zu koennen.
     *
     * @param  array<string, string>  $record
     */
    private function scoreFor(array $record): int
    {
        $tierart = ['hund' => 2, 'katze' => 1, 'pferd' => 3];
        $zustand = ['gesund' => 0, 'vorerkrankungen' => 3];

        return ($tierart[$record['tierart']] ?? 0)
            + ($zustand[$record['gesundheitszustand']] ?? 0);
    }
}

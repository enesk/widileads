<?php

declare(strict_types=1);

namespace App\Actions;

use App\Constants\FunnelFieldKey;
use App\Dto\FunnelSubmissionData;
use App\Events\Lead\LeadCreated;
use App\Funnel\Runtime\SubmissionReceiver;
use App\Models\Funnel;
use App\Models\FunnelVersion;
use App\Models\Lead;
use App\Models\LeadAnswer;
use App\Models\PublicSession;
use Illuminate\Support\Facades\DB;

/**
 * Legt aus einer abgeschlossenen Funnel-Sitzung einen Lead an (FB-031).
 *
 * Das ist der Eingang jedes Leads. Die Runtime (FB-020) kennt nur das Interface
 * SubmissionReceiver und uebergibt ein FunnelSubmissionData -- diese Klasse ist
 * die Umsetzung dahinter.
 *
 * Was hier bewusst NICHT passiert:
 *
 * - Der Verlauf wird nicht kopiert. Seit FB-021 fuehren public_sessions und
 *   session_events ihn als einzige Quelle; der Lead zeigt ueber
 *   `public_session_id` darauf. Zwei Wahrheiten ueber denselben Weg waeren
 *   schlimmer als eine. Ebenso bleiben `started_at` und `completed_at` an der
 *   Sitzung.
 * - Das Ergebnis wird nicht als Fremdschluessel auf funnel_results abgelegt,
 *   sondern als `result_key` aus dem Snapshot ("4-7") zusammen mit
 *   `funnel_version_id`. Die Live-Zeile darf sich aendern, die veroeffentlichte
 *   Fassung nicht -- genau dafuer gibt es die Versionierung.
 * - Es wird nichts bewertet. Der Lead entsteht im Zustand `neu`; ob er kaufbar
 *   ist, entscheidet der Pruefjob aus FB-033 (Architekturleitsatz 3: Beweis vor
 *   Bewertung).
 *
 * Die Herkunftsdaten werden dagegen von der Sitzung an den Lead uebernommen:
 * Sie gehoeren zur Qualitaet des verkauften Leads und muessen ihn ueberleben,
 * auch wenn die Sitzung spaeter aufgeraeumt wird. Erhoben werden sie beim Start
 * der Sitzung (FB-022) -- hier wird nur kopiert.
 */
class CreateLeadFromSession implements SubmissionReceiver
{
    /**
     * Herkunftsspalten, die eins zu eins von der Sitzung uebernommen werden.
     *
     * @var list<string>
     */
    private const ORIGIN_COLUMNS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'referrer',
        'embed_origin',
        'ip_hash',
        'user_agent',
    ];

    public function receive(FunnelSubmissionData $submission): void
    {
        $this->handle($submission);
    }

    /**
     * Idempotent je Sitzung: Ein zweiter Aufruf mit derselben Sitzung liefert
     * den bereits angelegten Lead zurueck und schreibt nichts. Abgesichert ist
     * das zusaetzlich durch den Unique-Index auf `leads.public_session_id` --
     * die Zusage haengt nicht allein an dieser Pruefung.
     */
    public function handle(FunnelSubmissionData $submission): Lead
    {
        $existing = $this->existingLeadFor($submission->publicSessionId);

        if ($existing !== null) {
            return $existing;
        }

        $version = FunnelVersion::query()->findOrFail($submission->funnelVersionId);
        $funnel = Funnel::query()->withoutGlobalScopes()->findOrFail($version->funnel_id);
        $session = PublicSession::query()->find($submission->publicSessionId);

        return DB::transaction(function () use ($submission, $funnel, $version, $session): Lead {
            $lead = Lead::query()->create([
                'tenant_id' => $funnel->tenant_id,
                'funnel_id' => $funnel->id,
                'funnel_version_id' => $version->id,
                'public_session_id' => $submission->publicSessionId,
                // Nur der Verweis, keine Entscheidung: Ob daraus eine Dublette
                // wird, klaert der Pruefjob (FB-033).
                'duplicate_of_lead_id' => $submission->duplicateOfLeadId,
                'score' => $submission->score,
                'result_key' => $submission->resultKey,
                // Der Preis wird beim Entstehen festgehalten: Aendert der
                // Betreiber ihn spaeter, gilt fuer diesen Lead weiterhin, was
                // zum Zeitpunkt der Anfrage galt.
                'price_at_creation' => $funnel->effectiveLeadPrice(),
                'phone_e164' => $this->contactValue($submission, FunnelFieldKey::TELEFON),
                'email_normalized' => $this->contactValue($submission, FunnelFieldKey::EMAIL),
                // Steht auch in lead_answers -- hier zusaetzlich als Spalte, damit
                // die Lead-Liste nach PLZ-Praefix filtern kann (FB-034).
                'postal_code' => $this->contactValue($submission, FunnelFieldKey::PLZ),
                ...$this->originFrom($session),
            ]);

            $this->storeAnswers($lead, $submission->answers);

            // lead_state steht in $guarded und kommt aus dem Standardwert der
            // Spalte (`neu`). Ohne dieses Nachladen traege der zurueckgegebene
            // Lead an dieser Stelle null -- und jeder Zuhoerer von LeadCreated
            // muesste selbst daran denken.
            $lead->refresh();

            LeadCreated::dispatch($lead);

            return $lead;
        });
    }

    private function existingLeadFor(int $publicSessionId): ?Lead
    {
        return Lead::query()
            ->withoutGlobalScopes()
            ->where('public_session_id', $publicSessionId)
            ->first();
    }

    /**
     * Rohantworten eins zu eins -- ohne Auswahl, ohne Bewertung.
     *
     * @param  array<string, mixed>  $answers
     */
    private function storeAnswers(Lead $lead, array $answers): void
    {
        foreach ($answers as $fieldKey => $value) {
            LeadAnswer::query()->create([
                'lead_id' => $lead->getKey(),
                'field_key' => (string) $fieldKey,
                'value' => $value,
            ]);
        }
    }

    /**
     * Einzelner Kontaktwert aus den Antworten. Die Werte sind bereits
     * normalisiert (Telefon in E.164, E-Mail kleingeschrieben) -- hier wird
     * nichts nachbearbeitet, sondern nur herausgegriffen.
     */
    private function contactValue(FunnelSubmissionData $submission, FunnelFieldKey $fieldKey): ?string
    {
        $value = $submission->answers[$fieldKey->value] ?? null;

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * Herkunft der Sitzung -- eins zu eins uebernommen.
     *
     * Erhoben und gehasht wird beim Start der Sitzung (FB-022, IpHasher). Hier
     * wird nichts neu ermittelt und nichts neu gehasht: zwei Verfahren wuerden
     * zwei unterschiedliche Hashes derselben Adresse ergeben und jeden Vergleich
     * wertlos machen.
     *
     * @return array<string, mixed>
     */
    private function originFrom(?PublicSession $session): array
    {
        if ($session === null) {
            return [];
        }

        $origin = [];

        foreach (self::ORIGIN_COLUMNS as $column) {
            $origin[$column] = $session->getAttribute($column);
        }

        return $origin;
    }
}

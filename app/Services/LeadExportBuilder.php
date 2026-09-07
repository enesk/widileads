<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\AuditAction;
use App\Constants\LeadExportStatus;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\LeadExport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Schreibt einen angeforderten Lead-Export als CSV (FB-073).
 *
 * Die Kontaktdaten kommen ausschliesslich ueber `Lead::contactFor()` -- der
 * Export ist der Vorgang, bei dem die meisten Daten auf einmal das Haus
 * verlassen, und er darf die Maskierung nicht umgehen. Wer den Export
 * angefordert hat, entscheidet damit ueber seinen Inhalt: derselbe Ausschnitt
 * sieht fuer verschiedene Leute verschieden aus.
 *
 * Geschrieben wird zeilenweise in einen Stream. Eine Datei mit 50.000 Leads
 * soll den Arbeitsspeicher nicht sehen.
 */
class LeadExportBuilder
{
    /**
     * Waehlbare Spalten. Der Schluessel steht im Export, die Beschriftung in
     * der Kopfzeile.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'id',
        'created_at',
        'funnel',
        'lead_state',
        'score',
        'result_key',
        'price_at_creation',
        'name',
        'email',
        'phone',
        'postal_code',
        'utm_source',
        'utm_campaign',
        'embed_origin',
        'answers',
    ];

    public function __construct(
        private readonly LeadListQuery $leads,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function build(LeadExport $export): void
    {
        $export->forceFill(['status' => LeadExportStatus::RUNNING])->save();

        $tenant = $export->tenant;
        $requester = $export->requester;

        if ($tenant === null || ! $requester instanceof User) {
            $export->forceFill([
                'status' => LeadExportStatus::FAILED,
                'failure_reason' => __('exports.errors.no_requester'),
            ])->save();

            return;
        }

        $columns = array_values(array_intersect($export->columns, self::COLUMNS));
        $disk = (string) config('funnel.export.disk');
        $path = 'lead-exports/'.$export->uuid.'.csv';

        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            $export->forceFill([
                'status' => LeadExportStatus::FAILED,
                'failure_reason' => __('exports.errors.not_writable'),
            ])->save();

            return;
        }

        // Byte Order Mark: Ohne sie zeigt Excel Umlaute als Buchstabensalat.
        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, array_map(static fn (string $column): string => __('exports.columns.'.$column), $columns), ';');

        $rows = 0;

        $this->leads->build($tenant, $requester, $export->filters ?? [])
            ->with(['answers', 'funnel'])
            ->chunkById((int) config('funnel.export.chunk_size'), function ($leads) use ($handle, $columns, $requester, &$rows): void {
                foreach ($leads as $lead) {
                    fputcsv($handle, $this->row($lead, $columns, $requester), ';');
                    $rows++;
                }
            });

        rewind($handle);
        Storage::disk($disk)->put($path, $handle);
        fclose($handle);

        $export->forceFill([
            'status' => LeadExportStatus::READY,
            'disk' => $disk,
            'path' => $path,
            'row_count' => $rows,
            'completed_at' => now(),
        ])->save();

        // Ein Export ist eine Offenlegung -- FB-005 hat die Aktion dafuer
        // vorbereitet.
        $this->auditLogger->log(
            AuditAction::DATA_EXPORTED,
            subject: $export,
            payload: ['columns' => $columns, 'rows' => $rows],
            tenant: $tenant,
            user: $requester,
        );
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function row(Lead $lead, array $columns, User $viewer): array
    {
        $contact = $lead->contactFor($viewer);

        $values = [
            'id' => (string) $lead->id,
            'created_at' => $lead->created_at?->format('Y-m-d H:i') ?? '',
            'funnel' => $lead->funnel instanceof Funnel ? $lead->funnel->name : '',
            'lead_state' => $lead->lead_state->label(),
            'score' => (string) $lead->score,
            'result_key' => (string) ($lead->result_key ?? ''),
            'price_at_creation' => (string) ($lead->price_at_creation ?? ''),
            'name' => (string) ($contact->fullName() ?? ''),
            'email' => (string) ($contact->email ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
            'postal_code' => (string) ($contact->postalCode ?? ''),
            'utm_source' => (string) ($lead->utm_source ?? ''),
            'utm_campaign' => (string) ($lead->utm_campaign ?? ''),
            'embed_origin' => (string) ($lead->embed_origin ?? ''),
            'answers' => $this->answers($lead),
        ];

        return array_map(static fn (string $column): string => $values[$column] ?? '', $columns);
    }

    /**
     * Die Qualifizierungsantworten -- ohne die reservierten Kontaktfelder. Die
     * stehen bereits in den Kontaktspalten und wuerden hier an der Maskierung
     * vorbeilaufen.
     */
    private function answers(Lead $lead): string
    {
        $parts = [];

        foreach ($lead->answers as $answer) {
            if ($answer->isPersonal()) {
                continue;
            }

            $value = $answer->value;

            $parts[] = $answer->field_key.': '.(is_array($value)
                ? implode(', ', array_map(static fn (mixed $part): string => (string) $part, $value))
                : (string) $value);
        }

        return implode(' | ', $parts);
    }
}

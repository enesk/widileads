<?php

declare(strict_types=1);

namespace App\Actions;

use App\Constants\FunnelFieldKey;
use App\Constants\FunnelStatus;
use App\Events\Funnel\FunnelPublished;
use App\Exceptions\FunnelNotPublishableException;
use App\Funnel\Results\ResultRangeValidator;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\SnapshotBuilder;
use App\Models\Funnel;
use App\Models\FunnelVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Veroeffentlicht einen Funnel (FB-014).
 *
 * Publish ist der Punkt, ab dem eine Fassung fuer den Endkunden verbindlich
 * wird. Deshalb wird hier geprueft und nicht spaeter: mindestens ein Schritt,
 * ein erreichbarer Kontaktweg und lueckenlose Ergebnisbereiche. Danach entsteht
 * ein unveraenderlicher Snapshot; Aenderungen am Entwurf beruehren die laufende
 * Strecke nicht mehr, bis erneut veroeffentlicht wird.
 */
class PublishFunnel
{
    public function __construct(
        private readonly SnapshotBuilder $snapshotBuilder,
        private readonly ResultRangeValidator $resultRangeValidator,
    ) {}

    /**
     * @throws FunnelNotPublishableException
     */
    public function handle(Funnel $funnel, ?User $publisher = null, ?string $note = null): FunnelVersion
    {
        $snapshot = $this->snapshotBuilder->build($funnel);

        $reasons = $this->reasonsAgainstPublishing($snapshot);

        if ($reasons !== []) {
            throw new FunnelNotPublishableException($reasons);
        }

        return DB::transaction(function () use ($funnel, $snapshot, $publisher, $note): FunnelVersion {
            // Sperre auf dem Funnel: Zwei gleichzeitige Veroeffentlichungen
            // duerfen nicht dieselbe Versionsnummer vergeben.
            $funnel = Funnel::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($funnel->getKey());

            $version = FunnelVersion::query()->create([
                'funnel_id' => $funnel->id,
                'version' => $this->nextVersionNumber($funnel),
                'note' => $note,
                'snapshot' => $snapshot,
                'published_at' => now(),
                'published_by' => $publisher?->getKey(),
            ]);

            $funnel->forceFill([
                'status' => FunnelStatus::PUBLISHED,
                'current_version_id' => $version->id,
            ])->save();

            // Wer auf Veroeffentlichungen reagieren will -- Webhooks (FB-030e),
            // spaeter Benachrichtigungen -- haengt sich an das Ereignis, statt
            // dass diese Action ihn kennen muss.
            FunnelPublished::dispatch($funnel, $version);

            return $version;
        });
    }

    /**
     * Was einer Veroeffentlichung im Weg steht -- alle Gruende auf einmal.
     *
     * @param  array<string, mixed>  $snapshot
     * @return list<string>
     */
    public function reasonsAgainstPublishing(array $snapshot): array
    {
        $read = FunnelSnapshot::fromArray($snapshot);
        $reasons = [];

        if ($read->steps === []) {
            $reasons[] = __('funnel.version.errors.no_steps');
        }

        if (! $this->hasContactField($read)) {
            $reasons[] = __('funnel.version.errors.no_contact_field', [
                'fields' => implode(', ', $this->requiredContactFieldKeys()),
            ]);
        }

        // Die Bereichspruefung stammt aus FB-013 und wird hier nur aufgerufen.
        $reasons = [...$reasons, ...$this->resultRangeValidator->validate($read)->messages()];

        return $reasons;
    }

    /**
     * Ein Funnel ohne erreichbaren Kontaktweg erzeugt Leads, die niemand
     * kontaktieren kann -- also wertlose Leads.
     */
    private function hasContactField(FunnelSnapshot $snapshot): bool
    {
        $fieldKeys = array_map(
            static fn ($question): string => $question->fieldKey,
            $snapshot->questions(),
        );

        foreach ($this->requiredContactFieldKeys() as $contactFieldKey) {
            if (in_array($contactFieldKey, $fieldKeys, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function requiredContactFieldKeys(): array
    {
        /** @var list<string> $configured */
        $configured = config('funnel.publish.required_contact_field_keys', []);

        return array_values(array_filter(
            $configured,
            static fn (string $fieldKey): bool => FunnelFieldKey::tryFrom($fieldKey) !== null,
        ));
    }

    private function nextVersionNumber(Funnel $funnel): int
    {
        return (int) FunnelVersion::query()->where('funnel_id', $funnel->id)->max('version') + 1;
    }
}

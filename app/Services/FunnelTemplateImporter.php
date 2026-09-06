<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\FunnelStatus;
use App\Exceptions\FunnelTemplateNotFound;
use App\Models\Funnel;
use App\Models\FunnelCondition;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelResult;
use App\Models\FunnelStep;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Legt aus einer Vorlage einen echten Funnel in den Live-Tabellen an (FB-019).
 *
 * Die Vorlagen liegen als JSON unter `database/templates` im Snapshot-Format
 * (siehe docs/funnel-builder/snapshot-format.md): adressiert wird ueber
 * `position` und `field_key`, nie ueber Datenbank-IDs. Der Importer ist die
 * Gegenrichtung des SnapshotBuilder -- er liest dasselbe Format und schreibt
 * daraus die Entwurfsfassung, aus der `PublishFunnel` spaeter wieder einen
 * Snapshot macht.
 *
 * Angelegt wird immer ein Entwurf. Ob und wann veroeffentlicht wird, entscheidet
 * der Betreiber.
 */
class FunnelTemplateImporter
{
    /**
     * Schluessel aller Vorlagen, die unter database/templates liegen.
     *
     * @return list<string>
     */
    public function availableTemplates(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'*.json');

        if ($files === false) {
            return [];
        }

        return array_values(array_map(
            static fn (string $path): string => basename($path, '.json'),
            $files,
        ));
    }

    /**
     * Legt die Vorlage als neuen Entwurf fuer diesen Mandanten an.
     *
     * @throws FunnelTemplateNotFound
     */
    public function import(string $templateKey, Tenant $tenant): Funnel
    {
        $template = $this->read($templateKey);

        return DB::transaction(function () use ($template, $tenant): Funnel {
            $funnel = $this->createFunnel($template['funnel'] ?? [], $tenant);

            /** @var array<int, int> $stepIdsByPosition */
            $stepIdsByPosition = [];
            /** @var array<string, int> $questionIdsByFieldKey */
            $questionIdsByFieldKey = [];

            foreach ($template['steps'] ?? [] as $step) {
                $created = FunnelStep::query()->create([
                    'funnel_id' => $funnel->id,
                    'position' => (int) $step['position'],
                    'title' => $step['title'] ?? null,
                    'description' => $step['description'] ?? null,
                ]);

                $stepIdsByPosition[(int) $step['position']] = (int) $created->id;

                foreach ($step['questions'] ?? [] as $question) {
                    $createdQuestion = FunnelQuestion::query()->create([
                        'step_id' => $created->id,
                        'position' => (int) $question['position'],
                        'type' => (string) $question['type'],
                        'field_key' => (string) $question['field_key'],
                        'label' => (string) $question['label'],
                        'help_text' => $question['help_text'] ?? null,
                        'required' => (bool) ($question['required'] ?? false),
                        'validation' => $question['validation'] ?? null,
                        'meta' => $question['meta'] ?? null,
                    ]);

                    // Der gespeicherte Schluessel gilt: das Model normalisiert ihn.
                    $questionIdsByFieldKey[$createdQuestion->field_key] = (int) $createdQuestion->id;

                    foreach ($question['options'] ?? [] as $option) {
                        FunnelOption::query()->create([
                            'question_id' => $createdQuestion->id,
                            'position' => (int) $option['position'],
                            'label' => (string) $option['label'],
                            'value' => (string) $option['value'],
                            'score' => $option['score'] ?? null,
                            'image_path' => $option['image_path'] ?? null,
                        ]);
                    }
                }
            }

            foreach ($template['results'] ?? [] as $result) {
                FunnelResult::query()->create([
                    'funnel_id' => $funnel->id,
                    'min_score' => (int) $result['min_score'],
                    'max_score' => (int) $result['max_score'],
                    'title' => (string) $result['title'],
                    'body' => $result['body'] ?? null,
                    'cta_label' => $result['cta_label'] ?? null,
                    'cta_url' => $result['cta_url'] ?? null,
                    'show_contact_form' => (bool) ($result['show_contact_form'] ?? true),
                ]);
            }

            foreach ($template['conditions'] ?? [] as $condition) {
                $sourceId = $questionIdsByFieldKey[(string) $condition['source_field_key']] ?? null;
                $targetId = $stepIdsByPosition[(int) $condition['target_step_position']] ?? null;

                // Eine Regel, deren Frage oder Zielschritt es nicht gibt, wird
                // uebergangen statt den ganzen Import scheitern zu lassen.
                if ($sourceId === null || $targetId === null) {
                    continue;
                }

                FunnelCondition::query()->create([
                    'funnel_id' => $funnel->id,
                    'source_question_id' => $sourceId,
                    'operator' => (string) $condition['operator'],
                    'value' => $condition['value'] ?? null,
                    'target_step_id' => $targetId,
                    'priority' => (int) ($condition['priority'] ?? 0),
                ]);
            }

            return $funnel;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createFunnel(array $attributes, Tenant $tenant): Funnel
    {
        $name = (string) ($attributes['name'] ?? 'Funnel');

        return Funnel::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            'slug' => $this->uniqueSlug((string) ($attributes['slug'] ?? Str::slug($name)), $tenant),
            'status' => FunnelStatus::DRAFT,
            'lead_price' => $attributes['lead_price'] ?? null,
            'contact_step_position' => $attributes['contact_step_position'] ?? null,
            'settings' => $attributes['settings'] ?? null,
        ]);
    }

    /**
     * Der Slug ist je Mandant eindeutig. Eine zweite Einrichtung derselben
     * Vorlage soll trotzdem gelingen, statt an einem Unique-Index zu scheitern.
     */
    private function uniqueSlug(string $slug, Tenant $tenant): string
    {
        $base = Str::slug($slug);
        $candidate = $base;
        $suffix = 1;

        while ($this->slugTaken($candidate, $tenant)) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    private function slugTaken(string $slug, Tenant $tenant): bool
    {
        return Funnel::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FunnelTemplateNotFound
     */
    private function read(string $templateKey): array
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.basename($templateKey).'.json';

        if (! is_file($path)) {
            throw FunnelTemplateNotFound::forKey($templateKey);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function directory(): string
    {
        return database_path('templates');
    }
}

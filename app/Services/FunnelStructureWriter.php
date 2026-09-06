<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\FunnelStatus;
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
 * Schreibt eine Funnel-Struktur im Snapshot-Format in die Live-Tabellen.
 *
 * Die Gegenrichtung des SnapshotBuilder (FB-014): der liest die Live-Tabellen
 * und schreibt das Format, dieser liest das Format und schreibt die
 * Live-Tabellen. Beide Wege sprechen dasselbe unter
 * docs/funnel-builder/snapshot-format.md beschriebene Format -- adressiert wird
 * ueber `position` und `field_key`, nie ueber Datenbank-IDs.
 *
 * Genutzt wird der Schreiber von zwei Stellen mit demselben Problem: dem Import
 * einer Vorlage (FB-019) und der tiefen Kopie eines Funnels (FB-018). Beide
 * legen einen Entwurf an; Token und Slug vergibt immer dieser Schreiber, damit
 * dieselbe Struktur beliebig oft angelegt werden kann.
 */
class FunnelStructureWriter
{
    /**
     * @param  array<string, mixed>  $structure  Funnel im Snapshot-Format
     * @param  array<string, mixed>  $funnelOverrides  Werte, die Vorrang vor der Struktur haben
     */
    public function write(array $structure, Tenant $tenant, array $funnelOverrides = []): Funnel
    {
        return DB::transaction(function () use ($structure, $tenant, $funnelOverrides): Funnel {
            /** @var array<string, mixed> $attributes */
            $attributes = $structure['funnel'] ?? [];

            $funnel = $this->createFunnel($attributes, $tenant, $funnelOverrides);

            /** @var array<int, int> $stepIdsByPosition */
            $stepIdsByPosition = [];
            /** @var array<string, int> $questionIdsByFieldKey */
            $questionIdsByFieldKey = [];

            foreach ($structure['steps'] ?? [] as $step) {
                $createdStep = FunnelStep::query()->create([
                    'funnel_id' => $funnel->id,
                    'position' => (int) $step['position'],
                    'title' => $step['title'] ?? null,
                    'description' => $step['description'] ?? null,
                ]);

                $stepIdsByPosition[(int) $step['position']] = (int) $createdStep->id;

                foreach ($step['questions'] ?? [] as $question) {
                    $createdQuestion = FunnelQuestion::query()->create([
                        'step_id' => $createdStep->id,
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

            foreach ($structure['results'] ?? [] as $result) {
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

            foreach ($structure['conditions'] ?? [] as $condition) {
                $sourceId = $questionIdsByFieldKey[(string) $condition['source_field_key']] ?? null;
                $targetId = $stepIdsByPosition[(int) $condition['target_step_position']] ?? null;

                // Eine Regel, deren Frage oder Zielschritt es nicht gibt, wird
                // uebergangen statt den ganzen Vorgang scheitern zu lassen.
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
     * @param  array<string, mixed>  $overrides
     */
    private function createFunnel(array $attributes, Tenant $tenant, array $overrides): Funnel
    {
        $name = (string) ($overrides['name'] ?? $attributes['name'] ?? 'Funnel');

        return Funnel::query()->create([
            'tenant_id' => $tenant->getKey(),
            'name' => $name,
            // public_token bleibt bewusst leer: das Model vergibt einen neuen.
            // Ein uebernommener Token waere nicht eindeutig.
            'slug' => $this->uniqueSlug((string) ($overrides['slug'] ?? $attributes['slug'] ?? Str::slug($name)), $tenant),
            'status' => FunnelStatus::DRAFT,
            'lead_price' => $overrides['lead_price'] ?? $attributes['lead_price'] ?? null,
            'contact_step_position' => $overrides['contact_step_position'] ?? $attributes['contact_step_position'] ?? null,
            'settings' => $overrides['settings'] ?? $attributes['settings'] ?? null,
        ]);
    }

    /**
     * Der Slug ist je Mandant eindeutig. Eine zweite Kopie derselben Struktur
     * soll trotzdem gelingen, statt an einem Unique-Index zu scheitern.
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
}

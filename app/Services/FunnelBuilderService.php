<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\QuestionType;
use App\Exceptions\FunnelConcurrentlyModified;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bearbeitet die Live-Tabellen eines Funnels fuer den Builder (FB-015).
 *
 * Die oeffentliche Auslieferung liest ausschliesslich Snapshots (FB-014/FB-020);
 * dieser Dienst fasst nur den Entwurfsstand an. Jede schreibende Methode nimmt
 * den Stand entgegen, auf dem der Bearbeiter aufgesetzt hat, und bricht ab, wenn
 * der Datensatz zwischenzeitlich von jemand anderem geaendert wurde.
 */
class FunnelBuilderService
{
    /**
     * Format, in dem der Bearbeitungsstand zwischen Server und Oberflaeche
     * ausgetauscht wird. Millisekunden sind bewusst enthalten, damit zwei
     * Speichervorgaenge innerhalb derselben Sekunde unterscheidbar bleiben
     * (dafuer tragen funnel_steps und funnel_questions timestamp(3)).
     */
    public const TIMESTAMP_FORMAT = 'Y-m-d H:i:s.v';

    public function addStep(Funnel $funnel, string $title): FunnelStep
    {
        return $funnel->steps()->create([
            'title' => $title,
            'position' => $this->nextPosition($funnel->steps()->max('position')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws FunnelConcurrentlyModified
     */
    public function updateStep(FunnelStep $step, array $attributes, ?string $seenAt): FunnelStep
    {
        $this->guardAgainstConcurrentEdit($step, $seenAt);

        $step->fill($this->only($attributes, ['title', 'description']));
        $this->advanceStamp($step);
        $step->save();

        return $step->refresh();
    }

    public function deleteStep(FunnelStep $step): void
    {
        DB::transaction(function () use ($step): void {
            $funnel = $step->funnel;

            $step->delete();

            $this->renumber($funnel->steps()->getQuery()->get());
        });
    }

    /**
     * Bringt die Schritte in die uebergebene Reihenfolge.
     *
     * Fremde Kennungen werden ignoriert: die Reihenfolge kommt aus dem Browser
     * und darf keinen Schritt eines anderen Funnels verschieben.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorderSteps(Funnel $funnel, array $orderedIds): void
    {
        $this->applyOrder($funnel->steps()->getQuery()->get(), $orderedIds);
    }

    public function addQuestion(FunnelStep $step, QuestionType $type, string $label, string $fieldKey): FunnelQuestion
    {
        return $step->questions()->create([
            'funnel_id' => $step->funnel_id,
            'type' => $type,
            'label' => $label,
            'field_key' => $fieldKey,
            'required' => false,
            'position' => $this->nextPosition($step->questions()->max('position')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws FunnelConcurrentlyModified
     */
    public function updateQuestion(FunnelQuestion $question, array $attributes, ?string $seenAt): FunnelQuestion
    {
        $this->guardAgainstConcurrentEdit($question, $seenAt);

        $question->fill($this->only($attributes, [
            'type',
            'label',
            'field_key',
            'help_text',
            'required',
            'validation',
            'meta',
        ]));
        $this->advanceStamp($question);
        $question->save();

        return $question->refresh();
    }

    public function deleteQuestion(FunnelQuestion $question): void
    {
        DB::transaction(function () use ($question): void {
            $step = $question->step;

            $question->delete();

            $this->renumber($step->questions()->getQuery()->get());
        });
    }

    /**
     * @param  list<int>  $orderedIds
     */
    public function reorderQuestions(FunnelStep $step, array $orderedIds): void
    {
        $this->applyOrder($step->questions()->getQuery()->get(), $orderedIds);
    }

    public function addOption(FunnelQuestion $question, string $label, string $value): FunnelOption
    {
        return $question->options()->create([
            'label' => $label,
            'value' => $value,
            'position' => $this->nextPosition($question->options()->max('position')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOption(FunnelOption $option, array $attributes): FunnelOption
    {
        $option->fill($this->only($attributes, ['label', 'value', 'score']))->save();

        return $option->refresh();
    }

    public function deleteOption(FunnelOption $option): void
    {
        DB::transaction(function () use ($option): void {
            $question = $option->question;

            $option->delete();

            $this->renumber($question->options()->getQuery()->get());
        });
    }

    /**
     * Bearbeitungsstand eines Datensatzes, wie ihn die Oberflaeche mitfuehrt.
     */
    public function stampOf(FunnelStep|FunnelQuestion $record): ?string
    {
        return $record->updated_at?->format(self::TIMESTAMP_FORMAT);
    }

    /**
     * Sorgt dafuer, dass jeder Speichervorgang den Zeitstempel echt vorruecken
     * laesst.
     *
     * Ein Zeitstempel-Vergleich taugt nur so weit, wie die Uhr aufloest: zwei
     * Speichervorgaenge innerhalb derselben Millisekunde waeren sonst
     * ununterscheidbar, und der zweite Bearbeiter wuerde den ersten
     * ueberschreiben, ohne dass es auffaellt. Deshalb wird der Zeitstempel
     * mindestens eine Millisekunde ueber den bisherigen gesetzt.
     */
    private function advanceStamp(FunnelStep|FunnelQuestion $record): void
    {
        $previous = $record->getOriginal('updated_at');
        $next = now();

        // Verglichen wird in der Aufloesung, in der der Zeitstempel gespeichert
        // wird. Feiner zu vergleichen brachte nichts: Eloquent haelt zwei Werte
        // innerhalb derselben Millisekunde fuer gleich und schriebe die Spalte
        // gar nicht erst.
        if ($previous instanceof Carbon
            && $next->format(self::TIMESTAMP_FORMAT) <= $previous->format(self::TIMESTAMP_FORMAT)) {
            $next = $previous->copy()->addMillisecond();
        }

        $record->updated_at = $next;
    }

    /**
     * Bricht ab, wenn der Datensatz seit dem uebergebenen Stand von jemand
     * anderem geaendert wurde.
     *
     * @throws FunnelConcurrentlyModified
     */
    private function guardAgainstConcurrentEdit(FunnelStep|FunnelQuestion $record, ?string $seenAt): void
    {
        // Ohne mitgefuehrten Stand gaebe es nichts zu vergleichen - das waere ein
        // Programmierfehler im Aufrufer und darf nicht stillschweigend
        // durchgehen.
        if ($seenAt === null) {
            throw new FunnelConcurrentlyModified($record::class);
        }

        $current = $this->stampOf(
            $record::query()->withoutGlobalScopes()->findOrFail($record->getKey()),
        );

        if ($current !== $seenAt) {
            throw new FunnelConcurrentlyModified($record::class);
        }
    }

    /**
     * @param  iterable<FunnelOption|FunnelQuestion|FunnelStep>  $records
     * @param  list<int>  $orderedIds
     */
    private function applyOrder(iterable $records, array $orderedIds): void
    {
        $known = collect($records)->keyBy('id');

        DB::transaction(function () use ($known, $orderedIds): void {
            $position = 1;

            foreach ($orderedIds as $id) {
                $record = $known->get($id);

                if ($record === null) {
                    continue;
                }

                $record->forceFill(['position' => $position++])->save();
                $known->forget($id);
            }

            // Nicht mitgeschickte Datensaetze hinten anhaengen, damit keine
            // Luecke und keine doppelte Position entsteht.
            foreach ($known as $record) {
                $record->forceFill(['position' => $position++])->save();
            }
        });
    }

    /**
     * @param  iterable<FunnelOption|FunnelQuestion|FunnelStep>  $records
     */
    private function renumber(iterable $records): void
    {
        $position = 1;

        foreach ($records as $record) {
            $record->forceFill(['position' => $position++])->save();
        }
    }

    private function nextPosition(mixed $highest): int
    {
        return ((int) $highest) + 1;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function only(array $attributes, array $allowed): array
    {
        return array_intersect_key($attributes, array_flip($allowed));
    }
}

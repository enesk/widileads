<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\ConditionOperator;
use App\Models\Funnel;
use App\Models\FunnelCondition;
use App\Models\FunnelResult;
use App\Services\FunnelRuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor fuer Verzweigungsregeln und Ergebnis-Screens (FB-016).
 *
 * Oben die Regeln - "Wenn [Frage] [Operator] [Wert] dann springe zu [Schritt]",
 * dazu Prioritaet und der Schritt, nach dem die Regel ausgewertet wird. Unten
 * die Ergebnisse mit Bereichsvorschau und Live-Pruefung.
 *
 * Geprueft wird mit dem ResultRangeValidator aus FB-013 - demselben Werkzeug,
 * das beim Veroeffentlichen laeuft. Ein zweiter Validator im Builder wuerde
 * frueher oder spaeter andere Ergebnisse liefern als PublishFunnel.
 */
class FunnelRules extends Component
{
    #[Locked]
    public Funnel $funnel;

    /**
     * Regeln, an die Oberflaeche gebunden: Kennung => Felder.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $conditions = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $results = [];

    public function mount(Funnel $funnel): void
    {
        $this->funnel = $funnel;

        $this->reload();
    }

    public function addCondition(FunnelRuleService $rules): void
    {
        $rules->addCondition($this->funnel);

        $this->reload();
    }

    public function saveCondition(int $conditionId, FunnelRuleService $rules): void
    {
        $condition = $this->funnel->conditions()->findOrFail($conditionId);
        $form = $this->conditions[$conditionId] ?? null;

        if ($form === null) {
            return;
        }

        $this->validateOnly('conditions.'.$conditionId.'.priority');

        $rules->updateCondition($condition, [
            'source_question_id' => (int) $form['source_question_id'],
            'operator' => ConditionOperator::from((string) $form['operator']),
            'value' => $this->parseValue((string) $form['operator'], (string) $form['value']),
            'target_step_id' => (int) $form['target_step_id'],
            'evaluate_at_step_position' => (int) $form['evaluate_at_step_position'],
            'priority' => (int) $form['priority'],
        ]);

        $this->reload();
    }

    public function deleteCondition(int $conditionId, FunnelRuleService $rules): void
    {
        $rules->deleteCondition($this->funnel->conditions()->findOrFail($conditionId));

        $this->reload();
    }

    public function addResult(FunnelRuleService $rules): void
    {
        $rules->addResult($this->funnel);

        $this->reload();
    }

    public function saveResult(int $resultId, FunnelRuleService $rules): void
    {
        $result = $this->funnel->results()->findOrFail($resultId);
        $form = $this->results[$resultId] ?? null;

        if ($form === null) {
            return;
        }

        $this->validate([
            'results.'.$resultId.'.title' => ['required', 'string', 'max:255'],
            'results.'.$resultId.'.min_score' => ['required', 'integer', 'min:0'],
            'results.'.$resultId.'.max_score' => ['required', 'integer', 'min:0'],
        ]);

        $rules->updateResult($result, [
            'min_score' => (int) $form['min_score'],
            'max_score' => (int) $form['max_score'],
            'title' => $form['title'],
            'body' => $form['body'] ?: null,
            'cta_label' => $form['cta_label'] ?: null,
            'cta_url' => $form['cta_url'] ?: null,
            'show_contact_form' => (bool) $form['show_contact_form'],
        ]);

        $this->reload();
    }

    public function deleteResult(int $resultId, FunnelRuleService $rules): void
    {
        $rules->deleteResult($this->funnel->results()->findOrFail($resultId));

        $this->reload();
    }

    public function render(FunnelRuleService $rules): View
    {
        $highestReachable = $rules->highestReachableScore($this->funnel);

        return view('livewire.dashboard.funnel-rules', [
            'questions' => $this->funnel->questions()->orderBy('position')->get(),
            'steps' => $this->funnel->steps()->get(),
            'operators' => ConditionOperator::labels(),
            'highestReachable' => $highestReachable,
            // Dieselbe Pruefung wie beim Veroeffentlichen. Die Meldungen kommen
            // fertig formuliert aus FB-013 und werden unveraendert angezeigt.
            'report' => $rules->checkResultRanges($this->funnel),
            'scale' => max($highestReachable, $this->highestConfiguredScore(), 1),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'conditions.*.priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'results.*.title' => ['required', 'string', 'max:255'],
            'results.*.min_score' => ['required', 'integer', 'min:0'],
            'results.*.max_score' => ['required', 'integer', 'min:0'],
            'conditions.*.operator' => ['required', Rule::in(ConditionOperator::values())],
        ];
    }

    /**
     * Der Vergleichswert kommt als Text aus der Oberflaeche; gespeichert wird er
     * als Liste. "in" nimmt mehrere, durch Komma getrennt; "answered" braucht
     * keinen Wert.
     *
     * @return list<string>
     */
    private function parseValue(string $operator, string $raw): array
    {
        if ($operator === ConditionOperator::ANSWERED->value) {
            return [];
        }

        if ($operator !== ConditionOperator::IN->value) {
            return trim($raw) === '' ? [] : [trim($raw)];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $raw)),
            static fn (string $part): bool => $part !== '',
        ));
    }

    private function highestConfiguredScore(): int
    {
        return (int) $this->funnel->results()->max('max_score');
    }

    private function reload(): void
    {
        $this->conditions = $this->funnel->conditions()
            ->orderByDesc('priority')
            ->get()
            ->mapWithKeys(fn (FunnelCondition $condition): array => [$condition->id => [
                'source_question_id' => $condition->source_question_id,
                'operator' => $condition->operator->value,
                'value' => implode(', ', $condition->value ?? []),
                'target_step_id' => $condition->target_step_id,
                'evaluate_at_step_position' => $condition->evaluationStepPosition(),
                'priority' => $condition->priority,
            ]])
            ->all();

        $this->results = $this->funnel->results()
            ->orderBy('min_score')
            ->get()
            ->mapWithKeys(fn (FunnelResult $result): array => [$result->id => [
                'min_score' => $result->min_score,
                'max_score' => $result->max_score,
                'title' => $result->title,
                'body' => $result->body ?? '',
                'cta_label' => $result->cta_label ?? '',
                'cta_url' => $result->cta_url ?? '',
                'show_contact_form' => (bool) $result->show_contact_form,
            ]])
            ->all();
    }
}

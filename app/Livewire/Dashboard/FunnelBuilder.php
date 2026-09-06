<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\FunnelFieldKey;
use App\Constants\QuestionType;
use App\Exceptions\FunnelConcurrentlyModified;
use App\Funnel\QuestionTypes\QuestionTypeRegistry;
use App\Models\Funnel;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use App\Services\FunnelBuilderService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Builder fuer Schritte und Fragen eines Funnels (FB-015).
 *
 * Drei Spalten: links die Schritte, in der Mitte die Fragen des gewaehlten
 * Schritts, rechts die Eigenschaften der markierten Frage. Beide Listen sind
 * per Ziehen sortierbar.
 *
 * Die Eigenschaften rechts speichern automatisch. Damit sich zwei gleichzeitige
 * Bearbeiter nicht gegenseitig ueberschreiben, fuehrt die Oberflaeche den Stand
 * mit, auf dem sie aufgesetzt hat; der FunnelBuilderService bricht ab, wenn der
 * Datensatz zwischenzeitlich woanders geaendert wurde.
 *
 * Fachlogik liegt im Service, die Fragetypen kommen aus der QuestionTypeRegistry
 * (FB-011) - hier gibt es bewusst keine zweite Typliste.
 */
class FunnelBuilder extends Component
{
    #[Locked]
    public Funnel $funnel;

    #[Locked]
    public ?int $selectedStepId = null;

    #[Locked]
    public ?int $selectedQuestionId = null;

    /**
     * Eigenschaften der markierten Frage, an die Oberflaeche gebunden.
     *
     * @var array<string, mixed>
     */
    public array $questionForm = [];

    /**
     * Stand der markierten Frage, auf dem der Bearbeiter aufgesetzt hat.
     */
    #[Locked]
    public ?string $questionSeenAt = null;

    /**
     * Titel des markierten Schritts, an die Oberflaeche gebunden.
     */
    public string $stepTitle = '';

    #[Locked]
    public ?string $stepSeenAt = null;

    /**
     * Meldung, wenn ein Speichervorgang wegen einer fremden Aenderung abbrach.
     */
    public ?string $conflictMessage = null;

    public function mount(Funnel $funnel): void
    {
        $this->funnel = $funnel;

        $this->selectStep($funnel->steps()->value('id'));
    }

    public function selectStep(?int $stepId): void
    {
        $step = $stepId === null ? null : $this->steps()->find($stepId);

        $this->selectedStepId = $step?->id;
        $this->stepTitle = $step->title ?? '';
        $this->stepSeenAt = $step === null ? null : $this->builder()->stampOf($step);
        $this->conflictMessage = null;

        $this->selectQuestion($step?->questions()->value('id'));
    }

    public function selectQuestion(?int $questionId): void
    {
        $question = $questionId === null ? null : $this->questions()->find($questionId);

        $this->selectedQuestionId = $question?->id;
        $this->questionSeenAt = $question === null ? null : $this->builder()->stampOf($question);
        $this->conflictMessage = null;

        $this->questionForm = $question === null ? [] : [
            'type' => $question->type->value,
            'label' => $question->label,
            'field_key' => $question->field_key,
            'help_text' => $question->help_text ?? '',
            'required' => $question->required,
        ];
    }

    public function addStep(): void
    {
        $step = $this->builder()->addStep($this->funnel, __('funnel.builder.new_step'));

        $this->selectStep($step->id);
    }

    public function deleteStep(int $stepId): void
    {
        $step = $this->steps()->findOrFail($stepId);

        $this->builder()->deleteStep($step);

        $this->selectStep($this->funnel->steps()->value('id'));
    }

    /**
     * @param  list<int|string>  $orderedIds
     */
    public function reorderSteps(array $orderedIds): void
    {
        $this->builder()->reorderSteps($this->funnel, $this->toIds($orderedIds));
    }

    public function saveStep(): void
    {
        $step = $this->selectedStep();

        if ($step === null) {
            return;
        }

        $this->validate([
            'stepTitle' => ['required', 'string', 'max:255'],
        ]);

        $this->guarded(function () use ($step): void {
            $saved = $this->builder()->updateStep($step, ['title' => $this->stepTitle], $this->stepSeenAt);

            $this->stepSeenAt = $this->builder()->stampOf($saved);
        });
    }

    public function addQuestion(string $type): void
    {
        $step = $this->selectedStep();

        if ($step === null) {
            return;
        }

        $questionType = QuestionType::from($type);

        $question = $this->builder()->addQuestion(
            $step,
            $questionType,
            $questionType->label(),
            $this->freeFieldKey($questionType),
        );

        $this->selectQuestion($question->id);
    }

    public function deleteQuestion(int $questionId): void
    {
        $question = $this->questions()->findOrFail($questionId);

        $this->builder()->deleteQuestion($question);

        $this->selectQuestion($this->selectedStep()?->questions()->value('id'));
    }

    /**
     * @param  list<int|string>  $orderedIds
     */
    public function reorderQuestions(array $orderedIds): void
    {
        $step = $this->selectedStep();

        if ($step === null) {
            return;
        }

        $this->builder()->reorderQuestions($step, $this->toIds($orderedIds));
    }

    /**
     * Autosave der Eigenschaften rechts. Die Oberflaeche ruft die Methode
     * entprellt auf, damit nicht jeder Tastendruck eine Anfrage ausloest.
     */
    public function saveQuestion(): void
    {
        $question = $this->selectedQuestion();

        if ($question === null) {
            return;
        }

        $this->validate([
            'questionForm.label' => ['required', 'string', 'max:255'],
            'questionForm.field_key' => ['required', 'string', 'max:255'],
            'questionForm.help_text' => ['nullable', 'string', 'max:1000'],
            'questionForm.type' => ['required', 'string', 'in:'.implode(',', QuestionType::values())],
        ]);

        $this->guarded(function () use ($question): void {
            $saved = $this->builder()->updateQuestion($question, [
                'type' => QuestionType::from($this->questionForm['type']),
                'label' => $this->questionForm['label'],
                'field_key' => $this->questionForm['field_key'],
                'help_text' => $this->questionForm['help_text'] ?: null,
                'required' => (bool) ($this->questionForm['required'] ?? false),
            ], $this->questionSeenAt);

            $this->questionSeenAt = $this->builder()->stampOf($saved);
            // Der Feldschluessel wird beim Speichern vereinheitlicht (FB-010),
            // deshalb den gespeicherten Wert zurueckspiegeln.
            $this->questionForm['field_key'] = $saved->field_key;
        });
    }

    public function addOption(): void
    {
        $question = $this->selectedQuestion();

        if ($question === null) {
            return;
        }

        $position = $question->options()->count() + 1;

        $this->builder()->addOption(
            $question,
            __('funnel.builder.new_option', ['position' => $position]),
            'option_'.$position,
        );
    }

    public function deleteOption(int $optionId): void
    {
        $question = $this->selectedQuestion();

        if ($question === null) {
            return;
        }

        $option = $question->options()->findOrFail($optionId);

        $this->builder()->deleteOption($option);
    }

    /**
     * Verwirft die eigenen Aenderungen und laedt den fremden Stand nach.
     */
    public function reloadQuestion(): void
    {
        $this->selectQuestion($this->selectedQuestionId);
    }

    public function render(QuestionTypeRegistry $registry): View
    {
        $step = $this->selectedStep();
        $question = $this->selectedQuestion();

        return view('livewire.dashboard.funnel-builder', [
            'steps' => $this->steps()->get(),
            'questions' => $step === null ? new Collection : $step->questions()->get(),
            'question' => $question,
            'options' => $question === null ? new Collection : $question->options()->get(),
            'questionTypes' => QuestionType::labels(),
            // Blade-Komponentenname des markierten Typs, damit die Vorschau
            // denselben Weg nimmt wie die oeffentliche Auslieferung (FB-011).
            'previewComponent' => $question === null ? null : $registry->forQuestion($question)->render(),
        ]);
    }

    /**
     * Fuehrt einen Speichervorgang aus und faengt den Konfliktfall ab.
     */
    private function guarded(callable $save): void
    {
        try {
            $save();
            $this->conflictMessage = null;
        } catch (FunnelConcurrentlyModified $exception) {
            $this->conflictMessage = $exception->getMessage();
        }
    }

    /**
     * Ein je Funnel eindeutiger Feldschluessel fuer eine neue Frage. Der
     * Unique-Index laeuft ueber (funnel_id, field_key), ein Duplikat waere
     * sonst ein Fehler beim Anlegen.
     */
    private function freeFieldKey(QuestionType $type): string
    {
        $base = FunnelFieldKey::normalize(Str::slug($type->value, '_'));
        $taken = $this->funnel->questions()->pluck('field_key')->all();

        $candidate = $base;
        $suffix = 1;

        while (in_array($candidate, $taken, true)) {
            $candidate = $base.'_'.(++$suffix);
        }

        return $candidate;
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function toIds(array $ids): array
    {
        return array_values(array_map(intval(...), $ids));
    }

    private function selectedStep(): ?FunnelStep
    {
        return $this->selectedStepId === null ? null : $this->steps()->find($this->selectedStepId);
    }

    private function selectedQuestion(): ?FunnelQuestion
    {
        return $this->selectedQuestionId === null ? null : $this->questions()->find($this->selectedQuestionId);
    }

    /**
     * @return HasMany<FunnelStep, Funnel>
     */
    private function steps()
    {
        return $this->funnel->steps();
    }

    /**
     * @return HasMany<FunnelQuestion, Funnel>
     */
    private function questions()
    {
        return $this->funnel->questions();
    }

    private function builder(): FunnelBuilderService
    {
        return app(FunnelBuilderService::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelThemeFont;
use App\Funnel\Theme\ContrastRatio;
use App\Models\Funnel;
use App\Models\FunnelTheme;
use App\Services\FunnelThemeService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Theme-Editor eines Funnels (FB-017).
 *
 * Links die Eingabefelder, rechts eine Live-Vorschau im iFrame. Die Vorschau
 * bekommt die Werte ueber die Adresse mitgegeben, nicht aus der Datenbank -
 * so zeigt sie den Stand im Formular, auch bevor gespeichert wurde.
 *
 * Reines Livewire, kein Filament-Formular (Abschnitt 5 des Agenten-Leitfadens).
 */
class ThemeEditor extends Component
{
    #[Locked]
    public Funnel $funnel;

    #[Locked]
    public int $themeId;

    public string $primary_color = '';

    public string $secondary_color = '';

    public string $background_color = '';

    public string $text_color = '';

    public string $font = '';

    public string $progress_style = '';

    public int $border_radius = 8;

    public string $button_next_label = '';

    public string $button_back_label = '';

    public string $button_submit_label = '';

    public string $logo_path = '';

    public bool $saved = false;

    public function mount(Funnel $funnel, FunnelThemeService $themes): void
    {
        $this->funnel = $funnel;

        $this->fillFrom($themes->forFunnel($funnel));
    }

    public function save(FunnelThemeService $themes): void
    {
        $data = $this->validate();

        $theme = $themes->update($this->theme(), [
            ...$data,
            'button_next_label' => $this->button_next_label ?: null,
            'button_back_label' => $this->button_back_label ?: null,
            'button_submit_label' => $this->button_submit_label ?: null,
            'logo_path' => $this->logo_path ?: null,
        ]);

        $this->fillFrom($theme);
        $this->saved = true;
    }

    public function render(): View
    {
        return view('livewire.dashboard.theme-editor', [
            'fonts' => FunnelThemeFont::labels(),
            'progressStyles' => FunnelProgressStyle::labels(),
            'contrastWarnings' => $this->contrastWarnings(),
            'previewUrl' => route('funnel.theme-preview', [
                'funnel' => $this->funnel,
                ...$this->previewParameters(),
            ]),
        ]);
    }

    /**
     * Kontrastpruefung der gewaehlten Farben (FB-027).
     *
     * Bewusst eine Warnung und kein Verbot: Es ist der Funnel des Betreibers,
     * und ein knapp verfehltes Verhaeltnis kann bei grosser Schrift trotzdem in
     * Ordnung sein. Er soll es nur wissen - auf seinem Bildschirm sieht
     * Hellgrau auf Weiss oft noch lesbar aus.
     *
     * @return list<array{label: string, ratio: float, required: float}>
     */
    private function contrastWarnings(): array
    {
        $pairs = [
            // Fliesstext auf dem Hintergrund - der wichtigste Fall.
            [__('builder.theme.contrast_text'), $this->text_color, $this->background_color, ContrastRatio::AA_NORMAL_TEXT],
            // Die Schaltflaechen tragen weisse Schrift auf der Primaerfarbe.
            [__('builder.theme.contrast_button'), '#ffffff', $this->primary_color, ContrastRatio::AA_NORMAL_TEXT],
            // Rahmen und Fortschrittsanzeige sind Bedienelemente, dort genuegt 3:1.
            [__('builder.theme.contrast_controls'), $this->secondary_color, $this->background_color, ContrastRatio::AA_NON_TEXT],
        ];

        $warnings = [];

        foreach ($pairs as [$label, $foreground, $background, $required]) {
            $ratio = ContrastRatio::between($foreground, $background);

            if ($ratio < $required) {
                $warnings[] = ['label' => $label, 'ratio' => $ratio, 'required' => $required];
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        // Die Farben landen unveraendert in einem style-Attribut. Ein freier
        // String waere damit ein Einfallstor, deshalb das strikte Hex-Muster.
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'background_color' => $hex,
            'text_color' => $hex,
            'font' => ['required', Rule::in(FunnelThemeFont::values())],
            'progress_style' => ['required', Rule::in(FunnelProgressStyle::values())],
            'border_radius' => ['required', 'integer', 'min:0', 'max:64'],
            'button_next_label' => ['nullable', 'string', 'max:60'],
            'button_back_label' => ['nullable', 'string', 'max:60'],
            'button_submit_label' => ['nullable', 'string', 'max:60'],
            'logo_path' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'primary_color' => __('builder.theme.primary_color'),
            'secondary_color' => __('builder.theme.secondary_color'),
            'background_color' => __('builder.theme.background_color'),
            'text_color' => __('builder.theme.text_color'),
            'font' => __('builder.theme.font_label'),
            'progress_style' => __('builder.theme.progress_label'),
            'border_radius' => __('builder.theme.border_radius'),
        ];
    }

    /**
     * Werte fuer die Vorschau. Sie kommen aus dem Formular, nicht aus der
     * Datenbank - sonst zeigte die Vorschau erst nach dem Speichern etwas an.
     *
     * @return array<string, string|int>
     */
    private function previewParameters(): array
    {
        return [
            'primary' => ltrim($this->primary_color, '#'),
            'secondary' => ltrim($this->secondary_color, '#'),
            'background' => ltrim($this->background_color, '#'),
            'text' => ltrim($this->text_color, '#'),
            'font' => $this->font,
            'progress' => $this->progress_style,
            'radius' => $this->border_radius,
            'next' => $this->button_next_label,
            'back' => $this->button_back_label,
            'submit' => $this->button_submit_label,
        ];
    }

    private function theme(): FunnelTheme
    {
        return FunnelTheme::query()->findOrFail($this->themeId);
    }

    private function fillFrom(FunnelTheme $theme): void
    {
        $this->themeId = $theme->id;
        $this->primary_color = $theme->primary_color;
        $this->secondary_color = $theme->secondary_color;
        $this->background_color = $theme->background_color;
        $this->text_color = $theme->text_color;
        $this->font = $theme->font->value;
        $this->progress_style = $theme->progress_style->value;
        $this->border_radius = $theme->border_radius;
        $this->button_next_label = $theme->button_next_label ?? '';
        $this->button_back_label = $theme->button_back_label ?? '';
        $this->button_submit_label = $theme->button_submit_label ?? '';
        $this->logo_path = $theme->logo_path ?? '';
    }
}

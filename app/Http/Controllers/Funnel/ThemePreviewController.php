<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnel;

use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelThemeFont;
use App\Http\Controllers\Controller;
use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live-Vorschau des Theme-Editors (FB-017).
 *
 * Zeigt einen beispielhaften Funnel-Schritt mit den Werten aus der Adresse -
 * also dem Stand im Formular, noch bevor gespeichert wurde. Bewusst eine eigene,
 * in sich geschlossene Darstellung und nicht der FunnelRunner: der gehoert zu
 * FB-020 und braucht einen veroeffentlichten Snapshot, den ein Entwurf mit
 * frisch geaendertem Theme nicht hat.
 */
class ThemePreviewController extends Controller
{
    public function show(Request $request, Funnel $funnel): View
    {
        $font = FunnelThemeFont::tryFrom((string) $request->query('font')) ?? FunnelThemeFont::SYSTEM;

        return view('funnel.theme-preview', [
            'funnel' => $funnel,
            'primary' => $this->color($request->query('primary'), '#2563eb'),
            'secondary' => $this->color($request->query('secondary'), '#64748b'),
            'background' => $this->color($request->query('background'), '#ffffff'),
            'text' => $this->color($request->query('text'), '#0f172a'),
            'fontFamily' => $font->fontFamily(),
            'progressStyle' => FunnelProgressStyle::tryFrom((string) $request->query('progress'))
                ?? FunnelProgressStyle::BAR,
            'radius' => max(0, min(64, (int) $request->query('radius', '8'))),
            'nextLabel' => $this->label($request->query('next'), __('builder.theme.default_next')),
            'backLabel' => $this->label($request->query('back'), __('builder.theme.default_back')),
            'submitLabel' => $this->label($request->query('submit'), __('builder.theme.default_submit')),
        ]);
    }

    /**
     * Nimmt nur ein sechsstelliges Hex entgegen. Die Werte stehen in der
     * Vorschau in einem style-Attribut; alles andere waere ein Einfallstor.
     */
    private function color(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? ltrim($value, '#') : '';

        return preg_match('/^[0-9a-fA-F]{6}$/', $value) === 1 ? '#'.$value : $fallback;
    }

    private function label(mixed $value, string $fallback): string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? $fallback : mb_substr($value, 0, 60);
    }
}

<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Services\Onboarding\OnboardingFunnelQuery;
use Filament\Widgets\Widget;

/**
 * LP-ONBOARD-015: Wer gerade haengt.
 *
 * Der Trichter sagt, welche Stufe klemmt; diese Tabelle sagt, bei wem. Gezeigt
 * wird jeder Kaeufer-Workspace, der seit mehr als 48 Stunden auf derselben
 * Stufe steht -- bewusst ohne Zeitraum-Filter, denn genau die alten Faelle
 * sind gemeint. Dazu die Kontakt-E-Mail und der Sprung zum Nutzer im Admin,
 * damit man ihn direkt anschreiben kann.
 *
 * Eine eigene Tabelle und kein TableWidget: Auf welcher Stufe ein Workspace
 * steht, steht in keiner Spalte -- es ergibt sich aus der Kette der Haken und
 * wird in OnboardingFunnelQuery gerechnet. Ein Eloquent-Query-Builder darauf
 * waere eine zweite Rechnung derselben Sache.
 */
class StuckWorkspacesTable extends Widget
{
    protected string $view = 'filament.admin.widgets.stuck-workspaces-table';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return app(OnboardingFunnelQuery::class)->stuck();
    }

    public function thresholdHours(): int
    {
        return OnboardingFunnelQuery::STUCK_HOURS;
    }

    public function stageLabel(string $stage): string
    {
        return __('reports.onboarding.stages.'.$stage);
    }

    /**
     * Die Adresse des Nutzers im Admin -- null, wenn der Workspace keinen
     * Anleger mehr hat (geloeschtes Konto).
     */
    public function userUrl(?int $userId): ?string
    {
        return $userId === null ? null : UserResource::getUrl('edit', ['record' => $userId]);
    }

    /**
     * Eine Wartezeit in Stunden menschenlesbar.
     */
    public function formatHours(int $hours): string
    {
        if ($hours < 48) {
            return trans_choice('reports.onboarding.hours', $hours, [
                'count' => (string) $hours,
            ]);
        }

        $days = $hours / 24;

        return trans_choice('reports.onboarding.days', (int) round($days), [
            'count' => number_format($days, 1, ',', '.'),
        ]);
    }
}

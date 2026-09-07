<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Models\User;
use App\Services\LeadDataRequestService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * FB-038: Einstieg fuer Auskunft und Loeschersuchen im Admin-Panel.
 *
 * Bewusst schlank: zwei Aktionen, je ein Feld. Die Arbeit macht
 * LeadDataRequestService; diese Seite nimmt nur die E-Mail-Adresse entgegen
 * und gibt das Ergebnis aus.
 */
class DataProtectionRequests extends Page
{
    protected string $view = 'filament.admin.pages.data-protection-requests';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 12;

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && (bool) auth()->user()->is_admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('funnel.gdpr.nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.leads');
    }

    public function getTitle(): string
    {
        return __('funnel.gdpr.heading');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
            $this->eraseAction(),
        ];
    }

    /**
     * Auskunft: laedt alles zu dieser Adresse als JSON herunter.
     */
    private function exportAction(): Action
    {
        return Action::make('export')
            ->label(__('funnel.gdpr.export.action'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading(__('funnel.gdpr.export.heading'))
            ->modalDescription(__('funnel.gdpr.export.description'))
            ->modalSubmitActionLabel(__('funnel.gdpr.export.submit'))
            ->schema([
                TextInput::make('email')
                    ->label(__('funnel.gdpr.email'))
                    ->helperText(__('funnel.gdpr.email_helper'))
                    ->email()
                    ->required(),
            ])
            ->action(function (array $data): ?StreamedResponse {
                $email = (string) $data['email'];
                $export = app(LeadDataRequestService::class)->exportForEmail($email, $this->actor());

                if ($export['lead_count'] === 0) {
                    Notification::make()
                        ->warning()
                        ->title(__('funnel.gdpr.export.empty'))
                        ->send();

                    return null;
                }

                Notification::make()
                    ->success()
                    ->title(__('funnel.gdpr.export.done', ['count' => $export['lead_count']]))
                    ->send();

                return response()->streamDownload(
                    function () use ($export): void {
                        echo (string) json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    },
                    'auskunft-'.now()->format('Y-m-d-His').'.json',
                    ['Content-Type' => 'application/json'],
                );
            });
    }

    /**
     * Loeschersuchen: anonymisiert, loescht nicht.
     */
    private function eraseAction(): Action
    {
        return Action::make('erase')
            ->label(__('funnel.gdpr.erase.action'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('funnel.gdpr.erase.heading'))
            ->modalDescription(__('funnel.gdpr.erase.description'))
            ->modalSubmitActionLabel(__('funnel.gdpr.erase.submit'))
            ->schema([
                TextInput::make('email')
                    ->label(__('funnel.gdpr.email'))
                    ->helperText(__('funnel.gdpr.email_helper'))
                    ->email()
                    ->required(),
            ])
            ->action(function (array $data): void {
                $erased = app(LeadDataRequestService::class)
                    ->eraseForEmail((string) $data['email'], $this->actor());

                if ($erased === 0) {
                    Notification::make()
                        ->warning()
                        ->title(__('funnel.gdpr.erase.empty'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('funnel.gdpr.erase.done', ['count' => $erased]))
                    ->send();
            });
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}

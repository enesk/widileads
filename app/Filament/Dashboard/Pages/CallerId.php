<?php

declare(strict_types=1);

namespace App\Filament\Dashboard\Pages;

use App\Constants\CallerIdStatus;
use App\Models\CallerId as CallerIdModel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CallerIdService;
use App\Services\Twilio\CallerIdValidationFailed;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Die eigene Rufnummer des Kaeufer-Mitarbeiters bestaetigen (FB-080).
 *
 * Beim Endkunden erscheint die Nummer dessen, der anruft. Twilio laesst das nur
 * fuer bestaetigte Nummern zu, und bestaetigt wird per Anruf: Twilio waehlt die
 * Nummer und sagt einen Code an, der hier auf der Seite steht.
 *
 * Die Seite zeigt nur an und stoesst an. Der Stand einer Nummer entsteht
 * ausschliesslich in CallerIdService -- eine Oberflaeche, die `verified` setzen
 * koennte, waere die Luecke, die die Bestaetigung schliessen soll.
 */
class CallerId extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.dashboard.pages.caller-id';

    protected static string|null|BackedEnum $navigationIcon = Heroicon::OutlinedPhone;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function getHeading(): string|Htmlable
    {
        return __('call.caller_id.heading');
    }

    public function getTitle(): string|Htmlable
    {
        return __('call.caller_id.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('call.caller_id.description');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('builder.groups.marketplace');
    }

    public static function getNavigationLabel(): string
    {
        return __('call.caller_id.nav_label');
    }

    public static function canAccess(): bool
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return Gate::allows('marketplace.access', $tenant);
    }

    public function mount(): void
    {
        $this->getSchema('form')?->fill([
            'phone_number' => $this->callerId()?->phone_number,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('call.caller_id.heading'))
                    ->schema([
                        TextInput::make('phone_number')
                            ->label(__('call.caller_id.phone_number'))
                            ->helperText(__('call.caller_id.phone_number_helper'))
                            ->tel()
                            ->required()
                            ->maxLength(32),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Der Stand der eigenen Nummer, verfallene Bestaetigungen bereits als
     * solche gekennzeichnet.
     */
    public function callerId(): ?CallerIdModel
    {
        $user = $this->currentUser();
        $service = app(CallerIdService::class);

        $callerId = $service->forUser($user);

        return $callerId instanceof CallerIdModel ? $service->expireIfOverdue($callerId) : null;
    }

    public function statusLabel(): ?string
    {
        return $this->callerId()?->status->label();
    }

    /**
     * Der angesagte Code -- nur solange die Bestaetigung laeuft.
     */
    public function validationCode(): ?string
    {
        $callerId = $this->callerId();

        return $callerId?->status === CallerIdStatus::PENDING ? $callerId->validation_code : null;
    }

    public function requestValidation(): void
    {
        /** @var array<string, mixed> $data */
        $data = $this->getSchema('form')?->getState() ?? [];

        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Tenant, 403);

        try {
            app(CallerIdService::class)->requestValidation(
                $tenant,
                $this->currentUser(),
                (string) ($data['phone_number'] ?? ''),
                route('twilio.caller-id.validation-status'),
            );
        } catch (InvalidArgumentException) {
            Notification::make()
                ->danger()
                ->title(__('call.caller_id.invalid_number'))
                ->send();

            return;
        } catch (CallerIdValidationFailed $exception) {
            // Die Twilio-Meldung gehoert ins Log, nicht auf den Bildschirm des
            // Mitarbeiters -- sie nennt Kontodetails.
            logger()->error('Twilio lehnte die Rufnummern-Bestaetigung ab.', [
                'message' => $exception->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title(__('call.caller_id.provider_failed'))
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title(__('call.caller_id.requested'))
            ->body(__('call.caller_id.requested_body'))
            ->send();
    }

    private function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}

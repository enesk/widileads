<?php

namespace App\Livewire\Filament;

use App\Services\ConfigService;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\ValidationResult;
use Livewire\Component;

class TwilioSettings extends Component implements HasForms
{
    private ConfigService $configService;

    private PhoneNumberUtil $phoneNumbers;

    use InteractsWithForms;

    public ?array $data = [];

    public function boot(ConfigService $configService, PhoneNumberUtil $phoneNumbers): void
    {
        $this->configService = $configService;
        $this->phoneNumbers = $phoneNumbers;
    }

    public function render()
    {
        return view('livewire.filament.twilio-settings');
    }

    public function mount(): void
    {
        $this->form->fill([
            'sid' => $this->configService->get('services.twilio.sid'),
            'token' => $this->configService->get('services.twilio.token'),
            'from' => $this->configService->get('services.twilio.from'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('sid')
                            ->label(__('SID'))
                            ->helperText(__('The Account SID from your Twilio account.'))
                            ->required(),
                        TextInput::make('token')
                            ->label(__('Token'))
                            ->helperText(__('The Auth Token from your Twilio account.'))
                            ->required(),
                        TextInput::make('from')
                            ->label(__('From'))
                            ->helperText(__('The phone number or alphanumeric sender ID to send messages from.'))
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, callable $set) => $set('from', $this->normalizeSender((string) $state) ?? $state))
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (is_string($value) && $this->normalizeSender($value) === null) {
                                    $fail(__('Enter the phone number in a dialable format, for example +491511234567.'));
                                }
                            })
                            ->required(),
                    ])->columnSpan([
                        'sm' => 6,
                        'xl' => 8,
                        '2xl' => 8,
                    ]),
                Section::make()->schema([
                    ViewField::make('how-to')
                        ->label(__('Paddle Settings'))
                        ->view('filament.admin.resources.verification-provider-resource.pages.partials.twilio-how-to'),
                ])->columnSpan([
                    'sm' => 6,
                    'xl' => 4,
                    '2xl' => 4,
                ]),
            ])->columns(12)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->configService->set('services.twilio.sid', $data['sid']);
        $this->configService->set('services.twilio.token', $data['token']);
        $this->configService->set('services.twilio.from', $this->normalizeSender((string) $data['from']) ?? $data['from']);

        $this->form->fill([
            'sid' => $data['sid'],
            'token' => $data['token'],
            'from' => $this->configService->get('services.twilio.from'),
        ]);

        Notification::make()
            ->title(__('Settings Saved'))
            ->success()
            ->send();
    }

    /**
     * Absender in der Form, in der Twilio ihn annimmt: eine Rufnummer immer in
     * E.164 ("+491511234567"), damit ein Click-to-Call nicht erst beim Waehlen
     * scheitert (FB-085). Alphanumerische Sender-IDs (enthalten Buchstaben,
     * nur fuer SMS zulaessig) bleiben unveraendert. Null heisst: die Eingabe
     * laesst sich weder als Rufnummer noch als Sender-ID lesen.
     *
     * Geprueft wird auf Plausibilitaet (Laenge und Landesvorwahl), nicht auf
     * isValidNumber() wie bei Lead-Nummern: welche Nummer als Absender taugt,
     * entscheidet ohnehin das Twilio-Konto, und eine dort gekaufte Nummer soll
     * hier nicht an einem strengeren Nummernplan scheitern.
     */
    private function normalizeSender(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/[a-zA-Z]/', $value) === 1) {
            return $value;
        }

        try {
            $parsed = $this->phoneNumbers->parse($value, (string) config('funnel.call.caller_id.region'));
        } catch (NumberParseException) {
            return null;
        }

        if ($this->phoneNumbers->isPossibleNumberWithReason($parsed) !== ValidationResult::IS_POSSIBLE) {
            return null;
        }

        return $this->phoneNumbers->format($parsed, PhoneNumberFormat::E164);
    }
}

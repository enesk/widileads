<?php

declare(strict_types=1);

namespace App\Dto;

use App\Constants\FunnelFieldKey;
use App\Models\Lead;
use App\Models\LeadAnswer;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Die Kontaktdaten eines Leads -- im Klartext oder verdeckt (FB-032).
 *
 * Vor dem Kauf sieht ein Kaeufer nur, dass es einen Kontakt gibt, nicht wen.
 * Nach dem Kauf sieht er alles. Diese Klasse kennt beide Fassungen und stellt
 * sie her; WER welche bekommt, entscheidet allein
 * App\Services\LeadContactResolver.
 *
 * Maskiert wird serverseitig und nur hier (Architekturleitsatz 5). Eine zweite
 * Maskierung in einem Blade-Template oder einer API-Resource gilt
 * ausdruecklich als nicht erfuellt: Sie waere die Stelle, an der irgendwann
 * jemand das Feld vergisst.
 */
final class LeadContact
{
    private function __construct(
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ?string $postalCode,
        public readonly bool $masked,
        public readonly bool $phoneMasked,
    ) {}

    /**
     * Loest die reservierten Feldschluessel eines Leads auf.
     *
     * Schreibweisen muessen hier nicht mehr bekannt sein: FB-010 normalisiert
     * den Feldschluessel beim Speichern und loest gebraeuchliche Varianten auf
     * den reservierten Schluessel auf.
     *
     * Telefon und E-Mail stehen zusaetzlich als eigene Spalten am Lead (FB-031)
     * -- normalisiert und damit verlaesslicher als die Rohantwort. Sie haben
     * deshalb Vorrang.
     */
    public static function fromLead(Lead $lead): self
    {
        $answers = $lead->answers
            ->mapWithKeys(static fn (LeadAnswer $answer): array => [$answer->field_key => $answer->value])
            ->all();

        $answer = static function (FunnelFieldKey $key) use ($answers): ?string {
            $value = $answers[$key->value] ?? null;

            if (! is_scalar($value)) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        return new self(
            firstName: $answer(FunnelFieldKey::VORNAME),
            lastName: $answer(FunnelFieldKey::NACHNAME),
            name: $answer(FunnelFieldKey::NAME),
            email: $lead->email_normalized ?? $answer(FunnelFieldKey::EMAIL),
            phone: $lead->phone_e164 ?? $answer(FunnelFieldKey::TELEFON),
            postalCode: $lead->postal_code ?? $answer(FunnelFieldKey::PLZ),
            masked: false,
            phoneMasked: false,
        );
    }

    /**
     * Die verdeckte Fassung.
     *
     * Der Name bleibt im Klartext: Er allein macht niemanden erreichbar, ist
     * aber die einzige Angabe, an der ein Kaeufer einen Lead im Marktplatz
     * wiedererkennt. E-Mail, Telefon und Postleitzahl werden gekuerzt --
     * erkennbar genug, um einzuschaetzen, ob der Lead passt, zu wenig, um
     * daran vorbei Kontakt aufzunehmen.
     */
    public function masked(): self
    {
        if ($this->masked) {
            return $this;
        }

        return new self(
            firstName: $this->firstName,
            lastName: $this->lastName,
            name: $this->name,
            email: self::maskEmail($this->email),
            phone: self::maskPhone($this->phone),
            postalCode: self::maskPostalCode($this->postalCode),
            masked: true,
            phoneMasked: true,
        );
    }

    /**
     * Dieselben Daten, aber mit einer eigens gesetzten Rufnummer (FB-085).
     *
     * Gedacht fuer den einen Fall, in dem Rufnummer und uebrige Kontaktdaten
     * auseinanderfallen: Ein Kaeufer, der den Lead gekauft hat, sieht Name,
     * E-Mail und Postleitzahl im Klartext, die Rufnummer aber erst, wenn die
     * Erreichbarkeitspruefung mit `billable` geendet hat. Bis dahin steht hier
     * die verkuerzte Fassung aus Lead::maskedPhone().
     *
     * Wer die Methode aufruft, entscheidet nichts selbst -- das tut allein
     * App\Services\LeadContactResolver.
     */
    public function withPhone(?string $phone, bool $phoneMasked): self
    {
        return new self(
            firstName: $this->firstName,
            lastName: $this->lastName,
            name: $this->name,
            email: $this->email,
            phone: $phone,
            postalCode: $this->postalCode,
            masked: $this->masked,
            phoneMasked: $phoneMasked,
        );
    }

    /**
     * Vollstaendiger Name aus Vor- und Nachname, sonst aus dem Sammelfeld.
     */
    public function fullName(): ?string
    {
        $parts = array_values(array_filter([$this->firstName, $this->lastName]));

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return $this->name;
    }

    /**
     * @return array{name: string|null, first_name: string|null, last_name: string|null, email: string|null, phone: string|null, postal_code: string|null, masked: bool, phone_masked: bool}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->fullName(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'postal_code' => $this->postalCode,
            'masked' => $this->masked,
            'phone_masked' => $this->phoneMasked,
        ];
    }

    /**
     * `mara.lindqvist@example.com` wird zu `m…@example.com`.
     *
     * Die Domain bleibt stehen: Sie sagt dem Kaeufer, ob es eine Privat- oder
     * Firmenadresse ist, ohne jemanden erreichbar zu machen.
     */
    private static function maskEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $atPosition = mb_strrpos($email, '@');

        if ($atPosition === false || $atPosition === 0) {
            return '…';
        }

        return mb_substr($email, 0, 1).'…'.mb_substr($email, $atPosition);
    }

    /**
     * `+493012345678` wird zu `+49 30 …`.
     *
     * Landesvorwahl und Ortsnetz bleiben lesbar -- daran erkennt ein Kaeufer die
     * Region. Der Rest der Nummer verschwindet.
     */
    private static function maskPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $international = self::internationalFormat($phone);

        if ($international === null) {
            // Nicht lesbar: Nach Zeichenposition zu schneiden waere geraten --
            // bei einer national notierten Nummer stuenden dort schon Ziffern
            // des Anschlusses. Dann lieber gar nichts zeigen.
            return '…';
        }

        $groups = explode(' ', $international);

        if (count($groups) < 3) {
            // libphonenumber hat die Nummer nicht in Landesvorwahl,
            // Netzkennzahl und Teilnehmeranschluss zerlegt. Die zweite Gruppe
            // waere hier bereits die ganze Nummer -- also nur die
            // Landesvorwahl stehen lassen.
            return $groups[0].' …';
        }

        return implode(' ', array_slice($groups, 0, 2)).' …';
    }

    /**
     * Die Fassung fuer den Kaeufer vor der Abrechnung (FB-085).
     *
     * `+491711234567` wird zu `+49 171 ***** 67`: Landesvorwahl und
     * Netzkennzahl bleiben stehen, jede weitere Ziffer wird zu einem Stern,
     * nur die letzten beiden bleiben lesbar. Damit kann ein Kaeufer einen
     * Lead wiedererkennen und eine Nummer aus seinem Telefonprotokoll
     * zuordnen -- waehlen kann er sie nicht.
     *
     * Bewusst grosszuegiger als masked(): Diese Fassung sieht nur, wer den
     * Lead bereits gekauft hat.
     */
    public static function maskPhoneToLastDigits(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $international = self::internationalFormat($phone);

        if ($international === null) {
            // Nicht lesbar: kein Praefix, das man gefahrlos stehen lassen
            // koennte -- dann bleiben nur die letzten beiden Ziffern.
            return self::maskAllButLastDigits($phone);
        }

        $groups = explode(' ', $international);

        // Landesvorwahl und erste Gruppe bleiben stehen; alles danach ist der
        // Teilnehmeranschluss und wird verdeckt.
        $prefix = array_slice($groups, 0, 2);
        $rest = preg_replace('/\D/', '', implode('', array_slice($groups, 2))) ?? '';

        if ($rest === '') {
            // libphonenumber hat die Nummer nicht in Landesvorwahl, Netzkennzahl
            // und Teilnehmeranschluss zerlegt (FB-085). Das Praefix waere hier
            // die ganze Nummer -- also verdecken wie bei einer nicht lesbaren.
            return self::maskAllButLastDigits($international);
        }

        $visible = mb_substr($rest, -2);
        $hidden = str_repeat('*', max(0, mb_strlen($rest) - 2));

        return trim(implode(' ', $prefix).' '.$hidden.' '.$visible);
    }

    /**
     * Jede Ziffer bis auf die letzten beiden wird zu einem Stern.
     *
     * Der Rueckfallweg fuer jede Nummer, die sich nicht verlaesslich in
     * Vorwahl und Teilnehmeranschluss zerlegen laesst.
     */
    private static function maskAllButLastDigits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if ($digits === '') {
            return '…';
        }

        return trim(str_repeat('*', max(0, mb_strlen($digits) - 2)).' '.mb_substr($digits, -2));
    }

    private static function internationalFormat(string $phone): ?string
    {
        try {
            $util = app(PhoneNumberUtil::class);
            $parsed = $util->parse($phone, (string) config('funnel.question.default_phone_region'));
        } catch (NumberParseException) {
            return null;
        }

        return $util->format($parsed, PhoneNumberFormat::INTERNATIONAL);
    }

    /**
     * `76131` wird zu `76…` -- die Region bleibt erkennbar, der Ort nicht.
     */
    private static function maskPostalCode(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        return mb_substr($postalCode, 0, 2).'…';
    }
}

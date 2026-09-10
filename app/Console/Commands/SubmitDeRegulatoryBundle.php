<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CompanyProfile;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Ticket #39: Baut das deutsche Regulatory Bundle "Germany: Local - Business"
 * zusammen und reicht es bei Twilio zur Pruefung ein.
 *
 * Ohne freigegebenes Bundle lehnt Twilio den Kauf einer deutschen
 * Festnetznummer mit Fehler 21649 ab -- auch auf einem Full-Konto mit
 * Guthaben und passender Ortsvorwahl. Der Weg von Hand fuehrt durch fuenf
 * Konsolenmasken, und ein falsch gesetztes Feld kostet Tage Bearbeitungszeit,
 * weil Twilio erst am Ende der Pruefung ablehnt.
 *
 * Was das Kommando nicht kann und nicht koennen soll: sich die Registerdaten
 * ausdenken. Firmenname, Registernummer und die drei Nachweis-PDFs kommen von
 * aussen. Fehlt eines davon, wird nichts angelegt, sondern aufgelistet, was
 * fehlt -- ein halb gefuelltes Bundle im Konto ist schlimmer als keines.
 *
 * Reihenfolge, die Twilio verlangt:
 * 1. Enduser vom Typ business mit den sechs Pflichtfeldern,
 * 2. je ein Nachweisdokument fuer Proof of Registration, Proof of Business
 *    Identity und Proof of Address (dasselbe PDF darf dreimal dienen, die
 *    Attribute unterscheiden sich),
 * 3. Bundle zur Regulierung anlegen,
 * 4. Enduser, Dokumente und Adresse als ItemAssignments anhaengen,
 * 5. Status auf pending-review setzen.
 *
 * Danach laeuft die Pruefung bei Twilio ueber Tage. Der Stand ist mit
 * `leads:check-call-setup --remote` ablesbar.
 */
class SubmitDeRegulatoryBundle extends Command
{
    /** Die Regulierung "Germany: Local - Business". */
    private const REGULATION_SID = 'RNfd11dde5d4b7252abf96139766f68034';

    private const BASE_URL = 'https://numbers.twilio.com/v2/RegulatoryCompliance';

    /** Vorgabe fuer alle drei Nachweise: der Handelsregisterauszug deckt sie ab. */
    private const DEFAULT_DOCUMENT_TYPE = 'commercial_registrar_excerpt';

    protected $signature = 'leads:submit-de-bundle
        {--requirements : Nur auflisten, welche Felder und Dokumenttypen Twilio verlangt, und beenden}
        {--business-name= : Firmenname genau wie im Register (Vorgabe: Rechnungseinstellungen)}
        {--registration-number= : HRB-Nummer, USt-IdNr oder Steuernummer (Vorgabe: Rechnungseinstellungen)}
        {--website= : Firmenwebsite oder Social-Media-Adresse (Vorgabe: APP_URL)}
        {--first-name= : Vorname der bevollmaechtigten Person}
        {--last-name= : Nachname der bevollmaechtigten Person}
        {--email= : Dienstliche E-Mail der bevollmaechtigten Person (Vorgabe: mail.from.address)}
        {--comments= : Freitext fuer die Pruefer bei Twilio}
        {--address= : Address SID (Vorgabe: die einzige validierte deutsche Adresse des Kontos)}
        {--proof-registration= : PDF fuer Proof of Registration}
        {--proof-identity= : PDF fuer Proof of Business Identity}
        {--proof-address= : PDF fuer Proof of Address}
        {--type-registration='.self::DEFAULT_DOCUMENT_TYPE.' : Dokumenttyp fuer Proof of Registration (commercial_registrar_excerpt oder tax_document)}
        {--type-identity='.self::DEFAULT_DOCUMENT_TYPE.' : Dokumenttyp fuer Proof of Business Identity (commercial_registrar_excerpt, trade_license oder tax_document)}
        {--type-address='.self::DEFAULT_DOCUMENT_TYPE.' : Dokumenttyp fuer Proof of Address (commercial_registrar_excerpt, trade_license oder tax_document)}
        {--submit : Das fertige Bundle zur Pruefung einreichen (sonst bleibt es im Stand draft)}';

    protected $description = 'Assemble and submit the German "Local - Business" regulatory bundle required to buy a DE voice number.';

    public function handle(CompanyProfile $company): int
    {
        if ($this->accountSid() === '' || $this->authToken() === '') {
            $this->error('Twilio-Zugangsdaten fehlen (TWILIO_SID/TWILIO_TOKEN oder Admin-Panel "Twilio").');

            return self::FAILURE;
        }

        if ($this->option('requirements')) {
            return $this->showRequirements();
        }

        $existing = $this->existingBundle();

        if (is_int($existing)) {
            return $existing;
        }

        $attributes = $this->endUserAttributes($company);
        $addressSid = $this->resolveAddressSid();
        $documents = $this->documentPlan();

        if (! $this->assertComplete($attributes, $addressSid, $documents)) {
            return self::FAILURE;
        }

        return $this->fillBundle($existing, $attributes, (string) $addressSid, $documents);
    }

    /**
     * Rein lesend: was Twilio fuer diese Regulierung verlangt. Nuetzlich, um
     * die Angaben einzusammeln, bevor irgendetwas angelegt wird.
     */
    private function showRequirements(): int
    {
        $response = $this->client()->get(self::BASE_URL.'/Regulations/'.self::REGULATION_SID);

        if ($response->failed()) {
            $this->error('Regulierung nicht abrufbar: '.$response->body());

            return self::FAILURE;
        }

        $this->info($response->json('friendly_name').' ('.self::REGULATION_SID.')');
        $this->newLine();

        $this->line('<comment>Angaben zum Enduser (Typ business):</comment>');

        foreach ($response->json('requirements.end_user.0.detailed_fields', []) as $field) {
            $this->line('  - '.$field['machine_name'].' -- '.$field['friendly_name'].
                ($field['description'] === '' ? '' : ': '.$field['description']));
        }

        $this->newLine();
        $this->line('<comment>Nachweise (je ein PDF, dasselbe Dokument darf mehrfach dienen):</comment>');

        foreach ($response->json('requirements.supporting_document.0', []) as $requirement) {
            $types = array_map(
                static fn (array $document): string => $document['type'],
                $requirement['accepted_documents'],
            );

            $this->line('  - '.$requirement['name'].': '.implode(', ', $types));
        }

        return self::SUCCESS;
    }

    /**
     * Sucht das deutsche Bundle des Kontos. Ein zweites zur selben Regulierung
     * hilft niemandem, deshalb wird ein vorhandener Entwurf weiterverwendet --
     * genau der Fall hier: das Trust-Hub-Onboarding hinterlaesst ein leeres
     * Bundle im Stand draft, dem nur ein Enduser ohne Attribute anhaengt.
     *
     * @return string|int|null die SID eines weiterverwendbaren Entwurfs, null
     *                         fuer "neu anlegen", oder ein Exit-Code, wenn
     *                         hier nichts mehr zu tun ist
     */
    private function existingBundle(): string|int|null
    {
        $response = $this->client()->get(self::BASE_URL.'/Bundles', [
            'IsoCountry' => 'DE',
            'NumberType' => 'local',
        ]);

        if ($response->failed()) {
            $this->error('Bundles nicht abrufbar: '.$response->body());

            return self::FAILURE;
        }

        foreach ($response->json('results', []) as $bundle) {
            if ($bundle['status'] === 'twilio-rejected') {
                continue;
            }

            if ($bundle['status'] === 'twilio-approved') {
                $this->info('Bundle '.$bundle['sid'].' ist bereits freigegeben -- der Nummernkauf kann laufen (Ticket #27).');

                return self::SUCCESS;
            }

            if ($bundle['status'] !== 'draft') {
                $this->warn('Bundle '.$bundle['sid'].' steht im Stand "'.$bundle['status'].'" -- die Pruefung laeuft, hier ist nichts zu tun.');

                return self::SUCCESS;
            }

            $this->line('Vorhandener Entwurf wird weiterverwendet: '.$bundle['sid']);

            return (string) $bundle['sid'];
        }

        return null;
    }

    /**
     * Entwuerfe aus dem Trust-Hub-Onboarding tragen einen Enduser ohne ein
     * einziges Attribut. Der wuerde die Pruefung sicher scheitern lassen und
     * blockiert den Platz fuer den richtigen -- also abhaengen, bevor der
     * eigene angehaengt wird. Angefasst wird nur, was nachweislich leer ist.
     */
    private function pruneEmptyEndUsers(string $bundleSid): void
    {
        $assignments = $this->client()->get(self::BASE_URL.'/Bundles/'.$bundleSid.'/ItemAssignments');

        foreach ($assignments->json('results', []) as $assignment) {
            $objectSid = (string) $assignment['object_sid'];

            if (! str_starts_with($objectSid, 'IT')) {
                continue;
            }

            $endUser = $this->client()->get(self::BASE_URL.'/EndUsers/'.$objectSid);

            if ($endUser->failed() || $endUser->json('attributes') !== []) {
                continue;
            }

            $this->client()->delete(self::BASE_URL.'/Bundles/'.$bundleSid.'/ItemAssignments/'.$assignment['sid']);
            $this->line('Leerer Enduser '.$objectSid.' vom Bundle abgehaengt.');
        }
    }

    /**
     * Die sechs Pflichtfelder plus die optionalen Kommentare. Was nicht als
     * Option kommt, wird aus den Rechnungseinstellungen gezogen -- dieselbe
     * Quelle wie Impressum und Rechnungen (App\Services\CompanyProfile).
     *
     * @return array<string, string>
     */
    private function endUserAttributes(CompanyProfile $company): array
    {
        return array_filter([
            'business_name' => $this->text('business-name') ?: (string) $company->name(),
            'business_registration_number' => $this->text('registration-number')
                ?: (string) ($company->registrationNumber() ?: $company->vatId()),
            'business_website' => $this->text('website') ?: (string) config('app.url'),
            'first_name' => $this->text('first-name'),
            'last_name' => $this->text('last-name'),
            'email' => $this->text('email') ?: (string) config('mail.from.address'),
            'comments' => $this->text('comments'),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * Die Adresse bindet das Bundle an den Ortsnetzbereich: eine Nummer aus
     * einer anderen Vorwahl lehnt Twilio mit Fehler 21615 ab. Steht genau eine
     * validierte deutsche Adresse im Konto, ist die Wahl eindeutig.
     */
    private function resolveAddressSid(): ?string
    {
        $given = $this->text('address');

        if ($given !== '') {
            return $given;
        }

        $response = $this->client()->get(
            'https://api.twilio.com/2010-04-01/Accounts/'.$this->accountSid().'/Addresses.json',
        );

        if ($response->failed()) {
            return null;
        }

        $candidates = array_values(array_filter(
            $response->json('addresses', []),
            static fn (array $address): bool => $address['iso_country'] === 'DE' && $address['validated'] === true,
        ));

        if (count($candidates) !== 1) {
            return null;
        }

        $this->line('Adresse: '.$candidates[0]['sid'].' ('.$candidates[0]['street'].', '.
            $candidates[0]['postal_code'].' '.$candidates[0]['city'].')');

        return $candidates[0]['sid'];
    }

    /**
     * Je Nachweis ein Eintrag: Anzeigename, Dokumenttyp, PDF-Pfad und die
     * Attribute, die Twilio fuer genau diesen Nachweis erwartet. Der Pfad darf
     * leer sein -- das faengt assertComplete() ab.
     *
     * @return list<array{label: string, option: string, type: string, path: string}>
     */
    private function documentPlan(): array
    {
        return [
            [
                'label' => 'Proof of Registration',
                'option' => 'proof-registration',
                'type' => $this->text('type-registration'),
                'path' => $this->text('proof-registration'),
            ],
            [
                'label' => 'Proof of Business Identity',
                'option' => 'proof-identity',
                'type' => $this->text('type-identity'),
                'path' => $this->text('proof-identity'),
            ],
            [
                'label' => 'Proof of Address',
                'option' => 'proof-address',
                'type' => $this->text('type-address'),
                'path' => $this->text('proof-address'),
            ],
        ];
    }

    /**
     * Prueft alles, bevor das erste Objekt im Konto entsteht. Fehlt etwas,
     * wird nichts angelegt und stattdessen aufgelistet, was zu besorgen ist.
     *
     * @param  array<string, string>  $attributes
     * @param  list<array{label: string, option: string, type: string, path: string}>  $documents
     */
    private function assertComplete(array $attributes, ?string $addressSid, array $documents): bool
    {
        $missing = [];

        foreach (['business_name', 'business_registration_number', 'business_website', 'first_name', 'last_name', 'email'] as $field) {
            if (($attributes[$field] ?? '') === '') {
                $missing[] = ['Enduser-Feld '.$field, $this->hintFor($field)];
            }
        }

        if ($addressSid === null) {
            $missing[] = ['Adresse', 'Keine eindeutige validierte deutsche Adresse im Konto -- mit --address=AD... angeben.'];
        }

        foreach ($documents as $document) {
            if ($document['path'] === '') {
                $missing[] = [$document['label'], 'PDF fehlt -- mit --'.$document['option'].'=/pfad/zum/dokument.pdf angeben.'];

                continue;
            }

            if (! is_readable($document['path'])) {
                $missing[] = [$document['label'], 'Datei nicht lesbar: '.$document['path']];
            }
        }

        if ($missing === []) {
            return true;
        }

        $this->error('Es wird nichts angelegt und nichts eingereicht -- es fehlen Angaben:');
        $this->table(['Fehlt', 'Woher'], $missing);
        $this->line('Die Feldbeschreibungen von Twilio zeigt `php artisan leads:submit-de-bundle --requirements`.');

        return false;
    }

    private function hintFor(string $field): string
    {
        return match ($field) {
            'business_name' => 'Firmenname laut Register -- Admin-Panel "Rechnungen" oder --business-name=.',
            'business_registration_number' => 'HRB-Nummer, USt-IdNr oder Steuernummer -- Admin-Panel "Rechnungen" (Kennung/USt-IdNr) oder --registration-number=.',
            'business_website' => 'APP_URL ist leer -- mit --website= angeben.',
            'email' => 'mail.from.address ist leer -- mit --email= angeben.',
            default => 'Nur als Option angebbar: --'.str_replace('_', '-', $field).'=.',
        };
    }

    /**
     * @param  string|null  $bundleSid  vorhandener Entwurf, null legt ein neues Bundle an
     * @param  array<string, string>  $attributes
     * @param  list<array{label: string, option: string, type: string, path: string}>  $documents
     */
    private function fillBundle(?string $bundleSid, array $attributes, string $addressSid, array $documents): int
    {
        if ($bundleSid !== null) {
            $this->pruneEmptyEndUsers($bundleSid);
        }

        $endUser = $this->client()->asForm()->post(self::BASE_URL.'/EndUsers', [
            'FriendlyName' => $attributes['business_name'].' (Business)',
            'Type' => 'business',
            'Attributes' => json_encode($attributes, JSON_THROW_ON_ERROR),
        ]);

        if (! $this->succeeded($endUser, 'Enduser')) {
            return self::FAILURE;
        }

        $this->line('Enduser angelegt: '.$endUser->json('sid'));

        $itemSids = [$endUser->json('sid')];

        foreach ($documents as $document) {
            $uploaded = $this->uploadDocument($document, $attributes, $addressSid);

            if ($uploaded === null) {
                return self::FAILURE;
            }

            $this->line($document['label'].' hochgeladen: '.$uploaded);
            $itemSids[] = $uploaded;
        }

        // Der Entwurf aus dem Onboarding traegt "email@twilio.com" als
        // Kontakt und einen Namen aus dem Zeitstempel -- beides wird beim
        // Fuellen mitgezogen, damit Rueckfragen von Twilio ankommen.
        $bundle = $this->client()->asForm()->post(
            self::BASE_URL.'/Bundles'.($bundleSid === null ? '' : '/'.$bundleSid),
            array_filter([
                'FriendlyName' => $attributes['business_name'].' -- DE Local Voice',
                'Email' => $attributes['email'],
                'RegulationSid' => $bundleSid === null ? self::REGULATION_SID : null,
            ]),
        );

        if (! $this->succeeded($bundle, 'Bundle')) {
            return self::FAILURE;
        }

        $bundleSid = (string) $bundle->json('sid');
        $this->line('Bundle bereit: '.$bundleSid);

        foreach ([...$itemSids, $addressSid] as $objectSid) {
            $assignment = $this->client()->asForm()->post(
                self::BASE_URL.'/Bundles/'.$bundleSid.'/ItemAssignments',
                ['ObjectSid' => $objectSid],
            );

            if (! $this->succeeded($assignment, 'Zuordnung von '.$objectSid)) {
                return self::FAILURE;
            }
        }

        $this->info('Bundle '.$bundleSid.' ist vollstaendig zusammengesetzt.');

        if (! $this->option('submit')) {
            $this->warn('Stand bleibt "draft". Zum Einreichen dasselbe Kommando mit --submit, oder in der Console pruefen und absenden.');

            return self::SUCCESS;
        }

        $submitted = $this->client()->asForm()->post(self::BASE_URL.'/Bundles/'.$bundleSid, [
            'Status' => 'pending-review',
        ]);

        if (! $this->succeeded($submitted, 'Einreichung')) {
            return self::FAILURE;
        }

        $this->info('Eingereicht. Stand: '.$submitted->json('status').
            ' -- die Pruefung bei Twilio dauert Tage, Kontrolle mit `php artisan leads:check-call-setup --remote`.');

        return self::SUCCESS;
    }

    /**
     * Der Upload ist multipart: Attribute als JSON, Datei als File. Die
     * Attribute unterscheiden sich je Nachweis -- der Registrierungsnachweis
     * traegt die Registernummer, der Identitaetsnachweis den Firmennamen, der
     * Anschriftennachweis die Address SID.
     *
     * @param  array{label: string, option: string, type: string, path: string}  $document
     * @param  array<string, string>  $attributes
     * @return string|null die SID des Dokuments, null bei Fehlschlag
     */
    private function uploadDocument(array $document, array $attributes, string $addressSid): ?string
    {
        $documentAttributes = match ($document['option']) {
            'proof-registration' => ['business_registration_number' => $attributes['business_registration_number']],
            'proof-identity' => ['business_name' => $attributes['business_name']],
            default => ['address_sids' => [$addressSid]],
        };

        $response = $this->client()
            ->attach('File', (string) file_get_contents($document['path']), basename($document['path']))
            ->post(self::BASE_URL.'/SupportingDocuments', [
                'FriendlyName' => $document['label'],
                'Type' => $document['type'],
                'Attributes' => json_encode($documentAttributes, JSON_THROW_ON_ERROR),
            ]);

        return $this->succeeded($response, $document['label']) ? (string) $response->json('sid') : null;
    }

    private function succeeded(Response $response, string $what): bool
    {
        if ($response->successful()) {
            return true;
        }

        $this->error($what.' fehlgeschlagen ('.$response->status().'): '.$response->body());

        return false;
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->accountSid(), $this->authToken())->acceptJson();
    }

    private function text(string $option): string
    {
        return trim((string) $this->option($option));
    }

    /**
     * Zugangsdaten stehen entweder in der Env oder, ueber das Admin-Panel
     * gepflegt, im SaaSykit-Block services.twilio.* -- dieselbe Reihenfolge
     * wie in CallService und im Preflight.
     */
    private function accountSid(): string
    {
        return (string) (config('twilio.account_sid') ?: config('services.twilio.sid'));
    }

    private function authToken(): string
    {
        return (string) (config('twilio.auth_token') ?: config('services.twilio.token'));
    }
}

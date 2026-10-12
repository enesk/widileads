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
 *
 * Lehnt Twilio ab (Ticket #79), wird kein zweites Bundle gebaut: das Kommando
 * schluesselt auf, welches Teil beanstandet ist, und ersetzt mit --resubmit nur
 * dieses eine Dokument ueber eine Bundle-Kopie ("Edit and resubmit"). Das
 * abgelehnte Bundle bleibt stehen -- es ist der Nachweis, warum es die Kopie
 * gibt.
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
        {--resubmit : Abgelehntes Bundle kopieren und nur das beanstandete Dokument ersetzen (Twilio: "Edit and resubmit")}
        {--bundle= : Bundle-SID, wenn mehrere Bundles abgelehnt sind oder eine Kopie weiterverwendet werden soll}
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

        if ($this->option('resubmit')) {
            return $this->resubmit($company);
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
     * Ein abgelehntes Bundle wird nicht uebersprungen (das legte ein zweites
     * Bundle von null an, Ticket #77), sondern aufgeschluesselt: welches Teil
     * beanstandet ist und wie der Weg per Kopie lautet (--resubmit).
     *
     * @return string|int|null die SID eines weiterverwendbaren Entwurfs, null
     *                         fuer "neu anlegen", oder ein Exit-Code, wenn
     *                         hier nichts mehr zu tun ist
     */
    private function existingBundle(): string|int|null
    {
        $bundles = $this->fetchBundles();

        if ($bundles === null) {
            return self::FAILURE;
        }

        $rejected = [];
        $draft = null;

        foreach ($bundles as $bundle) {
            $status = (string) $bundle['status'];

            if ($status === 'twilio-approved') {
                $this->info('Bundle '.$bundle['sid'].' ist bereits freigegeben -- der Nummernkauf kann laufen (Ticket #27).');

                return self::SUCCESS;
            }

            if ($status === 'twilio-rejected') {
                $rejected[] = $bundle;

                continue;
            }

            if ($status === 'draft') {
                $draft ??= $bundle;

                continue;
            }

            $this->warn('Bundle '.$bundle['sid'].' steht im Stand "'.$status.'" -- die Pruefung laeuft, hier ist nichts zu tun.');

            return self::SUCCESS;
        }

        if ($draft !== null) {
            return $this->useDraft((string) $draft['sid']);
        }

        if ($rejected !== []) {
            return $this->reportRejected($rejected);
        }

        return null;
    }

    /**
     * Ein Entwurf ist entweder leer (Trust-Hub-Onboarding, regulaerer
     * Fuellweg) oder die Kopie eines abgelehnten Bundles. Im zweiten Fall
     * wuerde der Fuellweg Enduser und alle drei PDFs doppelt anlegen --
     * deshalb endet er hier und verweist auf --resubmit.
     *
     * @return string|int die SID des fuellbaren Entwurfs oder ein Exit-Code
     */
    private function useDraft(string $bundleSid): string|int
    {
        $parts = $this->bundleParts($bundleSid);

        if ($parts !== null && $this->isBundleCopy($parts)) {
            $approved = count(array_filter(
                $parts,
                static fn (array $part): bool => $part['kind'] === 'document' && $part['status'] === 'approved',
            ));

            $this->warn('Entwurf '.$bundleSid.' ist die Kopie eines abgelehnten Bundles (Enduser und '.$approved.
                ' freigegebene Nachweise haengen schon dran). Weiter geht es nur mit --resubmit.');
            $this->line('  php artisan leads:submit-de-bundle --resubmit --bundle='.$bundleSid.
                ' --proof-registration=/pfad/auszug.pdf --submit');

            return $this->option('submit') ? self::FAILURE : self::SUCCESS;
        }

        $this->line('Vorhandener Entwurf wird weiterverwendet: '.$bundleSid);

        return $bundleSid;
    }

    /**
     * Benennt den Ablehnungsgrund Teil fuer Teil. Der Grund steht nicht am
     * Bundle und nicht in seinen Evaluations, sondern als failure_reason/errors
     * an der beanstandeten Ressource selbst.
     *
     * @param  list<array<string, mixed>>  $bundles  alle abgelehnten Bundles, das erste wird aufgeschluesselt
     */
    private function reportRejected(array $bundles): int
    {
        $bundle = $bundles[0];
        $bundleSid = (string) $bundle['sid'];

        $this->warn('Bundle '.$bundleSid.' („'.(string) ($bundle['friendly_name'] ?? '').'") steht auf "twilio-rejected".');

        $parts = $this->bundleParts($bundleSid);

        if ($parts === null) {
            return self::FAILURE;
        }

        $this->table(['Gegenstand', 'SID', 'Stand', 'Grund'], array_map(
            static fn (array $part): array => [$part['label'], $part['object'], $part['status'], $part['reason']],
            $parts,
        ));

        $faulty = $this->rejectedParts($parts);
        $documents = array_values(array_filter(
            $faulty,
            static fn (array $part): bool => $part['kind'] === 'document',
        ));

        $this->line('Beanstandet ist '.count($faulty).' von '.count($parts).' Teilen -- der Rest ist freigegeben.');

        if (count($bundles) > 1) {
            $this->line('Weitere abgelehnte Bundles: '.implode(', ', array_map(
                static fn (array $other): string => (string) $other['sid'],
                array_slice($bundles, 1),
            )).' -- mit --bundle=BU... auswaehlen.');
        }

        $this->newLine();

        if ($documents === []) {
            $this->line('Kein abgelehntes Dokument dabei -- das heilt --resubmit nicht, bitte in der Konsole pruefen.');
        } else {
            $options = '';

            foreach ($documents as $document) {
                $options .= ' --'.$this->purposeOf($document).'=/pfad/auszug.pdf';
            }

            $this->line('Weg bei Twilio ist die Bundle-Kopie ("Edit and resubmit"): die freigegebenen Teile');
            $this->line('bleiben, nur das beanstandete Dokument wird ersetzt.');
            $this->line('  php artisan leads:submit-de-bundle --resubmit'.
                (count($bundles) > 1 ? ' --bundle='.$bundleSid : '').$options.' --submit');
        }

        if ($this->option('submit')) {
            $this->newLine();
            $this->error('Es wird nichts angelegt: ohne --resubmit entstuende ein zweites Bundle zur selben Regulierung.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Ticket #79: abgelehntes Bundle kopieren und nur das beanstandete
     * Dokument ersetzen. Alle Vorbedingungen werden vor dem ersten
     * Schreibzugriff geprueft -- fehlt etwas, entsteht nichts im Konto.
     * Das abgelehnte Bundle bleibt in jedem Fall unangetastet: es ist der
     * Nachweis, warum die Kopie existiert.
     */
    private function resubmit(CompanyProfile $company): int
    {
        $source = $this->resubmitTarget();

        if (is_int($source)) {
            return $source;
        }

        $sourceSid = (string) $source['sid'];
        $sourceIsCopy = (string) $source['status'] === 'draft';

        $parts = $this->bundleParts($sourceSid);

        if ($parts === null) {
            return self::FAILURE;
        }

        $faulty = $this->rejectedParts($parts);
        $alien = array_values(array_filter(
            $faulty,
            static fn (array $part): bool => $part['kind'] !== 'document',
        ));

        if ($alien !== []) {
            $this->error('Es wird nichts angelegt und nichts eingereicht -- beanstandet ist nicht nur ein Dokument:');
            $this->table(['Fehlt', 'Woher'], array_map(
                static fn (array $part): array => [
                    $part['label'].' ('.$part['object'].')',
                    'Stand "'.$part['status'].'" -- das kann --resubmit nicht ersetzen. Regulaerer Weg ohne --resubmit oder Korrektur in der Twilio-Konsole.',
                ],
                $alien,
            ));

            return self::FAILURE;
        }

        /** @var array<string, list<string>> $needed Nachweis-Option => SIDs der abzuhaengenden Dokumente */
        $needed = [];

        foreach ($faulty as $part) {
            $needed[$this->purposeOf($part)][] = (string) $part['object'];
        }

        if ($needed === []) {
            if (! $sourceIsCopy) {
                $this->error('Kein abgelehntes Teil gefunden -- der Stand des Bundles passt nicht zu den Teilen, bitte in der Konsole pruefen.');

                return self::FAILURE;
            }

            // Wiederaufsetzpunkt: an der Kopie haengt das abgelehnte Dokument
            // schon nicht mehr. Welcher Nachweis zu ersetzen ist, sagen dann
            // die uebergebenen --proof-*-Optionen.
            foreach ($this->documentPlan() as $document) {
                if ($document['path'] !== '') {
                    $needed[$document['option']] = [];
                }
            }

            if ($needed === []) {
                $this->error('Es wird nichts angelegt und nichts eingereicht -- es fehlen Angaben:');
                $this->table(['Fehlt', 'Woher'], [[
                    'Ersatz-PDF',
                    'An der Kopie '.$sourceSid.' haengt kein abgelehntes Dokument mehr -- mit --proof-registration=/pfad/auszug.pdf angeben, welcher Nachweis ersetzt wird.',
                ]]);

                return self::FAILURE;
            }
        }

        $plan = array_column($this->documentPlan(), null, 'option');
        $attributes = $this->approvedAttributes($parts, $company);
        $addressSid = $this->bundleAddressSid($parts) ?? $this->resolveAddressSid();
        $missing = [];

        foreach (array_keys($needed) as $option) {
            $document = $plan[$option];

            if ($document['path'] === '') {
                $missing[] = [$document['label'], 'PDF fehlt -- mit --'.$option.'=/pfad/zum/dokument.pdf angeben.'];
            } elseif (! is_readable((string) $document['path'])) {
                $missing[] = [$document['label'], 'Datei nicht lesbar: '.$document['path']];
            }

            if ($option === 'proof-registration' && ($attributes['business_registration_number'] ?? '') === '') {
                $missing[] = ['Enduser-Feld business_registration_number', $this->hintFor('business_registration_number')];
            }

            if ($option === 'proof-identity' && ($attributes['business_name'] ?? '') === '') {
                $missing[] = ['Enduser-Feld business_name', $this->hintFor('business_name')];
            }

            if ($option === 'proof-address' && $addressSid === null) {
                $missing[] = ['Adresse', 'Keine Adresse am Bundle und keine eindeutige validierte deutsche im Konto -- mit --address=AD... angeben.'];
            }
        }

        if ($missing !== []) {
            $this->error('Es wird nichts angelegt und nichts eingereicht -- es fehlen Angaben:');
            $this->table(['Fehlt', 'Woher'], $missing);

            return self::FAILURE;
        }

        // Ab hier wird geschrieben.
        if ($sourceIsCopy) {
            $copySid = $sourceSid;
            $copyParts = $parts;
            $this->line('Kopie '.$copySid.' wird weiterverwendet (draft) -- es wird keine zweite angelegt.');
        } else {
            $copy = $this->client()->asForm()->post(self::BASE_URL.'/Bundles/'.$sourceSid.'/Copies', array_filter([
                'FriendlyName' => trim((string) ($source['friendly_name'] ?? '')) === ''
                    ? null
                    : (string) $source['friendly_name'].' -- Kopie',
            ]));

            if (! $this->succeeded($copy, 'Bundle-Kopie')) {
                return self::FAILURE;
            }

            $copySid = (string) $copy->json('sid');
            $this->info('Kopie angelegt: '.$copySid.' ('.(string) $copy->json('status').
                ') -- freigegebene Teile sind uebernommen.');

            $copyParts = $this->bundleParts($copySid);

            if ($copyParts === null) {
                return $this->abortedCopy($copySid, 'ItemAssignments der Kopie nicht abrufbar.');
            }
        }

        $objectSids = array_merge(...array_values($needed));

        foreach ($copyParts as $part) {
            if ($part['kind'] !== 'document') {
                continue;
            }

            if ($part['status'] !== 'rejected' && ! in_array((string) $part['object'], $objectSids, true)) {
                continue;
            }

            $detached = $this->client()->delete(
                self::BASE_URL.'/Bundles/'.$copySid.'/ItemAssignments/'.$part['assignment'],
            );

            if (! $detached->successful()) {
                return $this->abortedCopy($copySid, 'Abgelehntes Dokument '.$part['object'].
                    ' liess sich nicht abhaengen ('.$detached->status().'): '.$detached->body());
            }

            $this->line('Abgelehntes Dokument '.$part['object'].' abgehaengt.');
        }

        foreach (array_keys($needed) as $option) {
            $document = $plan[$option];
            $uploaded = $this->uploadDocument($document, $attributes, (string) $addressSid);

            if ($uploaded === null) {
                return $this->abortedCopy($copySid, 'Upload von '.$document['label'].' fehlgeschlagen.');
            }

            $this->line($document['label'].' hochgeladen: '.$uploaded);

            $assignment = $this->client()->asForm()->post(
                self::BASE_URL.'/Bundles/'.$copySid.'/ItemAssignments',
                ['ObjectSid' => $uploaded],
            );

            if (! $assignment->successful()) {
                return $this->abortedCopy($copySid, 'Zuordnung von '.$uploaded.' zur Kopie fehlgeschlagen ('.
                    $assignment->status().'): '.$assignment->body());
            }

            $this->line('An die Kopie angehaengt: '.$uploaded);
        }

        $this->info('Kopie '.$copySid.' ist vollstaendig -- Enduser und freigegebene Nachweise sind unveraendert uebernommen.'.
            ($sourceIsCopy ? '' : ' Das abgelehnte Bundle '.$sourceSid.' bleibt unangetastet.'));

        if (! $this->option('submit')) {
            $this->warn('Stand der Kopie bleibt "draft". Zum Einreichen dasselbe Kommando mit --submit.');

            return self::SUCCESS;
        }

        $submitted = $this->client()->asForm()->post(self::BASE_URL.'/Bundles/'.$copySid, [
            'Status' => 'pending-review',
        ]);

        if (! $submitted->successful()) {
            return $this->abortedCopy($copySid, 'Einreichung fehlgeschlagen ('.$submitted->status().'): '.$submitted->body());
        }

        $this->info('Eingereicht. Stand: '.$submitted->json('status').
            ' -- die Pruefung bei Twilio dauert Tage, Kontrolle mit `php artisan leads:check-call-setup --remote`.');

        return self::SUCCESS;
    }

    /**
     * Auf welchem Bundle arbeitet --resubmit? Vorrang hat --bundle=, danach
     * eine Kopie aus einem abgebrochenen Lauf, danach das eine abgelehnte
     * Bundle. Ist die Lage nicht eindeutig, wird nichts angefasst.
     *
     * @return array<string, mixed>|int das Bundle oder ein Exit-Code
     */
    private function resubmitTarget(): array|int
    {
        $given = $this->text('bundle');

        if ($given !== '') {
            $response = $this->client()->get(self::BASE_URL.'/Bundles/'.$given);

            if ($response->failed()) {
                $this->error('Bundle '.$given.' nicht abrufbar: '.$response->body());

                return self::FAILURE;
            }

            $status = (string) $response->json('status');

            if (! in_array($status, ['twilio-rejected', 'draft'], true)) {
                $this->error('Bundle '.$given.' steht auf "'.$status.
                    '" -- --resubmit arbeitet nur auf abgelehnten Bundles und auf deren Kopien im Stand draft.');

                return self::FAILURE;
            }

            return (array) $response->json();
        }

        $bundles = $this->fetchBundles();

        if ($bundles === null) {
            return self::FAILURE;
        }

        foreach ($bundles as $bundle) {
            if ((string) $bundle['status'] !== 'draft') {
                continue;
            }

            $parts = $this->bundleParts((string) $bundle['sid']);

            if ($parts !== null && $this->isBundleCopy($parts)) {
                $this->line('Kopie aus einem vorherigen Lauf gefunden: '.$bundle['sid'].' -- sie wird weiterverwendet.');

                return $bundle;
            }
        }

        $rejected = array_values(array_filter(
            $bundles,
            static fn (array $bundle): bool => (string) $bundle['status'] === 'twilio-rejected',
        ));

        if ($rejected === []) {
            $this->error('Kein abgelehntes Bundle im Konto -- --resubmit hat hier nichts zu tun.');

            return self::FAILURE;
        }

        if (count($rejected) > 1) {
            $this->error('Es wird nichts angelegt und nichts eingereicht -- die Lage ist nicht eindeutig:');
            $this->table(['Fehlt', 'Woher'], [[
                'Auswahl des Bundles',
                'Mehrere abgelehnte Bundles ('.implode(', ', array_map(
                    static fn (array $bundle): string => (string) $bundle['sid'],
                    $rejected,
                )).') -- mit --bundle=BU... auswaehlen.',
            ]]);

            return self::FAILURE;
        }

        return $rejected[0];
    }

    /**
     * Die Kopie steht nach einem Abbruch als draft im Konto. Damit der naechste
     * Lauf sie weiterverwendet und keine zweite anlegt, nennt die Fehlermeldung
     * immer SID und Wiederaufsetzpunkt.
     */
    private function abortedCopy(string $copySid, string $problem): int
    {
        $this->error($problem);
        $this->error('Kopie '.$copySid.' steht als draft im Konto. Erneut mit --resubmit --bundle='.$copySid.
            ' aufrufen -- es wird dieselbe Kopie weiterverwendet, keine zweite angelegt.');

        return self::FAILURE;
    }

    /**
     * Die deutschen Local-Bundles des Kontos.
     *
     * @return list<array<string, mixed>>|null
     */
    private function fetchBundles(): ?array
    {
        $response = $this->client()->get(self::BASE_URL.'/Bundles', [
            'IsoCountry' => 'DE',
            'NumberType' => 'local',
        ]);

        if ($response->failed()) {
            $this->error('Bundles nicht abrufbar: '.$response->body());

            return null;
        }

        return array_values($response->json('results', []));
    }

    /**
     * Loest die ItemAssignments eines Bundles zu lesbaren Teilen auf: Enduser,
     * Nachweise und Adresse, je mit Stand und Ablehnungsgrund. Der Grund steht
     * nicht am Bundle, sondern an der Ressource selbst.
     *
     * @return list<array{assignment: string, object: string, kind: string, label: string, status: string, reason: string, attributes: array<string, mixed>}>|null
     */
    private function bundleParts(string $bundleSid): ?array
    {
        $response = $this->client()->get(self::BASE_URL.'/Bundles/'.$bundleSid.'/ItemAssignments');

        if ($response->failed()) {
            $this->error('Teile von Bundle '.$bundleSid.' nicht abrufbar: '.$response->body());

            return null;
        }

        $parts = [];

        foreach ($response->json('results', []) as $assignment) {
            $objectSid = (string) $assignment['object_sid'];

            $part = [
                'assignment' => (string) $assignment['sid'],
                'object' => $objectSid,
                'kind' => 'other',
                'label' => 'Unbekanntes Objekt',
                'status' => 'ohne Beanstandung',
                'reason' => '',
                'attributes' => [],
            ];

            if (str_starts_with($objectSid, 'IT')) {
                $endUser = $this->client()->get(self::BASE_URL.'/EndUsers/'.$objectSid);
                $part['kind'] = 'enduser';
                $part['label'] = 'Enduser ('.(string) ($endUser->json('type') ?: 'business').')';
                $part['attributes'] = $endUser->json('attributes') ?? [];
            } elseif (str_starts_with($objectSid, 'RD')) {
                $document = $this->client()->get(self::BASE_URL.'/SupportingDocuments/'.$objectSid);
                $part['kind'] = 'document';
                $part['label'] = (string) ($document->json('friendly_name') ?: $document->json('type'));
                $part['status'] = (string) ($document->json('status') ?: 'ohne Stand');
                $part['reason'] = $this->failureReason($document);
                $part['attributes'] = $document->json('attributes') ?? [];
            } elseif (str_starts_with($objectSid, 'AD')) {
                $part['kind'] = 'address';
                $part['label'] = 'Adresse';
            }

            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * Ablehnungsgrund als ein Satz: failure_reason, ergaenzt um die
     * Fehlercodes aus errors (z. B. "18001: The document you have uploaded ...").
     */
    private function failureReason(Response $document): string
    {
        $reason = trim((string) $document->json('failure_reason'));

        $codes = array_map(
            static fn (array $error): string => trim(
                (string) ($error['code'] ?? '').': '.(string) ($error['description'] ?? $error['message'] ?? ''),
                " \t\n\r\0\x0B:",
            ),
            $document->json('errors') ?? [],
        );

        return implode(' | ', array_filter([$reason, ...$codes], static fn (string $text): bool => $text !== ''));
    }

    /**
     * @param  list<array{assignment: string, object: string, kind: string, label: string, status: string, reason: string, attributes: array<string, mixed>}>  $parts
     * @return list<array{assignment: string, object: string, kind: string, label: string, status: string, reason: string, attributes: array<string, mixed>}>
     */
    private function rejectedParts(array $parts): array
    {
        return array_values(array_filter(
            $parts,
            static fn (array $part): bool => $part['status'] === 'rejected',
        ));
    }

    /**
     * Erkennt die Kopie eines abgelehnten Bundles: entweder haengt ein Enduser
     * mit gesetzten Attributen dran oder mindestens ein freigegebener Nachweis.
     * Ein leerer Entwurf aus dem Trust-Hub-Onboarding hat beides nicht.
     *
     * @param  list<array{assignment: string, object: string, kind: string, label: string, status: string, reason: string, attributes: array<string, mixed>}>  $parts
     */
    private function isBundleCopy(array $parts): bool
    {
        foreach ($parts as $part) {
            if ($part['kind'] === 'enduser' && $part['attributes'] !== []) {
                return true;
            }

            if ($part['kind'] === 'document' && $part['status'] === 'approved') {
                return true;
            }
        }

        return false;
    }

    /**
     * Welcher Nachweis ist das? Die Attribute verraten es eindeutig: die
     * Registernummer steht am Registrierungsnachweis, der Firmenname am
     * Identitaetsnachweis, die Address-SID am Anschriftennachweis. Erst wenn
     * keines davon gesetzt ist, zaehlt der Anzeigename.
     *
     * @param  array{assignment: string, object: string, kind: string, label: string, status: string, reason: string, attributes: array<string, mixed>}  $part
     */
    private function purposeOf(array $part): string
    {
        if (isset($part['attributes']['business_registration_number'])) {
            return 'proof-registration';
        }

        if (isset($part['attributes']['address_sids'])) {
            return 'proof-address';
        }

        if (isset($part['attributes']['business_name'])) {
            return 'proof-identity';
        }

        $label = strtolower($part['label']);

        return match (true) {
            str_contains($label, 'identity') => 'proof-identity',
            str_contains($label, 'address') => 'proof-address',
            default => 'proof-registration',
        };
    }

    /**
     * Firmenname und Registernummer fuer das Ersatzdokument kommen aus dem
     * Enduser, der schon am Bundle haengt -- genau die Daten, die Twilio
     * bereits durchgewinkt hat. Was dort fehlt, fuellen Optionen und
     * Rechnungseinstellungen wie im regulaeren Weg.
     *
     * @param  list<array{assignment: string, object: string, kind: string, label: string, status: string, reason: string, attributes: array<string, mixed>}>  $parts
     * @return array<string, string>
     */
    private function approvedAttributes(array $parts, CompanyProfile $company): array
    {
        $attributes = $this->endUserAttributes($company);

        foreach ($parts as $part) {
            if ($part['kind'] !== 'enduser' || $part['attributes'] === []) {
                continue;
            }

            $fromBundle = array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $part['attributes'],
            );

            $attributes = [...$attributes, ...$fromBundle];

            break;
        }

        return array_filter($attributes, static fn (string $value): bool => $value !== '');
    }

    /**
     * @param  list<array{assignment: string, object: string, kind: string, label: string, status: string, reason: string, attributes: array<string, mixed>}>  $parts
     */
    private function bundleAddressSid(array $parts): ?string
    {
        foreach ($parts as $part) {
            if ($part['kind'] === 'address') {
                return $part['object'];
            }
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

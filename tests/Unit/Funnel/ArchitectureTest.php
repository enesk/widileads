<?php

declare(strict_types=1);

namespace Tests\Unit\Funnel;

use App\Dto\LeadContact;
use App\Services\LeadContactResolver;
use App\Services\LeadStateService;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * FB-042: Architekturregeln, die sich am Quelltext pruefen lassen.
 *
 * Alle drei Regeln aus dem Ticket stehen hier. Die dritte kam mit FB-032 dazu,
 * als `LeadContact` entstanden ist.
 *
 * Gepruefte Regeln:
 *
 * 1. Architekturleitsatz 2: `lead_state` wechselt ausschliesslich in
 *    LeadStateService. Dazu Leitsatz 4: `settled_price` und `settled_at`
 *    schreibt derselbe Dienst, sonst niemand.
 * 2. Architekturleitsatz 8: Schwellwerte (Fristen, Preise, Limits) stehen in
 *    config/funnel.php, nicht als Zahl im Code.
 * 3. Architekturleitsatz 5: Klartext-Kontaktdaten entstehen nur in LeadContact
 *    und werden nur ueber den LeadContactResolver herausgegeben.
 *
 * Gelesen wird ueber den PHP-Tokenizer statt per Textsuche: Kommentare und
 * Zeichenketten sollen nicht mitzaehlen. Ein Docblock, der `update(['lead_state'
 * => ...])` als Gegenbeispiel zitiert, ist kein Verstoss.
 */
class ArchitectureTest extends TestCase
{
    /**
     * Spalten, die nur ihr zustaendiger Dienst schreiben darf, und die Datei,
     * in der das erlaubt ist.
     *
     * @return array<string, string> Spalte => absoluter Pfad der einzigen erlaubten Datei
     */
    private function guardedColumns(): array
    {
        $stateService = (string) (new ReflectionClass(LeadStateService::class))->getFileName();

        return [
            'lead_state' => $stateService,
            'settled_price' => $stateService,
            'settled_at' => $stateService,
        ];
    }

    /**
     * Methoden, deren Zahlenargument fast immer ein fachlicher Schwellwert ist:
     * Fristen, Wartezeiten, Stueckzahlen.
     *
     * @var list<string>
     */
    private const THRESHOLD_METHODS = [
        'addDays', 'subDays',
        'addWeeks', 'subWeeks',
        'addMonths', 'subMonths',
        'addYears', 'subYears',
        'addHours', 'subHours',
        'addMinutes', 'subMinutes',
        'addSeconds', 'subSeconds',
        'take', 'limit', 'chunk', 'chunkById',
    ];

    /**
     * Zahlenliterale, die in einer Datei ausnahmsweise stehen duerfen, weil sie
     * kein Schwellwert sind. Jeder Eintrag braucht eine Begruendung im Review --
     * die Liste ist die Sollbruchstelle dieses Tests und soll kurz bleiben.
     *
     * @var array<string, list<int|float>> relativer Pfad => erlaubte Werte
     */
    private const ALLOWED_LITERALS = [];

    public function test_guarded_lead_columns_are_only_written_by_their_service(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            $code = $this->codeWithoutComments($file->getRealPath());

            foreach ($this->guardedColumns() as $column => $allowedFile) {
                if ($file->getRealPath() === $allowedFile) {
                    continue;
                }

                foreach ($this->writeSites($code, $column) as $line => $reason) {
                    $violations[] = $this->relativePath($file->getRealPath()).':'.$line.' -- '.$reason;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", array_merge(
            ['Zustands- und Abrechnungsspalten duerfen nur ueber LeadStateService::transition() geschrieben werden (Architekturleitsatz 2 und 4):', ''],
            $violations,
        )));
    }

    public function test_business_thresholds_are_not_hardcoded_in_funnel_code(): void
    {
        $violations = [];

        foreach ($this->funnelFiles() as $file) {
            $relative = $this->relativePath($file->getRealPath());
            $allowed = self::ALLOWED_LITERALS[$relative] ?? [];

            foreach ($this->thresholdLiterals($this->codeWithoutComments($file->getRealPath())) as $line => $finding) {
                if (in_array($finding['value'], $allowed, false)) {
                    continue;
                }

                $violations[] = $relative.':'.$line.' -- '.$finding['reason'];
            }
        }

        $this->assertSame([], $violations, implode("\n", array_merge(
            ['Fristen, Preise und Limits gehoeren nach config/funnel.php (Architekturleitsatz 8):', ''],
            $violations,
        )));
    }

    public function test_clear_text_contact_data_is_only_reachable_through_lead_contact(): void
    {
        $contactBuilder = (string) (new ReflectionClass(LeadContact::class))->getFileName();
        $resolver = (string) (new ReflectionClass(LeadContactResolver::class))->getFileName();

        $violations = [];

        foreach ($this->phpFilesIn(app_path()) as $file) {
            $path = (string) $file->getRealPath();
            $code = $this->codeWithoutComments($path);
            $relative = $this->relativePath($path);

            // Eine unmaskierte Fassung entsteht nur an einer Stelle. Wer sie
            // selbst baut, umgeht die Entscheidung darueber, wer sie sehen darf.
            if ($path !== $resolver && $path !== $contactBuilder) {
                foreach ($this->fromLeadCalls($code) as $line) {
                    $violations[] = $relative.':'.$line.' -- LeadContact::fromLead() ausserhalb des LeadContactResolver';
                }
            }

            // Und die Rohspalten liest ausschliesslich LeadContact selbst.
            if ($path !== $contactBuilder) {
                foreach ($this->rawContactReads($code) as $line) {
                    $violations[] = $relative.':'.$line.' -- direkter Zugriff auf eine Kontaktspalte';
                }
            }
        }

        // Views geben nur aus, was der LeadPresenter herausgibt -- sie kennen
        // die Kontaktspalten gar nicht.
        foreach ($this->phpFilesIn(resource_path('views')) as $file) {
            $path = (string) $file->getRealPath();
            $template = $this->withoutBladeComments((string) file_get_contents($path));

            if (preg_match('/(?:email_normalized|phone_e164)/', $template) === 1) {
                $violations[] = $this->relativePath($path).' -- Kontaktspalte in einem Template';
            }
        }

        $this->assertSame([], $violations, implode("\n", array_merge(
            ['Klartext-Kontaktdaten gibt es nur ueber LeadContact (Architekturleitsatz 5):', ''],
            $violations,
        )));
    }

    /**
     * Ein Architektur-Test, der nichts findet, ist von einem kaputten Test nicht
     * zu unterscheiden. Diese Probe stellt sicher, dass beide Regeln auf
     * bekannte Verstoesse tatsaechlich anschlagen -- und auf die typischen
     * Nicht-Verstoesse eben nicht.
     */
    public function test_the_rules_catch_known_violations(): void
    {
        $violating = <<<'PHP'
        <?php
        $lead->lead_state = LeadState::VERKAUFT;
        Lead::query()->update(['lead_state' => 'verkauft']);
        $lead->settled_price = 15.00;
        $cutoff = now()->subDays(730);
        PHP;

        $violatingCode = $this->stripComments($violating);

        $this->assertNotSame([], $this->writeSites($violatingCode, 'lead_state'), 'Direkte Zuweisung und Massen-Update muessen auffallen.');
        $this->assertNotSame([], $this->writeSites($violatingCode, 'settled_price'));
        $this->assertNotSame([], $this->thresholdLiterals($violatingCode), 'Preis-Literal und Fristen-Literal muessen auffallen.');

        $clean = <<<'PHP'
        <?php
        // Ein Kommentar ueber update(['lead_state' => ...]) ist kein Verstoss.
        /** Auch ein Docblock mit $lead->lead_state = ... nicht. */
        return [
            'lead_state' => LeadState::class,
            'settled_price' => 'decimal:2',
        ];
        $query->where('lead_state', LeadState::NEU);
        $cutoff = now()->subDays((int) config('funnel.lead.retention_days'));
        $price = (float) config('funnel.lead.default_price');
        PHP;

        // Wie im Ernstfall: erst Kommentare entfernen, dann pruefen.
        $cleanCode = $this->stripComments($clean);

        $this->assertSame([], $this->writeSites($cleanCode, 'lead_state'), 'Cast-Deklaration, Kommentar und where() sind keine Schreibzugriffe.');
        $this->assertSame([], $this->writeSites($cleanCode, 'settled_price'));
        $this->assertSame([], $this->thresholdLiterals($cleanCode), 'Werte aus der Konfiguration sind keine Literale.');

        $leakingCode = $this->stripComments(<<<'PHP'
        <?php
        $contact = LeadContact::fromLead($lead);
        $mail = $lead->email_normalized;
        $number = $lead->phone_e164;
        PHP);

        $this->assertNotSame([], $this->fromLeadCalls($leakingCode), 'Eine selbst gebaute Kontaktfassung muss auffallen.');
        $this->assertNotSame([], $this->rawContactReads($leakingCode), 'Ein direkter Zugriff auf die Kontaktspalten muss auffallen.');

        $presenterCode = $this->stripComments(<<<'PHP'
        <?php
        $contact = $lead->contactFor($viewer);
        $mail = $presenter->email();
        PHP);

        $this->assertSame([], $this->fromLeadCalls($presenterCode), 'Der Weg ueber contactFor() ist kein Verstoss.');
        $this->assertSame([], $this->rawContactReads($presenterCode));

        $this->assertSame('', trim($this->withoutBladeComments('{{-- email_normalized --}}')), 'Blade-Kommentare geben nichts aus.');
    }

    /**
     * Schreibzugriffe auf eine Spalte, in zwei Formen: die direkte Zuweisung
     * `$model->spalte = ...` und die Massenzuweisung `update(['spalte' => ...])`.
     *
     * @return array<int, string> Zeilennummer => Beschreibung
     */
    private function writeSites(string $code, string $column): array
    {
        $sites = [];

        $assignment = '/->\s*'.preg_quote($column, '/').'\s*=(?!=)/';
        foreach ($this->matchLines($code, $assignment) as $line) {
            $sites[$line] = "direkte Zuweisung an {$column}";
        }

        $massAssignment = '/\b(?:update|create|fill|forceFill|insert|upsert|updateOrCreate|firstOrCreate|insertOrIgnore)\s*\(\s*\[[^\]]*?\''
            .preg_quote($column, '/')."'\s*=>/s";
        foreach ($this->matchLines($code, $massAssignment) as $line) {
            $sites[$line] = "Massenzuweisung an {$column}";
        }

        return $sites;
    }

    /**
     * Zahlenliterale, die nach einem Schwellwert aussehen.
     *
     * @return array<int, array{value: int|float, reason: string}> Zeilennummer => Fund
     */
    private function thresholdLiterals(string $code): array
    {
        $findings = [];
        $tokens = token_get_all($code);
        $count = count($tokens);

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            // Jede Kommazahl im Funnel-Code ist ein Preis, eine Quote oder ein
            // Faktor -- und damit ein Schwellwert.
            if ($token[0] === T_DNUMBER) {
                $findings[$token[2]] = [
                    'value' => (float) $token[1],
                    'reason' => "Kommazahl {$token[1]} -- Preise und Quoten gehoeren in die Konfiguration",
                ];

                continue;
            }

            if ($token[0] !== T_STRING || ! in_array($token[1], self::THRESHOLD_METHODS, true)) {
                continue;
            }

            $argument = $this->firstNumericArgument($tokens, $index, $count);

            if ($argument !== null) {
                $findings[$token[2]] = [
                    'value' => $argument,
                    'reason' => "{$token[1]}({$argument}) -- Fristen und Limits gehoeren in die Konfiguration",
                ];
            }
        }

        return $findings;
    }

    /**
     * Liefert das Zahlenliteral, wenn der Aufruf ab $index genau damit beginnt.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens
     */
    private function firstNumericArgument(array $tokens, int $index, int $count): int|float|null
    {
        $position = $index + 1;

        while ($position < $count && is_array($tokens[$position]) && $tokens[$position][0] === T_WHITESPACE) {
            $position++;
        }

        if ($position >= $count || $tokens[$position] !== '(') {
            return null;
        }

        $position++;

        while ($position < $count && is_array($tokens[$position]) && $tokens[$position][0] === T_WHITESPACE) {
            $position++;
        }

        if ($position >= $count || ! is_array($tokens[$position])) {
            return null;
        }

        return match ($tokens[$position][0]) {
            T_LNUMBER => (int) $tokens[$position][1],
            T_DNUMBER => (float) $tokens[$position][1],
            default => null,
        };
    }

    /**
     * Quelltext ohne Kommentare, mit erhaltenen Zeilennummern.
     */
    private function codeWithoutComments(string $path): string
    {
        return $this->stripComments((string) file_get_contents($path));
    }

    private function stripComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                // Zeilenumbrueche stehen lassen, damit Zeilennummern stimmen.
                $code .= str_repeat("\n", substr_count($token[1], "\n"));

                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * @return list<int> Zeilennummern, in denen das Muster trifft
     */
    private function matchLines(string $code, string $pattern): array
    {
        if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $lines = [];

        foreach ($matches[0] as [$_, $offset]) {
            $lines[] = substr_count($code, "\n", 0, $offset) + 1;
        }

        return array_values(array_unique($lines));
    }

    /**
     * Dateien des Funnel Builders: alles unter app/, dessen Pfad `lead` oder
     * `funnel` enthaelt. Der mitgelieferte SaaSykit-Bestandscode bleibt aussen
     * vor -- er hat eigene Konfiguration und ist nicht Gegenstand dieser Regel.
     *
     * @return list<SplFileInfo>
     */
    private function funnelFiles(): array
    {
        return array_values(array_filter(
            $this->phpFilesIn(app_path()),
            fn (SplFileInfo $file): bool => preg_match('/(lead|funnel)/i', $this->relativePath((string) $file->getRealPath())) === 1,
        ));
    }

    /**
     * @return list<SplFileInfo>
     */
    private function phpFilesIn(string $directory): array
    {
        return array_values(iterator_to_array(
            Finder::create()->files()->in($directory)->name('*.php'),
            false,
        ));
    }

    /**
     * Stellen, an denen eine unmaskierte Kontaktfassung gebaut wird.
     *
     * @return list<int>
     */
    private function fromLeadCalls(string $code): array
    {
        return $this->matchLines($code, '/LeadContact::fromLead\s*\(/');
    }

    /**
     * Direkte Lesezugriffe auf die Kontaktspalten eines Leads.
     *
     * @return list<int>
     */
    private function rawContactReads(string $code): array
    {
        return $this->matchLines($code, '/->\s*(?:email_normalized|phone_e164)\b/');
    }

    /**
     * Blade-Kommentare entfernen -- ein Kommentar, der eine Kontaktspalte nennt,
     * gibt nichts aus.
     */
    private function withoutBladeComments(string $template): string
    {
        return (string) preg_replace('/\{\{--.*?--\}\}/s', '', $template);
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR);
    }
}

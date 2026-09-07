<?php

namespace Tests\Unit\Funnel;

use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Ein Uebersetzungsschluessel ohne Punkt, der wie eine Sprachdatei heisst,
 * liefert unter Umstaenden ein Array statt eines Strings (FB-028d).
 *
 * Laravel schaut bei __('Preview') zuerst in lang/<locale>.json. Findet es den
 * Schluessel dort nicht, deutet es ihn als Datei: lang/de/Preview.php. Auf einem
 * Dateisystem, das Gross- und Kleinschreibung nicht unterscheidet - macOS, der
 * Rechner der meisten Entwickler hier - trifft das unsere lang/de/preview.php,
 * und __() gibt das komplette Array zurueck. Wer den Rueckgabewert als String
 * weiterreicht, bekommt einen TypeError; die Seite antwortet mit 500.
 *
 * Genau so war /admin/open-graph-image-settings kaputt, waehrend die Testsuite
 * gruen war: Auf dem Linux-Rechner der CI passt "Preview" nicht auf
 * "preview.php", der Fehler tritt dort nicht auf. Ein Test, der die Ausgabe von
 * __() prueft, wuerde deshalb je nach Rechner ein anderes Ergebnis liefern -
 * dieser hier prueft stattdessen statisch, dass die Konstellation gar nicht erst
 * entsteht.
 *
 * Ausweg fuer einen Schluessel, der so heissen muss: Er wird in der JSON-Datei
 * der jeweiligen Sprache hinterlegt. Die wird zuerst gelesen, die Dateisuche
 * findet dann gar nicht mehr statt.
 */
class TranslationKeyCollisionTest extends TestCase
{
    /**
     * Verzeichnisse, in denen nach Uebersetzungsaufrufen gesucht wird.
     *
     * @var list<string>
     */
    private const SCANNED = ['app', 'resources/views'];

    public function test_kein_uebersetzungsschluessel_heisst_wie_eine_sprachdatei(): void
    {
        $problems = [];

        foreach ($this->translationKeys() as $key => $locations) {
            foreach ($this->collidingLocales($key) as $locale) {
                $problems[] = sprintf(
                    '__(\'%s\') in %s trifft auf lang/%s/%s.php. '
                    .'Entweder den Schluessel in eine Themendatei verschieben (z. B. builder.%s), '
                    .'oder ihn in lang/%s.json eintragen.',
                    $key,
                    implode(', ', $locations),
                    $locale,
                    strtolower($key),
                    strtolower($key),
                    $locale,
                );
            }
        }

        $this->assertSame([], $problems, "\n".implode("\n", $problems)."\n");
    }

    /**
     * Sprachen, in denen der Schluessel auf eine Sprachdatei trifft und nicht
     * durch einen JSON-Eintrag abgefangen wird.
     *
     * @return list<string>
     */
    private function collidingLocales(string $key): array
    {
        $colliding = [];

        foreach (glob($this->basePath('lang').'/*', GLOB_ONLYDIR) as $directory) {
            $locale = basename($directory);

            if ($locale === 'vendor') {
                continue;
            }

            $files = array_map(
                fn (string $path): string => strtolower(basename($path, '.php')),
                glob($directory.'/*.php') ?: [],
            );

            if (! in_array(strtolower($key), $files, true)) {
                continue;
            }

            if (array_key_exists($key, $this->jsonTranslations($locale))) {
                continue;
            }

            $colliding[] = $locale;
        }

        return $colliding;
    }

    /**
     * @return array<string, string>
     */
    private function jsonTranslations(string $locale): array
    {
        $path = $this->basePath('lang/'.$locale.'.json');

        if (! is_file($path)) {
            return [];
        }

        return (array) json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Alle Schluessel ohne Punkt, die per __(), trans() oder @lang() uebersetzt
     * werden, samt der Dateien, in denen sie stehen.
     *
     * @return array<string, list<string>>
     */
    private function translationKeys(): array
    {
        $finder = (new Finder)
            ->files()
            ->in(array_map($this->basePath(...), self::SCANNED))
            ->name(['*.php', '*.blade.php']);

        $keys = [];

        /** @var SplFileInfo $file */
        foreach ($finder as $file) {
            $matches = [];

            preg_match_all(
                '/(?:__|trans|@lang)\(\s*\'([^\'\\\\.]+)\'/',
                (string) file_get_contents($file->getPathname()),
                $matches,
            );

            foreach ($matches[1] as $key) {
                $keys[$key][$file->getRelativePathname()] = true;
            }
        }

        return array_map(array_keys(...), $keys);
    }

    private function basePath(string $relative = ''): string
    {
        return dirname(__DIR__, 3).($relative === '' ? '' : '/'.$relative);
    }
}

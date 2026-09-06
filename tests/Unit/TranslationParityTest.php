<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Jeder Uebersetzungsschluessel existiert in jeder Sprache.
 *
 * Diese Luecke faellt sonst niemandem auf: Die Suite laeuft unter APP_LOCALE=de,
 * kein Test rendert die englische Fassung, und `__()` gibt bei einem fehlenden
 * Schluessel den Schluessel selbst zurueck statt zu scheitern. Ein Kaeufer, der
 * die Anwendung auf Englisch nutzt, sieht dann `marketplace.profile.heading`
 * statt eines Titels -- und der Fehler ist bis dahin durch jede Pruefung
 * gelaufen.
 *
 * Genau so ist es zweimal passiert: `api_docs` (FB-030a) und die Audit-
 * Beschriftungen aus FB-050 lagen nur auf Deutsch vor.
 *
 * Geprueft wird in beide Richtungen -- ein Schluessel, den nur Englisch hat, ist
 * genauso ein Fehler -- und ueber alle Sprachverzeichnisse unter lang/. Neue
 * Dateien und neue Sprachen sind automatisch mit abgedeckt; niemand muss diesen
 * Test pflegen.
 *
 * Massgeblich sind die Dateien der Referenzsprache. Laravels mitgelieferte
 * Uebersetzungen liegen nur unter lang/en (auth, validation, passwords,
 * pagination); fuer sie greift die Ruecksprache des Frameworks, sie werden hier
 * nicht gepflegt und deshalb auch nicht eingefordert.
 */
class TranslationParityTest extends TestCase
{
    /**
     * Die Referenzsprache: an ihr messen sich die uebrigen. Deutsch ist die
     * Sprache, in der die Oberflaeche entsteht (Master-Prompt).
     */
    private const REFERENCE_LOCALE = 'de';

    /**
     * Von Paketen mitgelieferte Uebersetzungen -- sie werden nicht hier
     * gepflegt und muessen nicht vollstaendig sein.
     */
    private const IGNORED_DIRECTORIES = ['vendor'];

    /**
     * Ein Fall je Sprache ausser der Referenz.
     *
     * @return array<string, array{string}>
     */
    public static function localeProvider(): array
    {
        $locales = [];

        foreach (self::localeDirectories() as $locale) {
            if ($locale !== self::REFERENCE_LOCALE) {
                $locales[$locale] = [$locale];
            }
        }

        // Ein Test ohne Faelle ist von einem bestandenen Test nicht zu
        // unterscheiden. Gibt es nur die Referenzsprache, soll der Fall
        // trotzdem laufen und den Gleichstand mit sich selbst pruefen.
        return $locales === [] ? [self::REFERENCE_LOCALE => [self::REFERENCE_LOCALE]] : $locales;
    }

    #[DataProvider('localeProvider')]
    public function test_every_key_of_the_reference_locale_exists_in_every_locale(string $locale): void
    {
        $files = self::referenceFiles();

        $this->assertNotSame([], $files, 'Es gibt keine Sprachdateien zu pruefen.');

        $reference = self::keysOf(self::REFERENCE_LOCALE, $files);
        $translated = self::keysOf($locale, $files);

        $missing = array_values(array_diff($reference, $translated));
        $superfluous = array_values(array_diff($translated, $reference));

        $this->assertSame([], $missing, implode("\n", array_merge(
            [sprintf(
                'In lang/%s fehlen %d Schluessel, die lang/%s hat. Ohne sie zeigt die Oberflaeche '
                .'unter dieser Sprache den rohen Schluessel:',
                $locale,
                count($missing),
                self::REFERENCE_LOCALE,
            ), ''],
            $missing,
        )));

        // Auch die Gegenrichtung: ein Schluessel, den nur die Uebersetzung
        // kennt, ist entweder ein Tippfehler oder Ueberbleibsel.
        $this->assertSame([], $superfluous, implode("\n", array_merge(
            [sprintf(
                'lang/%s hat %d Schluessel, die es in lang/%s nicht gibt:',
                $locale,
                count($superfluous),
                self::REFERENCE_LOCALE,
            ), ''],
            $superfluous,
        )));
    }

    /**
     * Die Sprachdateien, die dieses Projekt selbst pflegt: die der
     * Referenzsprache.
     *
     * @return list<string> Dateinamen ohne Endung
     */
    private static function referenceFiles(): array
    {
        $files = array_map(
            static fn (string $path): string => basename($path, '.php'),
            glob(self::langPath(self::REFERENCE_LOCALE.'/*.php')) ?: [],
        );

        sort($files);

        return array_values($files);
    }

    /**
     * Die Schluessel der genannten Dateien als flache Liste
     * `datei.pfad.zum.schluessel`, sortiert.
     *
     * Fehlt eine Datei in dieser Sprache ganz, fehlen damit alle ihre
     * Schluessel -- genau das soll der Test melden.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    private static function keysOf(string $locale, array $files): array
    {
        $keys = [];

        foreach ($files as $file) {
            $path = self::langPath($locale.'/'.$file.'.php');

            if (! is_file($path)) {
                continue;
            }

            $translations = require $path;

            if (! is_array($translations)) {
                continue;
            }

            $keys = array_merge($keys, self::flatten($translations, $file));
        }

        sort($keys);

        return $keys;
    }

    /**
     * @param  array<array-key, mixed>  $translations
     * @return list<string>
     */
    private static function flatten(array $translations, string $prefix): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $path = $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = array_merge($keys, self::flatten($value, $path));

                continue;
            }

            $keys[] = $path;
        }

        return $keys;
    }

    /**
     * Pfad unterhalb von lang/.
     *
     * Bewusst ohne lang_path(): der Datenprovider laeuft, bevor PHPUnit die
     * Anwendung baut -- der Helper waere dort noch nicht verfuegbar.
     */
    private static function langPath(string $path): string
    {
        return dirname(__DIR__, 2).'/lang/'.$path;
    }

    /**
     * @return list<string>
     */
    private static function localeDirectories(): array
    {
        $directories = [];

        foreach (glob(self::langPath('*'), GLOB_ONLYDIR) ?: [] as $path) {
            $locale = basename($path);

            if (! in_array($locale, self::IGNORED_DIRECTORIES, true)) {
                $directories[] = $locale;
            }
        }

        sort($directories);

        return $directories;
    }
}

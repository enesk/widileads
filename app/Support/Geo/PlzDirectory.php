<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Deutsche Postleitzahlen mit Ortsname und Koordinate (LP-ONBOARD-009).
 *
 * **Eine Datei und keine Tabelle.** Die 8.293 Zustellbereiche aendern sich
 * ein-, zweimal im Jahr und gehoeren keinem Mandanten -- sie sind
 * Nachschlagewerk, nicht Betriebsdaten. Als Tabelle braeuchte jede Umgebung
 * eine Migration und einen gelaufenen Seeder, und wo der fehlt, kennt der
 * Wizard keine einzige Postleitzahl und laesst niemanden mehr durch Schritt 2.
 * Als mitgelieferte CSV ist sie ueberall sofort da: in der Entwicklung, in den
 * Tests und auf dem Server.
 *
 * **Quellen** (beide frei verwendbar, einmalig zusammengefuehrt):
 *  - Koordinaten: plz_geocoord des WZB (aus OpenStreetMap, ODbL) -- der
 *    Mittelpunkt des Zustellbereichs und nicht der eines Ortes.
 *  - Ortsnamen: GeoNames Postal Codes DE (CC-BY 4.0, www.geonames.org). Hat
 *    eine Postleitzahl mehrere Namen ("Dresden", "Dresden Innere Altstadt"),
 *    gewinnt der kuerzeste -- das ist der Name, den der Kaeufer erwartet.
 *
 * Grosskunden-Postleitzahlen (eine eigene Nummer fuer ein einzelnes
 * Unternehmen) stehen nicht darin: Dort wohnt niemand, der eine Anfrage
 * stellt, und als Mittelpunkt eines Umkreises waeren sie eine Falle.
 *
 * **Einmal je Anfrage geladen.** Die Datei wird beim ersten Zugriff als Ganzes
 * eingelesen und im Prozess gehalten. Ein Cache-Eintrag waere hier nichts
 * gewonnen: Das Lesen von 270 Kilobyte vom lokalen Dateisystem kostet weniger
 * als der Umweg ueber Redis, und veralten kann die Datei nicht.
 *
 * Gehalten wird statisch und nicht je Objekt: Die Klasse wird an mehreren
 * Stellen frisch aus dem Container geholt (Livewire-Komponente,
 * RegionStatsService), und eine Postleitzahl nachzuschlagen wuerde sonst
 * jedes Mal die ganze Datei neu lesen. Der Inhalt ist unveraenderlich --
 * damit ist der gemeinsame Zustand keiner, der jemandem gehoert.
 */
final class PlzDirectory
{
    /**
     * @var array<string, array{place: string, lat: float, lon: float}>|null
     */
    private static ?array $entries = null;

    /**
     * Was wir ueber eine Postleitzahl wissen -- oder null, wenn wir sie nicht
     * kennen.
     *
     * @return array{postal_code: string, place: string, lat: float, lon: float}|null
     */
    public function find(string $postalCode): ?array
    {
        $postalCode = $this->normalise($postalCode);

        if ($postalCode === null) {
            return null;
        }

        $entry = $this->entries()[$postalCode] ?? null;

        if ($entry === null) {
            return null;
        }

        return [
            'postal_code' => $postalCode,
            'place' => $entry['place'],
            'lat' => $entry['lat'],
            'lon' => $entry['lon'],
        ];
    }

    /**
     * Kennen wir diese Postleitzahl?
     */
    public function exists(string $postalCode): bool
    {
        return $this->find($postalCode) !== null;
    }

    /**
     * Fuenf Ziffern, oder null. Alles andere ist keine deutsche
     * Postleitzahl -- auch nicht "76133 Karlsruhe" oder "D-76133".
     */
    public function normalise(string $postalCode): ?string
    {
        $postalCode = trim($postalCode);

        return preg_match('/^\d{5}$/', $postalCode) === 1 ? $postalCode : null;
    }

    /**
     * Die Entfernung einer Postleitzahl vom Mittelpunkt in Kilometern, oder
     * null, wenn eine der beiden unbekannt ist.
     */
    public function distanceTo(string $centre, string $postalCode): ?float
    {
        $from = $this->find($centre);
        $to = $this->find($postalCode);

        if ($from === null || $to === null) {
            return null;
        }

        return SimpleProjection::distanceInKilometres($from['lat'], $from['lon'], $to['lat'], $to['lon']);
    }

    /**
     * Alle Postleitzahlen im Umkreis -- oder eine leere Liste, wenn wir den
     * Mittelpunkt nicht kennen.
     *
     * Reine Faktenauskunft: Welche Zustellbereiche liegen nah genug? Was die
     * Marktplatz-Vorauswahl daraus macht, entscheidet sie selbst (FB-053).
     *
     * @return list<string>
     */
    public function postalCodesWithin(string $centre, float $radiusKm): array
    {
        $from = $this->find($centre);

        if ($from === null || $radiusKm <= 0.0) {
            return [];
        }

        $within = [];

        foreach ($this->entries() as $postalCode => $entry) {
            $distanceKm = SimpleProjection::distanceInKilometres($from['lat'], $from['lon'], $entry['lat'], $entry['lon']);

            if ($distanceKm <= $radiusKm) {
                $within[] = $postalCode;
            }
        }

        return $within;
    }

    /**
     * Die Datei, einmal je Anfrage gelesen.
     *
     * @return array<string, array{place: string, lat: float, lon: float}>
     */
    private function entries(): array
    {
        if (self::$entries !== null) {
            return self::$entries;
        }

        $entries = [];
        $handle = @fopen($this->path(), 'rb');

        if ($handle === false) {
            // Ohne Nachschlagewerk kennt der Wizard keine Postleitzahl. Das
            // ist ein Fehler der Auslieferung und keiner der Eingabe --
            // geworfen wird hier trotzdem nicht: Der Schritt zeigt dann "Diese
            // PLZ kennen wir nicht", und "Ganz Deutschland" bleibt gangbar.
            return self::$entries = [];
        }

        while (($row = fgetcsv($handle, 128, ',', '"', '')) !== false) {
            if (count($row) < 4) {
                continue;
            }

            $entries[(string) $row[0]] = [
                'place' => (string) $row[1],
                'lat' => (float) $row[2],
                'lon' => (float) $row[3],
            ];
        }

        fclose($handle);

        return self::$entries = $entries;
    }

    private function path(): string
    {
        return resource_path('data/plz-de.csv');
    }
}

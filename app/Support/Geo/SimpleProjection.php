<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Die Abbildung von Lat/Lon auf die Deutschland-Karte (LP-ONBOARD-009).
 *
 * **Eine Gerade, keine Kartografie.** Laengengrad mal Faktor ist x,
 * Breitengrad mal Faktor ist y -- mehr passiert hier nicht. Eine echte
 * Projektion (Mercator, Albers) waere fuer eine Flaeche von Flensburg bis
 * Oberstdorf genauer, braeuchte aber dieselbe Rechnung im Browser, sobald der
 * Schieberegler den Kreis bewegt. Die Karte zeigt eine Groessenordnung ("etwa
 * dieser Teil von Deutschland") und keine Grenze, die jemand nachmisst; der
 * Fehler dieser Geraden liegt ueber Deutschland bei wenigen Kilometern und
 * damit unter der Strichstaerke des Kreises.
 *
 * **Der eine Kniff:** x wird mit dem Kosinus der mittleren Breite gestaucht.
 * Ohne ihn waere Deutschland in die Breite gezogen, weil ein Laengengrad hier
 * nur rund 70 statt 111 Kilometer misst. Mit ihm messen beide Achsen
 * dieselben Kilometer je Einheit -- und erst dadurch ist ein Umkreis auf der
 * Karte ein Kreis und keine Ellipse (siehe kilometresToUnits).
 *
 * **Dieselben Zahlen im Browser.** Die Pfade der Bundeslaender
 * (resources/views/partials/maps/germany.blade.php) sind mit genau diesen
 * Konstanten einmalig aus GeoJSON gerechnet; resources/js/modules/germany-map.js
 * setzt Pin und Kreis live und bekommt die Konstanten als data-Attribute
 * mitgegeben. Wer hier eine Zahl aendert, verschiebt den Pin gegen die
 * Landkarte -- die Pfade muessten dann neu erzeugt werden.
 */
final class SimpleProjection
{
    /** Der westliche Rand der Karte in Grad. */
    public const LON_MIN = 5.8;

    /** Der noerdliche Rand der Karte in Grad. */
    public const LAT_MAX = 55.1;

    /** Einheiten der viewBox je Breitengrad. */
    public const SCALE = 60.0;

    /** Kosinus der mittleren Breite (51,2 Grad) -- die Stauchung der x-Achse. */
    public const LON_FACTOR = 0.6270;

    /** Die Breite der viewBox. */
    public const WIDTH = 350.0;

    /** Die Hoehe der viewBox. */
    public const HEIGHT = 474.0;

    /**
     * Kilometer je Breitengrad. Oeffentlich, weil germany-map.js denselben
     * Wert braucht, um den Radius waehrend des Ziehens zu rechnen.
     */
    public const KM_PER_DEGREE = 111.32;

    /**
     * Ein Punkt der Erde als Punkt der viewBox.
     *
     * @return array{x: float, y: float}
     */
    public static function project(float $latitude, float $longitude): array
    {
        return [
            'x' => round(($longitude - self::LON_MIN) * self::SCALE * self::LON_FACTOR, 1),
            'y' => round((self::LAT_MAX - $latitude) * self::SCALE, 1),
        ];
    }

    /**
     * Ein Umkreis in Kilometern als Radius in Einheiten der viewBox.
     *
     * Beide Achsen tragen denselben Massstab (siehe Klassenkommentar) --
     * deshalb genuegt hier eine Zahl und es braucht keine Ellipse.
     */
    public static function kilometresToUnits(float $kilometres): float
    {
        return round($kilometres * self::SCALE / self::KM_PER_DEGREE, 1);
    }

    /**
     * Die Entfernung zweier Punkte in Kilometern (Haversine).
     *
     * Hier steht ausdruecklich die echte Formel und nicht die Gerade von
     * oben: Ob ein Lead im Umkreis liegt, entscheidet ueber eine Zahl, die der
     * Kaeufer liest -- und spaeter darueber, was er zu sehen bekommt. Die
     * Karte darf ungenau sein, die Auswahl nicht.
     */
    public static function distanceInKilometres(
        float $latitude,
        float $longitude,
        float $otherLatitude,
        float $otherLongitude,
    ): float {
        $earthRadius = 6371.0;

        $deltaLat = deg2rad($otherLatitude - $latitude);
        $deltaLon = deg2rad($otherLongitude - $longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($latitude)) * cos(deg2rad($otherLatitude)) * sin($deltaLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

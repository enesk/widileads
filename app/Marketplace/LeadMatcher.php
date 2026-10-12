<?php

declare(strict_types=1);

namespace App\Marketplace;

use App\Constants\BuyerRegion;
use App\Funnel\Support\LooseValueComparison;
use App\Models\BuyerProfile;
use App\Support\Geo\PlzDirectory;

/**
 * Passt ein Lead zu den Kaufkriterien eines Kaeufers (FB-051)?
 *
 * **Reine Funktion.** Kein Datenbankzugriff, kein Zustand, keine Uhrzeit, keine
 * Zufallszahl -- dieselben Eingaben ergeben immer dieselbe Antwort. Die
 * Umkreispruefung liest das Postleitzahlenverzeichnis (PlzDirectory), und das
 * ist kein Bruch: Die Datei liegt im Quellcode, ist unveraenderlich und in
 * jeder Umgebung dieselbe -- eine Konstante in Dateiform, keine Betriebsdaten.
 *
 * Das ist die Kernanforderung des Tickets und kein Selbstzweck: Der Marktplatz
 * (FB-053) zeigt, was diese Funktion durchlaesst, und der Autokauf (FB-056) kauft, was
 * sie durchlaesst. Haette sie Seiteneffekte, wuerden Anzeige und Autokauf
 * auseinanderlaufen -- ein Kaeufer bekaeme Leads, die er im Marktplatz nie
 * gesehen hat, oder saehe welche, die der Autokauf ignoriert.
 *
 * Alle Kriterien gelten zusammen (UND). Ein leeres Kriterium schraenkt nicht
 * ein: Ein frisches Profil sieht alles. Die Alternative -- leer heisst nichts --
 * haette zur Folge, dass ein Kaeufer nach dem Anlegen einen leeren Marktplatz
 * sieht und den Fehler bei uns sucht.
 *
 * **Region und Praefixe gelten zusammen (UND).** `region`/`postal_code`/
 * `radius_km` kommen aus Wizard-Schritt 2, `postal_prefixes` pflegt der Kaeufer
 * in den Kaufkriterien von Hand. Sind beide gesetzt, muss ein Lead beide
 * passieren -- wie bei jedem anderen Kriterienpaar hier. Zusammenfassen liesse
 * sich das nicht: Ein ODER wuerde den engeren der beiden Filter stumm
 * aufheben, und der Kaeufer saehe nach dem Setzen eines Umkreises mehr
 * Anfragen als vorher.
 *
 * Fehlt einem Lead die Angabe, auf die ein gesetztes Kriterium zielt, passt er
 * nicht. Also: im Zweifel nicht anzeigen. Ein Lead ohne Postleitzahl gegen ein
 * Regionsprofil zu verkaufen waere ein Umsatz, den der Kaeufer reklamiert.
 *
 * `daily_limit`, `auto_buy` und `notify_email` wertet dieser Matcher bewusst
 * nicht aus -- sie beschreiben nicht, ob ein Lead passt, sondern was danach
 * geschieht. Das entscheidet FB-056.
 */
final class LeadMatcher
{
    public static function matches(MatchableLead $lead, BuyerProfile $profile): bool
    {
        return self::matchesFunnel($lead, $profile)
            && self::matchesPostalPrefix($lead, $profile)
            && self::matchesRadius($lead, $profile)
            && self::matchesMinimumScore($lead, $profile)
            && self::matchesAnswerFilters($lead, $profile);
    }

    /**
     * Stammt der Lead aus einem der gewaehlten Funnels?
     */
    private static function matchesFunnel(MatchableLead $lead, BuyerProfile $profile): bool
    {
        $funnelIds = $profile->funnelIds();

        if ($funnelIds === []) {
            return true;
        }

        return $lead->funnelId !== null && in_array($lead->funnelId, $funnelIds, true);
    }

    /**
     * Liegt die Postleitzahl in einer der gewaehlten Regionen?
     *
     * Verglichen wird der Anfang der Postleitzahl, nicht ihr Ganzes: "76" deckt
     * alles von 76001 bis 76999 ab.
     */
    private static function matchesPostalPrefix(MatchableLead $lead, BuyerProfile $profile): bool
    {
        $prefixes = $profile->postalPrefixes();

        if ($prefixes === []) {
            return true;
        }

        $postalCode = trim((string) $lead->postalCode);

        if ($postalCode === '') {
            return false;
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($postalCode, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Liegt die Postleitzahl im gewaehlten Umkreis?
     *
     * Greift nur, wenn der Kaeufer in Wizard-Schritt 2 "nur in meiner Region"
     * gewaehlt und dazu eine Postleitzahl und einen Umkreis gespeichert hat.
     * `nationwide` -- und ebenso ein halb gefuelltes Kriterium -- schraenkt
     * nicht ein.
     *
     * Ein Lead ohne Postleitzahl oder mit einer, die das Verzeichnis nicht
     * kennt, passt nicht: dieselbe Regel wie beim Praefixfilter, im Zweifel
     * nicht anzeigen.
     *
     * Kennt das Verzeichnis dagegen den **Mittelpunkt** nicht, schraenkt das
     * Kriterium nicht ein. Das ist die Gegenrichtung derselben Ueberlegung:
     * Ein unbekannter Mittelpunkt ist keine Aussage ueber einen einzelnen
     * Lead, sondern ein unbrauchbares Kriterium -- und ein Umkreis um eine
     * Grosskunden-Postleitzahl wuerde sonst den ganzen Marktplatz leeren,
     * ohne dass jemand den Grund saehe.
     */
    private static function matchesRadius(MatchableLead $lead, BuyerProfile $profile): bool
    {
        if ($profile->region !== BuyerRegion::RADIUS) {
            return true;
        }

        $centre = trim((string) $profile->postal_code);
        $radiusKm = $profile->radius_km;

        if ($centre === '' || $radiusKm === null || $radiusKm <= 0) {
            return true;
        }

        $directory = new PlzDirectory;

        if (! $directory->exists($centre)) {
            return true;
        }

        $postalCode = trim((string) $lead->postalCode);

        if ($postalCode === '') {
            return false;
        }

        $distanceKm = $directory->distanceTo($centre, $postalCode);

        return $distanceKm !== null && $distanceKm <= (float) $radiusKm;
    }

    /**
     * Erreicht der Lead die geforderte Mindestpunktzahl?
     *
     * Ein Lead ohne Punktzahl passt nicht, sobald eine Untergrenze gesetzt ist:
     * "keine Punktzahl" ist keine hohe.
     */
    private static function matchesMinimumScore(MatchableLead $lead, BuyerProfile $profile): bool
    {
        $minimum = $profile->min_score;

        if ($minimum === null) {
            return true;
        }

        return $lead->score !== null && $lead->score >= $minimum;
    }

    /**
     * Stimmen alle Antwortfilter?
     *
     * Je Feldschluessel genuegt eine Uebereinstimmung -- bei einer
     * Mehrfachauswahl reicht also eine angekreuzte Option aus der erlaubten
     * Liste. Ueber die Feldschluessel hinweg muessen dagegen alle Filter
     * zutreffen.
     *
     * Verglichen wird mit LooseValueComparison, derselben Regel, nach der
     * FB-012 seine Verzweigungen auswertet: Eine Regel "tierart ist hund" und
     * ein Kriterium "tierart in [hund]" muessen denselben Lead treffen.
     */
    private static function matchesAnswerFilters(MatchableLead $lead, BuyerProfile $profile): bool
    {
        foreach ($profile->answerFilters() as $fieldKey => $allowedValues) {
            if (! self::answerIsAllowed($lead->answersFor($fieldKey), $allowedValues)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $answers
     * @param  list<string>  $allowedValues
     */
    private static function answerIsAllowed(array $answers, array $allowedValues): bool
    {
        foreach ($answers as $answer) {
            foreach ($allowedValues as $allowed) {
                if (LooseValueComparison::equals($answer, $allowed)) {
                    return true;
                }
            }
        }

        return false;
    }
}

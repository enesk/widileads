<?php

declare(strict_types=1);

namespace App\Marketplace;

use App\Funnel\Support\LooseValueComparison;
use App\Models\BuyerProfile;

/**
 * Passt ein Lead zu den Kaufkriterien eines Kaeufers (FB-051)?
 *
 * **Reine Funktion.** Kein Datenbankzugriff, kein Zustand, keine Uhrzeit, keine
 * Zufallszahl -- dieselben Eingaben ergeben immer dieselbe Antwort. Das ist die
 * Kernanforderung des Tickets und kein Selbstzweck: Der Marktplatz (FB-053)
 * zeigt, was diese Funktion durchlaesst, und der Autokauf (FB-056) kauft, was
 * sie durchlaesst. Haette sie Seiteneffekte, wuerden Anzeige und Autokauf
 * auseinanderlaufen -- ein Kaeufer bekaeme Leads, die er im Marktplatz nie
 * gesehen hat, oder saehe welche, die der Autokauf ignoriert.
 *
 * Alle Kriterien gelten zusammen (UND). Ein leeres Kriterium schraenkt nicht
 * ein: Ein frisches Profil sieht alles. Die Alternative -- leer heisst nichts --
 * haette zur Folge, dass ein Kaeufer nach dem Anlegen einen leeren Marktplatz
 * sieht und den Fehler bei uns sucht.
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

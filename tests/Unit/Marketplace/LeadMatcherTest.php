<?php

declare(strict_types=1);

namespace Tests\Unit\Marketplace;

use App\Marketplace\LeadMatcher;
use App\Marketplace\MatchableLead;
use App\Models\BuyerProfile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FB-051: Der LeadMatcher ist Filterlogik mit Geldfolge.
 *
 * Ein zu weiter Filter verkauft dem Kaeufer Leads, die er nicht wollte -- und
 * er reklamiert sie. Ein zu enger laesst Umsatz liegen, ohne dass es jemandem
 * auffaellt. Deshalb ist hier jeder Filtertyp einzeln und in Kombination
 * belegt.
 *
 * Ohne Datenbank: das Profil wird instanziiert, nicht gespeichert. Genau das
 * ist die Zusicherung des Tickets -- der Matcher ist eine reine Funktion, und
 * ein Test, der eine Datenbank braeuchte, waere der Gegenbeweis.
 */
class LeadMatcherTest extends TestCase
{
    /**
     * Ein Lead, der ohne Einschraenkung durch jeden Filter geht. Die Faelle
     * unten aendern jeweils nur das, worum es ihnen geht.
     *
     * @param  array<string, mixed>  $overrides
     */
    private static function lead(array $overrides = []): MatchableLead
    {
        return MatchableLead::fromArray(array_merge([
            'funnel_id' => 7,
            'score' => 12,
            'postal_code' => '76133',
            'answers' => [
                'tierart' => 'hund',
                'alter_in_jahren' => 7,
                'vorerkrankungen' => ['huefte', 'augen'],
            ],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private static function profile(array $criteria = []): BuyerProfile
    {
        return new BuyerProfile(array_merge([
            'funnel_ids' => [],
            'postal_prefixes' => [],
            'answer_filters' => [],
            'min_score' => null,
        ], $criteria));
    }

    /**
     * Je Filtertyp: was er durchlaesst und was er abweist.
     *
     * @return array<string, array{MatchableLead, BuyerProfile, bool}>
     */
    public static function filterProvider(): array
    {
        return [
            // Ein leeres Kriterium schraenkt nicht ein -- ein frisches Profil
            // sieht alles. Sonst saehe ein neuer Kaeufer einen leeren
            // Marktplatz und suchte den Fehler bei uns.
            'ohne Kriterien passt jeder Lead' => [self::lead(), self::profile(), true],

            'Funnel: einer der gewaehlten' => [
                self::lead(), self::profile(['funnel_ids' => [7, 9]]), true,
            ],
            'Funnel: nicht gewaehlt' => [
                self::lead(), self::profile(['funnel_ids' => [9]]), false,
            ],
            'Funnel: Lead ohne Funnelbezug faellt bei gesetztem Filter raus' => [
                self::lead(['funnel_id' => null]), self::profile(['funnel_ids' => [7]]), false,
            ],

            // Der Praefix deckt die ganze Region ab: "76" trifft 76001 bis 76999.
            'PLZ: Praefix trifft' => [
                self::lead(), self::profile(['postal_prefixes' => ['68', '76']]), true,
            ],
            'PLZ: Praefix trifft nicht' => [
                self::lead(), self::profile(['postal_prefixes' => ['10', '20']]), false,
            ],
            'PLZ: vollstaendige Postleitzahl als Praefix' => [
                self::lead(), self::profile(['postal_prefixes' => ['76133']]), true,
            ],
            'PLZ: Lead ohne Postleitzahl faellt bei gesetztem Filter raus' => [
                self::lead(['postal_code' => null]), self::profile(['postal_prefixes' => ['76']]), false,
            ],

            'Antwort: erlaubter Wert' => [
                self::lead(), self::profile(['answer_filters' => ['tierart' => ['hund', 'katze']]]), true,
            ],
            'Antwort: nicht erlaubter Wert' => [
                self::lead(), self::profile(['answer_filters' => ['tierart' => ['katze']]]), false,
            ],
            'Antwort: Mehrfachauswahl, eine Uebereinstimmung genuegt' => [
                self::lead(), self::profile(['answer_filters' => ['vorerkrankungen' => ['augen']]]), true,
            ],
            'Antwort: Feldschluessel im Lead nicht beantwortet' => [
                self::lead(), self::profile(['answer_filters' => ['versicherung' => ['ja']]]), false,
            ],
            // Derselbe tolerante Vergleich wie in den Verzweigungsregeln aus
            // FB-012: ein Browser liefert Zahlen als String, ein Kaeufer tippt
            // Werte in beliebiger Schreibweise.
            'Antwort: Zahl als String trifft Zahl' => [
                self::lead(), self::profile(['answer_filters' => ['alter_in_jahren' => ['7']]]), true,
            ],
            'Antwort: Grossschreibung trifft Kleinschreibung' => [
                self::lead(), self::profile(['answer_filters' => ['tierart' => ['Hund']]]), true,
            ],
            // Ein Filter ohne erlaubte Werte wuerde jeden Lead ausschliessen --
            // gemeint ist er als "keine Einschraenkung", also wird er verworfen.
            'Antwort: leerer Filter schraenkt nicht ein' => [
                self::lead(), self::profile(['answer_filters' => ['tierart' => []]]), true,
            ],

            'Punktzahl: erreicht die Untergrenze' => [
                self::lead(), self::profile(['min_score' => 12]), true,
            ],
            'Punktzahl: unter der Untergrenze' => [
                self::lead(), self::profile(['min_score' => 13]), false,
            ],
            'Punktzahl: Lead ohne Punktzahl faellt bei gesetzter Untergrenze raus' => [
                self::lead(['score' => null]), self::profile(['min_score' => 1]), false,
            ],
            'Punktzahl: Lead ohne Punktzahl passt ohne Untergrenze' => [
                self::lead(['score' => null]), self::profile(), true,
            ],
            'Punktzahl: negative Untergrenze' => [
                self::lead(['score' => -3]), self::profile(['min_score' => -5]), true,
            ],

            // Nachgelagerte Felder gehoeren FB-056: sie beschreiben nicht, ob
            // ein Lead passt, sondern was danach geschieht.
            'Autokauf und Tageslimit aendern die Auswahl nicht' => [
                self::lead(), self::profile(['auto_buy' => false, 'daily_limit' => 0]), true,
            ],
        ];
    }

    #[DataProvider('filterProvider')]
    public function test_each_filter_type_admits_and_rejects_as_specified(
        MatchableLead $lead,
        BuyerProfile $profile,
        bool $expected,
    ): void {
        $this->assertSame($expected, LeadMatcher::matches($lead, $profile));
    }

    /**
     * Mehrere Kriterien gelten zusammen: ein einziges verfehltes reicht zum
     * Ausschluss. Genau hier entsteht der teure Fehler -- ein Matcher, der
     * "irgendeines trifft" auswertet, verkauft munter am Profil vorbei.
     *
     * @return array<string, array{MatchableLead, BuyerProfile, bool}>
     */
    public static function combinationProvider(): array
    {
        $strict = [
            'funnel_ids' => [7],
            'postal_prefixes' => ['76'],
            'answer_filters' => ['tierart' => ['hund'], 'vorerkrankungen' => ['huefte']],
            'min_score' => 10,
        ];

        return [
            'alle vier Kriterien treffen' => [
                self::lead(), self::profile($strict), true,
            ],
            'nur der Funnel passt nicht' => [
                self::lead(['funnel_id' => 8]), self::profile($strict), false,
            ],
            'nur die Region passt nicht' => [
                self::lead(['postal_code' => '10115']), self::profile($strict), false,
            ],
            'nur die Punktzahl passt nicht' => [
                self::lead(['score' => 9]), self::profile($strict), false,
            ],
            'nur einer von zwei Antwortfiltern passt' => [
                self::lead(['answers' => ['tierart' => 'hund', 'vorerkrankungen' => ['augen']]]),
                self::profile($strict),
                false,
            ],
            'beide Antwortfilter treffen ueber verschiedene Felder' => [
                self::lead(['answers' => ['tierart' => 'Hund', 'vorerkrankungen' => ['huefte', 'augen']]]),
                self::profile($strict),
                true,
            ],
        ];
    }

    #[DataProvider('combinationProvider')]
    public function test_all_criteria_must_hold_together(
        MatchableLead $lead,
        BuyerProfile $profile,
        bool $expected,
    ): void {
        $this->assertSame($expected, LeadMatcher::matches($lead, $profile));
    }

    /**
     * Die Zusicherung des Tickets, ausdruecklich geprueft: derselbe Aufruf gibt
     * immer dieselbe Antwort und hinterlaesst nichts. Marktplatz (FB-053) und
     * Autokauf (FB-056) duerfen sich darauf verlassen.
     */
    public function test_matching_is_free_of_side_effects(): void
    {
        $lead = self::lead();
        $profile = self::profile([
            'funnel_ids' => [7],
            'postal_prefixes' => ['76'],
            'answer_filters' => ['tierart' => ['hund']],
            'min_score' => 10,
        ]);

        $before = $profile->getAttributes();

        $results = [];

        for ($i = 0; $i < 3; $i++) {
            $results[] = LeadMatcher::matches($lead, $profile);
        }

        $this->assertSame([true, true, true], $results);

        // Weder Profil noch Lead wurden dabei veraendert. (Nicht ueber
        // isDirty() geprueft: ein nie gespeichertes Modell gilt ohnehin als
        // veraendert, das saegte am falschen Ast.)
        $this->assertSame($before, $profile->getAttributes());
        $this->assertSame(
            ['tierart' => 'hund', 'alter_in_jahren' => 7, 'vorerkrankungen' => ['huefte', 'augen']],
            $lead->answers,
        );
    }
}

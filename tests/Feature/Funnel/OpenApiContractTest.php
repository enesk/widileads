<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\ConditionOperator;
use App\Constants\FunnelFieldKey;
use App\Constants\FunnelProgressStyle;
use App\Constants\FunnelStatus;
use App\Constants\FunnelThemeFont;
use App\Constants\LeadState;
use App\Constants\QuestionType;
use App\Constants\TenantApiAbility;
use App\Constants\TenantType;
use App\Http\Controllers\ApiDocsController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * FB-030a: docs/openapi.yaml ist die Single Source of Truth der Management-API.
 * FB-030g: docs/openapi-public.yaml dasselbe fuer die oeffentliche Runtime-API.
 *
 * Dieser Test haelt Spezifikation und Anwendung zusammen. Er prueft drei Dinge:
 *
 *  1. Die Datei ist eine wohlgeformte OpenAPI-3.1-Spezifikation: alle
 *     Pflichtangaben sind da, jede Operation ist vollstaendig beschrieben und
 *     jeder $ref laesst sich aufloesen.
 *  2. Spezifikation und Routen laufen nicht auseinander: jede als
 *     "implemented" markierte Operation existiert als Route unter dem Praefix
 *     der Spezifikation -- und umgekehrt ist jede dort registrierte Route auch
 *     beschrieben. Geplante Endpunkte sind ausdruecklich erlaubt und werden
 *     nachvollziehbar uebersprungen.
 *  3. Kein Wertevorrat laeuft auseinander: jede Aufzaehlung der Spezifikation
 *     fuehrt exakt die Werte ihres Enums in app/Constants. Genau hier entsteht
 *     die Drift sonst unbemerkt -- ein umbenannter Enum-Wert bricht keinen Test,
 *     macht die Dokumentation aber falsch.
 *
 * Die ersten beiden Pruefungen laufen ueber BEIDE Spezifikationen. Solange sie
 * nur den Praefix der Management-API kannten, waren die vier oeffentlichen
 * Endpunkte aus FB-026 fuer sie unsichtbar: Der Test lief gruen, weil er sie
 * nicht sah -- eine Pruefung, die nichts prueft und trotzdem Sicherheit
 * suggeriert (FB-030g).
 *
 * Der Test braucht keine Datenbank -- er liest zwei Dateien und Laravels
 * Routenliste.
 */
class OpenApiContractTest extends TestCase
{
    /**
     * Die eingelesenen Spezifikationen, nach ihrem Namen abgelegt.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $specs = [];

    /**
     * HTTP-Methoden, die in einem Path Item eine Operation beschreiben. Alles
     * andere darin (etwa "parameters" oder "summary") ist keine Operation.
     *
     * @var list<string>
     */
    private const OPERATION_METHODS = ['get', 'put', 'post', 'patch', 'delete', 'options', 'head', 'trace'];

    /**
     * Die beiden Spezifikationen: ihr Pfad, der Routen-Praefix, unter dem sie
     * gilt, und ob ihre Operationen eine Berechtigung nennen muessen.
     *
     * Die oeffentliche Runtime-API steht bewusst in einer eigenen Datei: Sie
     * kennt kein Token, keinen Workspace und damit keine Berechtigungen. In
     * dieselbe Datei gezwungen muesste die Regel "jede Operation nennt ihre
     * Berechtigung aus TenantApiAbility" aufgeweicht werden -- und pruefte dann
     * fuer die Management-API auch nichts mehr.
     *
     * @var array<string, array{path: string, prefix: string, abilities: bool}>
     */
    private const SPECIFICATIONS = [
        'Management-API' => [
            'path' => ApiDocsController::SPEC_PATH,
            'prefix' => 'api/v1',
            'abilities' => true,
        ],
        'Oeffentliche Runtime-API' => [
            'path' => 'docs/openapi-public.yaml',
            'prefix' => 'api/public/v1',
            'abilities' => false,
        ],
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function specifications(): array
    {
        $cases = [];

        foreach (array_keys(self::SPECIFICATIONS) as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::SPECIFICATIONS as $name => $specification) {
            $path = base_path($specification['path']);

            $this->assertFileExists($path, "Die OpenAPI-Spezifikation der {$name} fehlt.");

            /** @var array<string, mixed> $spec */
            $spec = Yaml::parseFile($path);

            $this->specs[$name] = $spec;
        }
    }

    #[DataProvider('specifications')]
    public function test_specification_is_a_well_formed_openapi_31_document(string $name): void
    {
        $spec = $this->specs[$name];

        $this->assertIsString($spec['openapi'] ?? null);
        $this->assertStringStartsWith(
            '3.1',
            (string) $spec['openapi'],
            'Die Spezifikation muss OpenAPI 3.1 sein.',
        );

        foreach (['info', 'servers', 'security', 'paths', 'components'] as $section) {
            $this->assertArrayHasKey($section, $spec, "Der Abschnitt \"{$section}\" fehlt.");
        }

        $this->assertSame(
            '/'.self::SPECIFICATIONS[$name]['prefix'],
            $spec['servers'][0]['url'] ?? null,
            'Die Server-URL muss dem Routen-Praefix entsprechen, unter dem die Endpunkte registriert sind.',
        );

        $this->assertUnresolvedReferences($spec);
        $this->assertOperationsAreFullyDescribed($spec, self::SPECIFICATIONS[$name]['abilities']);
    }

    /**
     * Kontaktdaten gibt es nur in der Management-API -- die oeffentliche
     * Runtime-API liefert nie einen Lead aus, sie nimmt nur Antworten entgegen.
     */
    public function test_the_management_specification_separates_masked_and_full_contacts(): void
    {
        $this->assertLeadContactIsModelledAsTwoSchemas($this->specs['Management-API']);
    }

    #[DataProvider('specifications')]
    public function test_specification_and_registered_routes_do_not_drift_apart(string $name): void
    {
        $spec = $this->specs[$name];

        $documented = $this->documentedOperations($spec);
        $registered = $this->registeredApiRoutes(self::SPECIFICATIONS[$name]['prefix']);

        $planned = array_keys(array_filter($documented, fn (string $status): bool => $status === 'planned'));

        $this->assertNotEmpty(
            $documented,
            'Die Spezifikation beschreibt keine einzige Operation.',
        );

        // Ein Praefix ohne eine einzige registrierte Route hiesse, dass dieser
        // Durchgang nichts prueft -- genau der Zustand, den FB-030g behoben hat.
        $this->assertNotEmpty(
            $registered,
            sprintf(
                'Unter "%s" ist keine einzige Route registriert. Entweder stimmt der Praefix nicht, '
                .'oder dieser Durchgang prueft nichts.',
                self::SPECIFICATIONS[$name]['prefix'],
            ),
        );

        // Richtung 1: Was als umgesetzt gilt, muss es auch geben.
        foreach ($documented as $operation => $status) {
            if ($status !== 'implemented') {
                continue;
            }

            $this->assertContains(
                $operation,
                $registered,
                "Die Spezifikation fuehrt \"{$operation}\" als umgesetzt, es gibt aber keine solche Route. "
                .'Entweder fehlt die Route oder der Eintrag gehoert auf x-status: planned.',
            );
        }

        // Richtung 2: Was es gibt, muss beschrieben und als umgesetzt markiert
        // sein. Sonst entstuende ein Endpunkt, den die Spezifikation nicht kennt
        // -- und sie waere nicht mehr die Single Source of Truth.
        foreach ($registered as $operation) {
            $this->assertArrayHasKey(
                $operation,
                $documented,
                "Die Route \"{$operation}\" existiert, ist aber in {$name} nicht beschrieben.",
            );

            $this->assertSame(
                'implemented',
                $documented[$operation],
                "Die Route \"{$operation}\" existiert, steht in {$name} aber auf "
                .'x-status: planned. Nach der Umsetzung ist der Eintrag auf "implemented" zu setzen.',
            );
        }

        // Geplante Endpunkte sind erlaubt, solange ein Ticket offen ist. Sie
        // werden bewusst nicht geprueft -- der Hinweis haelt sichtbar, wie
        // viele es noch sind.
        $this->addToAssertionCount(1);

        if ($planned !== []) {
            $this->assertGreaterThan(
                0,
                count($planned),
                sprintf('%d geplante Operationen uebersprungen: %s', count($planned), implode(', ', $planned)),
            );
        }
    }

    public function test_documented_enumerations_match_the_ones_the_application_knows(): void
    {
        $spec = $this->specs['Management-API'];

        $abilities = $this->valuesOf(TenantApiAbility::class);

        /** @var array<string, string> $listed */
        $listed = $spec['x-abilities'] ?? [];
        $documented = array_keys($listed);
        sort($documented);

        $this->assertSame(
            $abilities,
            $documented,
            'Die Liste unter x-abilities und App\Constants\TenantApiAbility muessen deckungsgleich sein.',
        );

        // Jede Aufzaehlung der Spezifikation gegen ihr Enum. Der Wertevorrat ist
        // der Teil des Vertrags, der sich am leisesten aendert: ein
        // umbenannter Case faellt sonst erst dem Nutzer der Doku auf.
        $enums = [
            'Ability' => TenantApiAbility::class,
            'FunnelStatus' => FunnelStatus::class,
            'ConditionOperator' => ConditionOperator::class,
            'QuestionType' => QuestionType::class,
            'ReservedFieldKey' => FunnelFieldKey::class,
            'LeadState' => LeadState::class,
        ];

        $this->assertEnumerationsMatch($spec, $enums);

        $documented = $spec['components']['schemas']['Tenant']['properties']['type']['enum'] ?? null;

        $this->assertIsArray($documented);
        sort($documented);

        $this->assertSame(
            $this->valuesOf(TenantType::class),
            $documented,
            'Der Workspace-Typ muss genau die Werte aus App\Constants\TenantType fuehren.',
        );

        foreach ($this->eachOperation($spec) as $operation => $definition) {
            foreach ($definition['x-required-abilities'] as $ability) {
                $this->assertContains(
                    $ability,
                    $abilities,
                    "Die Operation \"{$operation}\" verlangt die unbekannte Berechtigung \"{$ability}\".",
                );
            }
        }
    }

    /**
     * Der Snapshot geht unveraendert nach aussen. Seine Wertevorraete stehen
     * damit ebenso im Vertrag wie die der Management-API -- ein umbenannter
     * Fragetyp macht sonst die Doku falsch, ohne dass etwas bricht (FB-030g).
     */
    public function test_the_public_specification_documents_the_snapshot_enumerations(): void
    {
        $this->assertEnumerationsMatch($this->specs['Oeffentliche Runtime-API'], [
            'QuestionType' => QuestionType::class,
            'ConditionOperator' => ConditionOperator::class,
            'ThemeFont' => FunnelThemeFont::class,
            'ProgressStyle' => FunnelProgressStyle::class,
        ]);
    }

    /**
     * Die oeffentlichen Endpunkte sind tokenlos. Das ist eine Aussage der
     * Spezifikation, keine vergessene Angabe -- deshalb wird sie geprueft.
     */
    public function test_the_public_specification_states_that_it_needs_no_token(): void
    {
        $spec = $this->specs['Oeffentliche Runtime-API'];

        $this->assertSame(
            [],
            $spec['security'],
            'Die oeffentliche Runtime-API kennt keine Anmeldung; security muss leer sein.',
        );

        $this->assertArrayNotHasKey(
            'securitySchemes',
            $spec['components'],
            'Ein Sicherheitsschema hier waere irrefuehrend: Diese Endpunkte tragen kein Token.',
        );

        foreach ($this->eachOperation($spec) as $operation => $definition) {
            $this->assertArrayNotHasKey(
                'x-required-abilities',
                $definition,
                "Die Operation \"{$operation}\" nennt eine Berechtigung, obwohl sie ohne Token erreichbar ist.",
            );

            $this->assertArrayNotHasKey(
                'security',
                $definition,
                "Die Operation \"{$operation}\" verlangt eine Anmeldung, die es hier nicht gibt.",
            );
        }
    }

    /**
     * Vergleicht die Aufzaehlungen einer Spezifikation mit ihren Enums.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, class-string<\BackedEnum>>  $enums
     */
    private function assertEnumerationsMatch(array $spec, array $enums): void
    {
        foreach ($enums as $schema => $enum) {
            $documented = $spec['components']['schemas'][$schema]['enum'] ?? null;

            $this->assertIsArray($documented, "Das Schema \"{$schema}\" fuehrt keine Aufzaehlung.");

            sort($documented);

            $this->assertSame(
                $this->valuesOf($enum),
                $documented,
                "Das Schema \"{$schema}\" und {$enum} muessen dieselben Werte fuehren.",
            );
        }
    }

    /**
     * Die Werte eines Enums, sortiert -- damit der Vergleich nicht an der
     * Reihenfolge haengt, in der jemand die Cases notiert hat.
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return list<string>
     */
    private function valuesOf(string $enum): array
    {
        /** @var list<string> $values */
        $values = array_column($enum::cases(), 'value');

        sort($values);

        return $values;
    }

    /**
     * Jeder $ref muss innerhalb des Dokuments auf etwas Vorhandenes zeigen.
     * Ein toter Verweis faellt sonst erst dem Leser der Doku auf.
     *
     * @param  array<string, mixed>  $spec
     */
    private function assertUnresolvedReferences(array $spec): void
    {
        $broken = [];

        $walk = function (mixed $node, string $trail) use (&$walk, &$broken, $spec): void {
            if (! is_array($node)) {
                return;
            }

            foreach ($node as $key => $value) {
                if ($key === '$ref' && is_string($value)) {
                    if (! $this->resolves($spec, $value)) {
                        $broken[] = $trail.' -> '.$value;
                    }

                    continue;
                }

                $walk($value, $trail.'/'.$key);
            }
        };

        $walk($spec, '');

        $this->assertSame([], $broken, 'Nicht aufloesbare Verweise: '.implode(', ', $broken));
    }

    /**
     * Jede Operation traegt die Angaben, ohne die weder der Contract-Test noch
     * ein Leser der Doku etwas anfangen kann.
     *
     * @param  array<string, mixed>  $spec
     */
    private function assertOperationsAreFullyDescribed(array $spec, bool $requiresAbilities): void
    {
        $operationIds = [];

        $required = ['operationId', 'summary', 'x-status', 'x-ticket', 'responses'];

        if ($requiresAbilities) {
            $required[] = 'x-required-abilities';
        }

        foreach ($this->eachOperation($spec) as $operation => $definition) {
            foreach ($required as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $definition,
                    "Der Operation \"{$operation}\" fehlt die Angabe \"{$field}\".",
                );
            }

            $this->assertContains(
                $definition['x-status'],
                ['implemented', 'planned'],
                "Die Operation \"{$operation}\" hat einen unbekannten x-status.",
            );

            if ($requiresAbilities) {
                $this->assertIsArray(
                    $definition['x-required-abilities'],
                    "x-required-abilities muss bei \"{$operation}\" eine Liste sein.",
                );
            }

            $this->assertNotEmpty(
                $definition['responses'],
                "Die Operation \"{$operation}\" beschreibt keine einzige Antwort.",
            );

            $operationIds[] = $definition['operationId'];
        }

        $this->assertSame(
            array_unique($operationIds),
            $operationIds,
            'Jede operationId darf nur einmal vorkommen.',
        );
    }

    /**
     * Architekturleitsatz 5: Kontaktdaten werden serverseitig maskiert. Die
     * Spezifikation bildet das als zwei getrennte Schemas ab -- nicht als ein
     * Schema mit optionalen Kontaktfeldern. Waere es eines, koennte FB-030d
     * spaeter unbemerkt Klartext ausliefern, wo Maskierung gemeint war.
     *
     * @param  array<string, mixed>  $spec
     */
    private function assertLeadContactIsModelledAsTwoSchemas(array $spec): void
    {
        $schemas = $spec['components']['schemas'];

        foreach (['LeadMasked', 'LeadFull', 'LeadContactMasked', 'LeadContactFull', 'Lead'] as $schema) {
            $this->assertArrayHasKey($schema, $schemas, "Das Schema \"{$schema}\" fehlt.");
        }

        $this->assertSame(
            [
                ['$ref' => '#/components/schemas/LeadMasked'],
                ['$ref' => '#/components/schemas/LeadFull'],
            ],
            $schemas['Lead']['oneOf'] ?? null,
            'Lead muss genau eine der beiden Auspraegungen sein.',
        );

        $this->assertSame(
            'contact_visibility',
            $schemas['Lead']['discriminator']['propertyName'] ?? null,
            'Die Auspraegung muss an contact_visibility erkennbar sein, nicht am Vorhandensein von Feldern.',
        );

        // Beide Auspraegungen verlangen dieselben Kontaktfelder; nur ihr Format
        // unterscheidet sich. Ein Feld darf in keiner der beiden optional sein.
        $fields = ['first_name', 'last_name', 'email', 'phone', 'postal_code'];

        foreach (['LeadContactMasked', 'LeadContactFull'] as $schema) {
            $this->assertSame(
                $fields,
                $schemas[$schema]['required'] ?? null,
                "Alle Kontaktfelder muessen in \"{$schema}\" verpflichtend sein.",
            );
        }

        // Die maskierte Auspraegung schreibt ihre Kuerzung als Muster fest --
        // damit ist ein unmaskierter Wert an dieser Stelle vertragswidrig.
        foreach (['email', 'phone', 'postal_code'] as $field) {
            $this->assertArrayHasKey(
                'pattern',
                $schemas['LeadContactMasked']['properties'][$field],
                "Das maskierte Feld \"{$field}\" muss sein Format als Muster festlegen.",
            );
        }
    }

    /**
     * Alle Operationen der Spezifikation als "METHODE /pfad" auf ihren x-status.
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, string>
     */
    private function documentedOperations(array $spec): array
    {
        $operations = [];

        foreach ($this->eachOperation($spec) as $operation => $definition) {
            $operations[$operation] = (string) $definition['x-status'];
        }

        return $operations;
    }

    /**
     * Alle tatsaechlich registrierten Routen unter dem Praefix als
     * "METHODE /pfad", geschrieben wie in der Spezifikation: ohne Praefix, mit
     * fuehrendem Schraegstrich.
     *
     * @return list<string>
     */
    private function registeredApiRoutes(string $prefix): array
    {
        $routes = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, $prefix.'/')) {
                continue;
            }

            $path = substr($uri, strlen($prefix));

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }

                $routes[] = strtolower($method).' '.$path;
            }
        }

        sort($routes);

        return array_values(array_unique($routes));
    }

    /**
     * Laeuft ueber alle Operationen aus "paths" und "webhooks".
     *
     * @param  array<string, mixed>  $spec
     * @return iterable<string, array<string, mixed>>
     */
    private function eachOperation(array $spec): iterable
    {
        /** @var array<string, array<string, mixed>> $paths */
        $paths = $spec['paths'] ?? [];

        foreach ($paths as $path => $item) {
            foreach (self::OPERATION_METHODS as $method) {
                if (isset($item[$method])) {
                    yield $method.' '.$path => $item[$method];
                }
            }
        }

        /** @var array<string, array<string, mixed>> $webhooks */
        $webhooks = $spec['webhooks'] ?? [];

        foreach ($webhooks as $event => $item) {
            foreach (self::OPERATION_METHODS as $method) {
                if (isset($item[$method])) {
                    yield 'webhook '.$event => $item[$method];
                }
            }
        }
    }

    /**
     * Zeigt ein interner Verweis auf einen vorhandenen Knoten?
     *
     * @param  array<string, mixed>  $spec
     */
    private function resolves(array $spec, string $reference): bool
    {
        if (! str_starts_with($reference, '#/')) {
            return false;
        }

        $node = $spec;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return false;
            }

            $node = $node[$segment];
        }

        return true;
    }
}

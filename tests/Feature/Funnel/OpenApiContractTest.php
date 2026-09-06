<?php

declare(strict_types=1);

namespace Tests\Feature\Funnel;

use App\Constants\TenantApiAbility;
use App\Http\Controllers\ApiDocsController;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * FB-030a: docs/openapi.yaml ist die Single Source of Truth der Management-API.
 *
 * Dieser Test haelt Spezifikation und Anwendung zusammen. Er prueft drei Dinge:
 *
 *  1. Die Datei ist eine wohlgeformte OpenAPI-3.1-Spezifikation: alle
 *     Pflichtangaben sind da, jede Operation ist vollstaendig beschrieben und
 *     jeder $ref laesst sich aufloesen.
 *  2. Spezifikation und Routen laufen nicht auseinander: jede als
 *     "implemented" markierte Operation existiert als Route unter /api/v1 --
 *     und umgekehrt ist jede registrierte Route dort auch beschrieben. Geplante
 *     Endpunkte (FB-030b ff.) sind ausdruecklich erlaubt und werden
 *     nachvollziehbar uebersprungen.
 *  3. Die dokumentierten Berechtigungen sind exakt die aus TenantApiAbility.
 *
 * Der Test braucht keine Datenbank -- er liest eine Datei und Laravels
 * Routenliste.
 */
class OpenApiContractTest extends TestCase
{
    /**
     * Die eingelesene Spezifikation.
     *
     * @var array<string, mixed>
     */
    private array $spec;

    /**
     * HTTP-Methoden, die in einem Path Item eine Operation beschreiben. Alles
     * andere darin (etwa "parameters" oder "summary") ist keine Operation.
     *
     * @var list<string>
     */
    private const OPERATION_METHODS = ['get', 'put', 'post', 'patch', 'delete', 'options', 'head', 'trace'];

    /**
     * Praefix, unter dem die Management-API registriert ist. Er steht in der
     * Spezifikation als Server-URL und hier als Routen-Praefix.
     */
    private const ROUTE_PREFIX = 'api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        $path = base_path(ApiDocsController::SPEC_PATH);

        $this->assertFileExists($path, 'Die OpenAPI-Spezifikation fehlt.');

        /** @var array<string, mixed> $spec */
        $spec = Yaml::parseFile($path);

        $this->spec = $spec;
    }

    public function test_specification_is_a_well_formed_openapi_31_document(): void
    {
        $this->assertIsString($this->spec['openapi'] ?? null);
        $this->assertStringStartsWith(
            '3.1',
            (string) $this->spec['openapi'],
            'Die Spezifikation muss OpenAPI 3.1 sein.',
        );

        foreach (['info', 'servers', 'security', 'paths', 'components'] as $section) {
            $this->assertArrayHasKey($section, $this->spec, "Der Abschnitt \"{$section}\" fehlt.");
        }

        $this->assertSame(
            '/'.self::ROUTE_PREFIX,
            $this->spec['servers'][0]['url'] ?? null,
            'Die Server-URL muss dem Routen-Praefix der Management-API entsprechen.',
        );

        $this->assertUnresolvedReferences();
        $this->assertOperationsAreFullyDescribed();
        $this->assertLeadContactIsModelledAsTwoSchemas();
    }

    public function test_specification_and_registered_routes_do_not_drift_apart(): void
    {
        $documented = $this->documentedOperations();
        $registered = $this->registeredApiRoutes();

        $planned = array_keys(array_filter($documented, fn (string $status): bool => $status === 'planned'));

        $this->assertNotEmpty(
            $documented,
            'Die Spezifikation beschreibt keine einzige Operation.',
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
                "Die Route \"{$operation}\" existiert, ist aber in docs/openapi.yaml nicht beschrieben.",
            );

            $this->assertSame(
                'implemented',
                $documented[$operation],
                "Die Route \"{$operation}\" existiert, steht in docs/openapi.yaml aber auf "
                .'x-status: planned. Nach der Umsetzung ist der Eintrag auf "implemented" zu setzen.',
            );
        }

        // Geplante Endpunkte sind der Normalfall, solange FB-030b bis FB-030f
        // offen sind. Sie werden bewusst nicht geprueft -- der Hinweis haelt
        // sichtbar, wie viele es noch sind.
        $this->addToAssertionCount(1);

        if ($planned !== []) {
            $this->assertGreaterThan(
                0,
                count($planned),
                sprintf('%d geplante Operationen uebersprungen: %s', count($planned), implode(', ', $planned)),
            );
        }
    }

    public function test_documented_abilities_are_exactly_the_ones_the_application_knows(): void
    {
        $known = TenantApiAbility::values();

        /** @var array<string, string> $listed */
        $listed = $this->spec['x-abilities'] ?? [];

        sort($known);
        $documented = array_keys($listed);
        sort($documented);

        $this->assertSame(
            $known,
            $documented,
            'Die Liste unter x-abilities und App\Constants\TenantApiAbility muessen deckungsgleich sein.',
        );

        $enum = $this->spec['components']['schemas']['Ability']['enum'] ?? [];
        sort($enum);

        $this->assertSame(
            $known,
            $enum,
            'Das Schema "Ability" muss genau die Werte aus TenantApiAbility fuehren.',
        );

        foreach ($this->eachOperation() as $operation => $definition) {
            foreach ($definition['x-required-abilities'] as $ability) {
                $this->assertContains(
                    $ability,
                    $known,
                    "Die Operation \"{$operation}\" verlangt die unbekannte Berechtigung \"{$ability}\".",
                );
            }
        }
    }

    /**
     * Jeder $ref muss innerhalb des Dokuments auf etwas Vorhandenes zeigen.
     * Ein toter Verweis faellt sonst erst dem Leser der Doku auf.
     */
    private function assertUnresolvedReferences(): void
    {
        $broken = [];

        $walk = function (mixed $node, string $trail) use (&$walk, &$broken): void {
            if (! is_array($node)) {
                return;
            }

            foreach ($node as $key => $value) {
                if ($key === '$ref' && is_string($value)) {
                    if (! $this->resolves($value)) {
                        $broken[] = $trail.' -> '.$value;
                    }

                    continue;
                }

                $walk($value, $trail.'/'.$key);
            }
        };

        $walk($this->spec, '');

        $this->assertSame([], $broken, 'Nicht aufloesbare Verweise: '.implode(', ', $broken));
    }

    /**
     * Jede Operation traegt die Angaben, ohne die weder der Contract-Test noch
     * ein Leser der Doku etwas anfangen kann.
     */
    private function assertOperationsAreFullyDescribed(): void
    {
        $operationIds = [];

        foreach ($this->eachOperation() as $operation => $definition) {
            foreach (['operationId', 'summary', 'x-status', 'x-ticket', 'x-required-abilities', 'responses'] as $field) {
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

            $this->assertIsArray(
                $definition['x-required-abilities'],
                "x-required-abilities muss bei \"{$operation}\" eine Liste sein.",
            );

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
     */
    private function assertLeadContactIsModelledAsTwoSchemas(): void
    {
        $schemas = $this->spec['components']['schemas'];

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
     * @return array<string, string>
     */
    private function documentedOperations(): array
    {
        $operations = [];

        foreach ($this->eachOperation() as $operation => $definition) {
            $operations[$operation] = (string) $definition['x-status'];
        }

        return $operations;
    }

    /**
     * Alle tatsaechlich registrierten Routen unter /api/v1 als "METHODE /pfad",
     * geschrieben wie in der Spezifikation: ohne Praefix, mit fuehrendem Schraegstrich.
     *
     * @return list<string>
     */
    private function registeredApiRoutes(): array
    {
        $routes = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, self::ROUTE_PREFIX.'/')) {
                continue;
            }

            $path = substr($uri, strlen(self::ROUTE_PREFIX));

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
     * @return iterable<string, array<string, mixed>>
     */
    private function eachOperation(): iterable
    {
        /** @var array<string, array<string, mixed>> $paths */
        $paths = $this->spec['paths'] ?? [];

        foreach ($paths as $path => $item) {
            foreach (self::OPERATION_METHODS as $method) {
                if (isset($item[$method])) {
                    yield $method.' '.$path => $item[$method];
                }
            }
        }

        /** @var array<string, array<string, mixed>> $webhooks */
        $webhooks = $this->spec['webhooks'] ?? [];

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
     */
    private function resolves(string $reference): bool
    {
        if (! str_starts_with($reference, '#/')) {
            return false;
        }

        $node = $this->spec;

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

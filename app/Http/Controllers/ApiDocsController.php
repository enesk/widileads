<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Oeffentliche Dokumentation der beiden APIs (FB-030a, FB-030h).
 *
 * Die Dokumentation wird nicht erzeugt, sondern angezeigt: Die beiden
 * Spezifikationen sind die Single Source of Truth, diese Seite bindet sie nur
 * in einen Betrachter ein. Deshalb gibt es hier keinen Generierungsschritt und
 * keine zweite Fassung, die auseinanderlaufen koennte.
 *
 * Es sind zwei, weil es zwei APIs mit zwei Zielgruppen gibt: die Management-API
 * unter /api/v1 fuer das Backend eines Betreibers und die Runtime-API unter
 * /api/public/v1 fuer ein fremdes Frontend. Sie liegen in getrennten Dateien --
 * die Begruendung steht im Kopf von docs/openapi-public.yaml.
 *
 * Beide haengen am selben Schalter funnel.api.docs_enabled: Wer die
 * Dokumentation abschaltet, meint beide.
 */
class ApiDocsController extends Controller
{
    /**
     * Pfad der Management-Spezifikation, relativ zum Projektverzeichnis.
     */
    public const SPEC_PATH = 'docs/openapi.yaml';

    /**
     * Pfad der Spezifikation der oeffentlichen Runtime-API.
     */
    public const PUBLIC_SPEC_PATH = 'docs/openapi-public.yaml';

    /**
     * Die Betrachterseite der Management-API.
     */
    public function index(): View
    {
        return $this->page('management');
    }

    /**
     * Liefert docs/openapi.yaml unveraendert aus.
     */
    public function spec(): BinaryFileResponse|Response
    {
        return $this->file(self::SPEC_PATH);
    }

    /**
     * Die Betrachterseite der oeffentlichen Runtime-API.
     *
     * Sie gehoert nach aussen, nicht in ein internes Wiki: FB-026 richtet sich
     * ausdruecklich an fremde Frontends, und die brauchen die Beschreibung, um
     * ueberhaupt etwas bauen zu koennen.
     */
    public function publicIndex(): View
    {
        return $this->page('public');
    }

    /**
     * Liefert docs/openapi-public.yaml unveraendert aus.
     */
    public function publicSpec(): BinaryFileResponse|Response
    {
        return $this->file(self::PUBLIC_SPEC_PATH);
    }

    /**
     * Die Seite laedt die Spezifikation ueber ihre eigene Route nach, statt sie
     * einzubetten -- so bleibt die YAML auch einzeln abrufbar, etwa fuer
     * Codegeneratoren.
     */
    private function page(string $current): View
    {
        return view('pages.api-docs', [
            'specUrl' => route($current === 'public' ? 'api-docs.public.spec' : 'api-docs.spec'),
            'viewerUrl' => (string) config('funnel.api.docs_viewer_url'),
            'current' => $current,
            // Beide Spezifikationen stehen auf jeder der beiden Seiten zur
            // Auswahl. Ohne diese Liste fuehrte nichts von der einen zur
            // anderen, und die oeffentliche waere nur zu finden, wer von ihr
            // schon weiss.
            'specifications' => [
                [
                    'key' => 'management',
                    'label' => __('funnel.api_docs.management'),
                    'url' => route('api-docs'),
                ],
                [
                    'key' => 'public',
                    'label' => __('funnel.api_docs.public'),
                    'url' => route('api-docs.public'),
                ],
            ],
        ]);
    }

    private function file(string $path): BinaryFileResponse|Response
    {
        $absolute = base_path($path);

        if (! is_file($absolute)) {
            return response(__('funnel.api_docs.spec_missing'), 404);
        }

        return response()->file($absolute, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}

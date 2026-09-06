<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Oeffentliche Dokumentation der Management-API (FB-030a).
 *
 * Die Dokumentation wird nicht erzeugt, sondern angezeigt: docs/openapi.yaml
 * ist die Single Source of Truth, diese Seite bindet sie nur in einen
 * Betrachter ein. Deshalb gibt es hier auch keinen Generierungsschritt und
 * keine zweite Fassung der Spezifikation, die auseinanderlaufen koennte.
 */
class ApiDocsController extends Controller
{
    /**
     * Pfad der Spezifikation, relativ zum Projektverzeichnis.
     */
    public const SPEC_PATH = 'docs/openapi.yaml';

    /**
     * Die Betrachterseite. Sie laedt die Spezifikation ueber die Route
     * api-docs.spec nach, statt sie einzubetten -- so bleibt die YAML auch
     * einzeln abrufbar, etwa fuer Codegeneratoren.
     */
    public function index(): View
    {
        return view('pages.api-docs', [
            'specUrl' => route('api-docs.spec'),
            'viewerUrl' => (string) config('funnel.api.docs_viewer_url'),
        ]);
    }

    /**
     * Liefert docs/openapi.yaml unveraendert aus.
     */
    public function spec(): BinaryFileResponse|Response
    {
        $path = base_path(self::SPEC_PATH);

        if (! is_file($path)) {
            return response(__('funnel.api_docs.spec_missing'), 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}

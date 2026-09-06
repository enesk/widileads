<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnel;

use App\Actions\PublishFunnel;
use App\Funnel\Snapshots\FunnelSnapshot;
use App\Funnel\Snapshots\SnapshotBuilder;
use App\Http\Controllers\Controller;
use App\Models\Funnel;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vorschau eines Funnels ueber einen signierten, befristeten Link (FB-018).
 *
 * Gezeigt wird der **Entwurfsstand**, nicht die veroeffentlichte Fassung --
 * sonst waere die Vorschau wertlos: Sie soll ja gerade vor dem ersten Publish
 * zeigen, was entstanden ist. Dafuer baut der SnapshotBuilder einen
 * Vorschau-Snapshot aus den Live-Tabellen. Der Grundsatz "die oeffentliche
 * Auslieferung liest nur Snapshots" bleibt damit unangetastet: Auch die
 * Vorschau liest einen Snapshot, er wird nur nicht gespeichert.
 *
 * Zusaetzlich zeigt die Seite, was einer Veroeffentlichung noch im Weg steht --
 * dieselbe Pruefung, die PublishFunnel anwendet.
 */
class FunnelPreviewController extends Controller
{
    public function __construct(
        private readonly SnapshotBuilder $snapshotBuilder,
        private readonly PublishFunnel $publishFunnel,
    ) {}

    public function __invoke(string $token): View
    {
        // Ohne Mandantenfilter: Der signierte Link ist die Zugangskontrolle,
        // ein angemeldeter Nutzer ist nicht vorausgesetzt.
        $funnel = Funnel::query()
            ->withoutGlobalScopes()
            ->where('public_token', $token)
            ->first();

        if ($funnel === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $structure = $this->snapshotBuilder->build($funnel);

        return view('funnel.preview', [
            'funnel' => $funnel,
            'snapshot' => FunnelSnapshot::fromArray($structure),
            'blockers' => $this->publishFunnel->reasonsAgainstPublishing($structure),
        ]);
    }
}

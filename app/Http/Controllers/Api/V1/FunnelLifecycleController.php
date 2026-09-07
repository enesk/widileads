<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ArchiveFunnel;
use App\Actions\DuplicateFunnel;
use App\Actions\PublishFunnel;
use App\Exceptions\FunnelNotPublishableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DuplicateFunnelRequest;
use App\Http\Requests\Api\V1\PublishFunnelRequest;
use App\Http\Resources\Api\V1\FunnelResource;
use App\Http\Resources\Api\V1\FunnelVersionResource;
use App\Http\Responses\ProblemResponse;
use App\Models\Funnel;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lebenszyklus eines Funnels ueber die API (FB-030c).
 *
 * Der Controller ruft nur die vorhandenen Actions -- Veroeffentlichen (FB-014),
 * Duplizieren und Archivieren (FB-018). Eine zweite Umsetzung derselben
 * Vorgaenge waere der sichere Weg, dass API und Oberflaeche mit der Zeit
 * unterschiedliche Ergebnisse liefern.
 */
class FunnelLifecycleController extends Controller
{
    public function publish(PublishFunnelRequest $request, Funnel $funnel): JsonResponse
    {
        try {
            $version = app(PublishFunnel::class)->handle(
                $funnel,
                null,
                $request->string('note')->value() ?: null,
            );
        } catch (FunnelNotPublishableException $exception) {
            // Die Gruende sind Fachaussagen, keine Feldfehler -- sie gehoeren in
            // den Rumpf, damit der Aufrufer sie anzeigen kann.
            return ProblemResponse::make(
                ProblemResponse::TYPE_VALIDATION_FAILED,
                __('api.problems.validation_failed.title'),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->getMessage(),
                ['funnel' => $exception->reasons],
            );
        }

        return FunnelVersionResource::make($version)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function duplicate(DuplicateFunnelRequest $request, Funnel $funnel): JsonResponse
    {
        $copy = app(DuplicateFunnel::class)->handle($funnel);

        // Name und Slug bestimmt die Action selbst, damit dieselbe Struktur
        // beliebig oft kopiert werden kann. Wer eigene Werte mitgibt, bekommt
        // sie danach gesetzt -- die Eindeutigkeit prueft der Form Request.
        $wanted = array_filter($request->safe()->only(['name', 'slug']));

        if ($wanted !== []) {
            $copy->update($wanted);
        }

        return FunnelResource::make($copy->fresh() ?? $copy)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function archive(Funnel $funnel): FunnelResource
    {
        return FunnelResource::make(app(ArchiveFunnel::class)->handle($funnel));
    }
}

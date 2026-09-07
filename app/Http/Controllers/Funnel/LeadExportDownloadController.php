<?php

declare(strict_types=1);

namespace App\Http\Controllers\Funnel;

use App\Http\Controllers\Controller;
use App\Models\LeadExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Herunterladen eines fertigen Lead-Exports (FB-073).
 *
 * Der Link ist signiert und befristet -- aber nicht die einzige Kontrolle: Wer
 * ihn weiterreicht, gibt Kontaktdaten weiter, deshalb wird zusaetzlich geprueft,
 * ob der Abrufende zu dem Workspace gehoert, dem der Export gehoert.
 */
class LeadExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $export): StreamedResponse
    {
        $found = LeadExport::query()->where('uuid', $export)->first();

        if ($found === null || ! $found->isDownloadable()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();

        if (! $user instanceof User || ! $user->tenants()->whereKey($found->tenant_id)->exists()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return Storage::disk((string) $found->disk)->download((string) $found->path, $found->fileName());
    }
}

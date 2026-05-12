<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\AdminQueuedExport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadAdminQueuedExportController
{
    public function __invoke(Request $request, AdminQueuedExport $export): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($export->user_id !== $user->id) {
            abort(403);
        }

        if ($export->expires_at->isPast()) {
            abort(410, 'Export expiré.');
        }

        if (!Storage::disk($export->disk)->exists($export->path)) {
            abort(404);
        }

        return Storage::disk($export->disk)->download($export->path, $export->download_name, [
            'Content-Type' => $export->mime_type,
        ]);
    }
}

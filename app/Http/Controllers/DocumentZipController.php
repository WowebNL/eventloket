<?php

namespace App\Http\Controllers;

use App\Models\Zaak;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentZipController extends Controller
{
    public function __invoke(Request $request, Zaak $zaak, string $token): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $meta = Cache::get("document_zip.{$token}");

        abort_if($meta === null, 404);
        abort_unless(($meta['zaak_id'] ?? '') === $zaak->id, 403);
        abort_unless((int) ($meta['user_id'] ?? 0) === auth()->id(), 403);

        $path = (string) ($meta['path'] ?? '');
        abort_unless(Storage::exists($path), 404);

        activity('document')
            ->event('multi_download')
            ->causedBy(auth()->user())
            ->performedOn($zaak)
            ->withProperties([
                'token' => $token,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log(__('activity/event.multi_download_serve'));

        $fileName = "documenten-{$zaak->public_id}.zip";

        return Storage::download($path, $fileName, [
            'Content-Type' => 'application/zip',
        ]);
    }
}

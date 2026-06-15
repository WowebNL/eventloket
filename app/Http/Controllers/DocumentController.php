<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Zaak;
use App\Support\Uploads\DocumentUploadType;
use App\ValueObjects\ZGW\Informatieobject;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Woweb\Openzaak\Openzaak;

class DocumentController extends Controller
{
    public function __invoke(DocumentRequest $request, Zaak $zaak, string $documentuuid, ?string $type = 'view')
    {
        $document = $zaak->documenten->where('uuid', $documentuuid)->firstOrFail();

        $validated = $request->validated();
        if (isset($validated['version']) && $validated['version'] != $document->versie) {
            // get the specified version
            $document = new Informatieobject(...(new Openzaak)->get($document->url.'?versie='.$validated['version'])->toArray());
        }
        $disposition = $type === 'download' ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE;

        $raw = (new Openzaak)->getRaw($document->inhoud);

        // Older / externally created documents may have an empty or missing MIME
        // type, in which case we detect it from the actual content bytes.
        $mime = $document->formaat !== '' ? $document->formaat : null;
        if ($mime === null && $raw !== '') {
            $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $raw) ?: null;
        }
        $mime ??= 'application/octet-stream';

        // Existing documents may have been stored without a file extension in
        // their bestandsnaam, which makes them impossible to open after download.
        // We reconstruct a usable filename from the resolved MIME type.
        $fileName = DocumentUploadType::ensureFileNameHasExtension($document->bestandsnaam, $mime);

        return response($raw)->withHeaders([
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $fileName),
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Content-Type' => $mime,
        ]);
    }
}

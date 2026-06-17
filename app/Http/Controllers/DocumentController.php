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

        $event = $type === 'download' ? 'download' : 'view';

        activity('document')
            ->event($event)
            ->causedBy(auth()->user())
            ->performedOn($zaak)
            ->withProperties([
                'document_uuid' => $documentuuid,
                'filename' => $document->bestandsnaam,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ])
            ->log(__('activity/event.'.$event));

        $dispositionType = $type === 'download' ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE;

        // Only trust the stored MIME type when it is on the upload allowlist;
        // anything else is served as a generic binary so disallowed content can
        // never be rendered under a permissive Content-Type.
        $mimeIsTrusted = DocumentUploadType::storedMimeTypeIsAllowed($document->formaat);
        $contentType = $mimeIsTrusted ? $document->formaat : 'application/octet-stream';

        // Existing documents may have been stored without a file extension in
        // their bestandsnaam, which makes them impossible to open after download.
        // When we have a trusted MIME type we reconstruct a usable filename from it.
        $fileName = $mimeIsTrusted
            ? DocumentUploadType::ensureFileNameHasExtension($document->bestandsnaam, $document->formaat)
            : $document->bestandsnaam;

        return response((new Openzaak)->getRaw($document->inhoud))->withHeaders([
            'Content-Disposition' => HeaderUtils::makeDisposition($dispositionType, $fileName),
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Content-Type' => $contentType,
        ]);
    }
}

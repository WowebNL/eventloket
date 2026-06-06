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

        $contentType = DocumentUploadType::storedMimeTypeIsAllowed($document->formaat)
            ? $document->formaat
            : 'application/octet-stream';

        return response((new Openzaak)->getRaw($document->inhoud))->withHeaders([
            'Content-Disposition' => HeaderUtils::makeDisposition($dispositionType, $document->bestandsnaam),
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Content-Type' => $contentType,
        ]);
    }
}

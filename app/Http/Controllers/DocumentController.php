<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Zaak;
use App\Support\Uploads\DocumentUploadType;
use App\Services\Zgw\ZgwResource;
use App\ValueObjects\ZGW\Informatieobject;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class DocumentController extends Controller
{
    public function __invoke(DocumentRequest $request, Zaak $zaak, string $documentuuid, ?string $type = 'view')
    {
        $document = $zaak->documenten->where('uuid', $documentuuid)->firstOrFail();

        $validated = $request->validated();
        if (isset($validated['version']) && $validated['version'] != $document->versie) {
            // get the specified version
            $document = new Informatieobject(...ZgwResource::byUrl($zaak->zgwConnectionName(), $document->url.'?versie='.$validated['version']));
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

        return response(ZgwResource::downloadByUrl($zaak->zgwConnectionName(), $document->inhoud))->withHeaders([
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $dispositionType,
                $fileName,
                $this->asciiFileNameFallback($fileName),
            ),
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Content-Type' => $contentType,
        ]);
    }

    /**
     * Builds an ASCII-only fallback filename for the Content-Disposition header.
     *
     * HTTP headers may only carry ASCII, so a bestandsnaam containing characters
     * such as "ö" makes {@see HeaderUtils::makeDisposition()} throw unless an ASCII
     * fallback is supplied. The full UTF-8 name is still sent via the RFC 6266
     * "filename*" field, so modern browsers keep the original characters; this
     * fallback is only used by legacy clients.
     */
    private function asciiFileNameFallback(string $fileName): string
    {
        // Transliterate accented characters (ö -> o), then strip anything Symfony
        // rejects in the fallback: non-printable ASCII, "%", and path separators.
        $fallback = Str::ascii($fileName);
        $fallback = (string) preg_replace('/[^\x20-\x7e]/', '', $fallback);
        $fallback = str_replace(['%', '/', '\\'], '', $fallback);
        $fallback = trim($fallback);

        return $fallback !== '' ? $fallback : 'document';
    }
}

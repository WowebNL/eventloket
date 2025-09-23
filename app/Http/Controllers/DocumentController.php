<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;
use Woweb\Openzaak\Openzaak;

class DocumentController extends Controller
{
    public function __invoke(DocumentRequest $request, Zaak $zaak, string $documentuuid, ?string $type = 'view')
    {
        /** @phpstan-ignore-next-line */
        $document = $zaak->documenten->where('uuid', $documentuuid)->firstOrFail();

        $validated = $request->validated();
        if (isset($validated['version']) && $validated['version'] != $document->versie) {
            // get the specified version
            $document = new Informatieobject(...(new Openzaak)->get($document->url.'?versie='.$validated['version'])->toArray());
        }
        $disposition = $type === 'download' ? 'attachment' : 'inline';

        return response((new Openzaak)->getRaw($document->inhoud))->withHeaders([
            'Content-disposition' => $disposition.'; filename='.$document->bestandsnaam,
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Content-Type' => $document->formaat,
        ]);
    }
}

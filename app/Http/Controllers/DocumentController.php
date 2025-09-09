<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Zaak;
use Woweb\Openzaak\Openzaak;

class DocumentController extends Controller
{
    public function __invoke(DocumentRequest $request, Zaak $zaak, string $documentuuid, ?string $type = 'view')
    {
        /** @phpstan-ignore-next-line */
        $document = $zaak->documenten->where('uuid', $documentuuid)->firstOrFail();
        $disposition = $type === 'download' ? 'attachment' : 'inline';

        return response((new Openzaak)->getRaw($document->inhoud))->withHeaders([
            'Content-disposition' => $disposition.'; filename='.$document->bestandsnaam,
            'Access-Control-Expose-Headers' => 'Content-Disposition',
            'Content-Type' => $document->formaat,
        ]);
    }
}

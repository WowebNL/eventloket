<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\Support\Uploads\DocumentUploadType;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;

/**
 * Gehardende `FileUpload`-factory voor het evenementformulier.
 *
 * Spiegelt de validatie + opslag-config van `UploadDocumentAction`
 * (zie `app/Filament/Shared/Resources/Zaken/Actions/UploadDocumentAction.php:76-84`)
 * zodat een bestand dat de organisator op een form-stap upload
 * dezelfde restricties krijgt als een document dat de behandelaar
 * later via de zaak-actie zou uploaden:
 *
 * - max 30 MB
 * - mime-type-mapping uit `config/app.php` (eml/emlx/msg met
 *   passende mime-types ipv `application/octet-stream`)
 * - executable / script-uploads geweigerd via
 *   `DocumentUploadType::fileUploadRule()`
 * - opgeslagen op de lokale `local`-disk in een tenant-specifieke
 *   sub-map zodat `UploadFormBijlagenToZGW` 'm gegarandeerd
 *   terugvindt
 * - private visibility
 * - originele bestandsnaam bewaard zodat 'ie 1-op-1 als
 *   `bestandsnaam` naar OpenZaak kan
 */
final class EventloketFileUpload
{
    public static function make(string $name): FileUpload
    {
        $tenant = Filament::getTenant();
        $directory = $tenant && property_exists($tenant, 'id')
            ? sprintf('event-form-uploads/%s', $tenant->id)
            : 'event-form-uploads';

        return FileUpload::make($name)
            ->maxSize(30720) // 30 MB
            ->mimeTypeMap(config('app.document_mime_type_mappings'))
            ->rule(DocumentUploadType::fileUploadRule())
            ->directory($directory)
            ->disk('local')
            ->visibility('private')
            ->preserveFilenames();
    }
}

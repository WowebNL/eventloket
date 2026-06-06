<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use App\Models\Organisation;
use App\Support\Uploads\DocumentUploadType;
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
 * - max 60 MB
 * - mime-type-mapping uit `config/app.php` (eml/emlx/msg met
 *   passende mime-types ipv `application/octet-stream`)
 * - executable / script-uploads geweigerd via
 *   `DocumentUploadType::fileUploadRule()`
 * - opgeslagen op de lokale `local`-disk in een organisatie-specifieke
 *   sub-map (`event-form-uploads/{organisation_uuid}/`) zodat
 *   `UploadFormBijlagenToZGW` 'm gegarandeerd terugvindt
 * - private visibility
 * - originele bestandsnaam bewaard zodat 'ie 1-op-1 als
 *   `bestandsnaam` naar OpenZaak kan
 *
 * De `Organisation` wordt expliciet meegegeven in plaats van via
 * `Filament::getTenant()` opgehaald, omdat de Livewire upload-request
 * (`/livewire/upload-file`) niet door de Filament panel-middleware gaat
 * waardoor `getTenant()` daar altijd `null` retourneert.
 */
final class EventloketFileUpload
{
    public static function make(string $name, ?Organisation $organisation = null): FileUpload
    {
        $directory = $organisation !== null
            ? sprintf('event-form-uploads/%s', $organisation->uuid)
            : 'event-form-uploads';

        return FileUpload::make($name)
            ->maxSize(61440) // 60 MB
            ->mimeTypeMap(config('app.document_mime_type_mappings'))
            ->rule(DocumentUploadType::fileUploadRule())
            ->directory($directory)
            ->disk('local')
            ->visibility('private')
            ->storeFileNamesIn("{$name}_namen");
    }
}

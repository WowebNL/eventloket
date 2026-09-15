<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Enums\Role;
use App\Models\MunicipalityZaaktypeMapping;
use App\Models\Zaak;

/**
 * Decides the informatieobjecttype of a document a user uploads on a zaak.
 *
 * An organiser does not pick a type: their uploads use the bijlage-documenttype
 * the koppeling configures for the zaaktype
 * ({@see MunicipalityZaaktypeMapping::$bijlage_informatieobjecttype}), which is
 * the same slot the aanvraag-bijlagen already use. When the koppeling leaves
 * that slot empty the existing heuristic in
 * {@see ZaaktypeBlueprint::bijlageInformatieobjecttype()} decides, so an
 * unconfigured zaaktype keeps working exactly as before. Every other role still
 * chooses the type in the upload form.
 */
final class UploadDocumentTypeResolver
{
    /**
     * Whether a user of this role picks the documenttype themselves. A null role
     * (an upload whose user no longer exists) keeps the submitted value.
     */
    public static function isChosenByUser(?Role $role): bool
    {
        return $role !== Role::Organiser;
    }

    /**
     * The informatieobjecttype url to apply when the user does not choose one,
     * or null when the zaaktype exposes no documenttypes at all.
     *
     * Deliberately reads the documenttypes unfiltered
     * ({@see Zaak::catalogusDocumentTypes()}): this is the koppeling's choice,
     * not the uploader's, so it must not depend on the levels that uploader may
     * see. Reading the per-user collection made the answer depend on where the
     * code ran, since the queued path has no authenticated user to filter on,
     * and it returned null whenever the catalogus labels its documenttypes at a
     * level outside the uploader's visible set.
     */
    public static function defaultFor(Zaak $zaak): ?string
    {
        $types = $zaak->catalogusDocumentTypes();

        if ($types->isEmpty()) {
            return null;
        }

        $chosen = ZaaktypeBlueprint::bijlageInformatieobjecttype(
            MunicipalityZaaktypeMapping::forZaaktype($zaak->zaaktype),
            $types,
        );

        // The DTO exposes the url as a stringable value object, so cast it the
        // same way the other ZGW call sites do.
        $url = $chosen?->url === null ? '' : (string) $chosen->url;

        return $url !== '' ? $url : null;
    }
}

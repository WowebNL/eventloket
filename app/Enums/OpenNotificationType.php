<?php

namespace App\Enums;

enum OpenNotificationType: string
{
    case CreateZaak = 'create_zaak';
    case UpdateZaak = 'update_zaak';
    case UpdateZaakEigenschap = 'update_zaakeigenschap';
    case ZaakStatusChanged = 'zaak_status_changed';
    case NewZaakDocument = 'new_zaak_document';
    case UpdatedZaakDocument = 'updated_zaak_document';
}

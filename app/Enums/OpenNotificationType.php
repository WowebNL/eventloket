<?php

namespace App\Enums;

enum OpenNotificationType: string
{
    case CreateZaak = 'create_zaak';
    case UpdateZaak = 'update_zaak';
    case UpdateZaakEigenschap = 'update_zaakeigenschap';
    case ZaakStatusChanged = 'zaak_status_changed';
}

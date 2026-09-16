<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What caused Eventloket to destroy its own data about a zaak.
 *
 * The zaakdata itself is always destroyed in the zaaksysteem, never here. This
 * records which route told us it had been, so the permanent report can say so.
 */
enum ZaakDestructionSource: string implements HasLabel
{
    /**
     * A ZGW "destroy" notification on the zaken channel. The normal route, for
     * our own OpenZaak as much as for a municipality's own instance: whoever
     * deleted the zaak, the notification is what reaches us.
     */
    case Notification = 'notification';

    /**
     * The zaak was on a destruction list that finished, but the notification
     * never arrived. Picked up by archiving:reconcile-destroyed-zaken.
     */
    case Reconciliation = 'reconciliation';

    public function getLabel(): string
    {
        return __("enums/zaak_destruction_source.{$this->value}.label");
    }
}

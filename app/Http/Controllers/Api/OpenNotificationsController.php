<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OpenNotificationRequest;
use App\Jobs\ProcessOpenNotification;
use App\Services\Notificaties\NotificationRoundTripProbe;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Arr;

class OpenNotificationsController extends Controller
{
    public function listen(OpenNotificationRequest $request): void
    {
        $data = $request->validated();

        // A notification carrying a correlation id we generated is our own
        // round-trip probe: record the receipt and do not process it further.
        // recordReceipt() only matches ids we marked pending, so real
        // notifications fall through to normal processing.
        $probeId = Arr::get($data, 'kenmerken.probe_id');

        if (is_string($probeId) && $probeId !== '' && NotificationRoundTripProbe::recordReceipt($probeId)) {
            return;
        }

        dispatch(new ProcessOpenNotification(new OpenNotification(
            actie: $data['actie'],
            kanaal: $data['kanaal'],
            resource: $data['resource'],
            hoofdObject: $data['hoofdObject'],
            resourceUrl: $data['resourceUrl'],
            aanmaakdatum: $data['aanmaakdatum'],
        )));
    }
}

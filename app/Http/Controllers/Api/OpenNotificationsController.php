<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OpenNotificationRequest;
use App\Jobs\ProcessOpenNotification;
use App\Listeners\LogZgwRequest;
use App\Models\ZgwRequestLog;
use App\Services\Zgw\ZgwConnectionResolver;
use App\ValueObjects\OpenNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenNotificationsController extends Controller
{
    public function __construct(private readonly ZgwConnectionResolver $connections) {}

    public function listen(OpenNotificationRequest $request): void
    {
        $data = $request->validated();

        $notification = new OpenNotification(
            actie: $data['actie'],
            kanaal: $data['kanaal'],
            resource: $data['resource'],
            hoofdObject: $data['hoofdObject'],
            resourceUrl: $data['resourceUrl'],
            aanmaakdatum: $data['aanmaakdatum'],
        );

        $this->logIncomingNotification($notification);

        dispatch(new ProcessOpenNotification($notification));
    }

    /**
     * Record the received notification in the ZGW log so it shows up in the
     * connection's logboek alongside the outbound requests. Attribution uses the
     * hoofdObject URL: a per-municipality connection maps to that municipality's
     * logboek, anything ambiguous to the shared "main" connection. A logging
     * failure must never break the webhook.
     */
    private function logIncomingNotification(OpenNotification $notification): void
    {
        try {
            $connection = $this->connections->forUrl($notification->hoofdObject);

            ZgwRequestLog::create([
                'connection' => $connection,
                'municipality_id' => ZgwRequestLog::municipalityIdFromConnection($connection),
                'user_id' => null,
                'method' => 'NOTIFY',
                'resource' => $this->path($notification->resourceUrl),
                'status_code' => 200,
                'failed' => false,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record an incoming ZGW notification log.', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * The URL path without its query string (which may carry personal data),
     * matching how outbound requests are stored by {@see LogZgwRequest}.
     */
    private function path(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $uri;
    }
}

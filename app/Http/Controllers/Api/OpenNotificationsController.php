<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\IdentifyWebhookClient;
use App\Http\Requests\Api\OpenNotificationRequest;
use App\Jobs\ProcessOpenNotification;
use App\Listeners\LogZgwRequest;
use App\Models\ZgwAbonnement;
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
            // Carried through to the queued job: the kenmerken are what tells a
            // handler which connection owns the notification when several share
            // a host.
            kenmerken: OpenNotification::normaliseKenmerken($data['kenmerken'] ?? null),
            // Who called, established from the validated token rather than from
            // anything in the payload. A handler that acts irreversibly checks
            // this against the zaak's own connection.
            senderConnection: $this->senderConnection($request),
        );

        $this->logIncomingNotification($notification);

        dispatch(new ProcessOpenNotification($notification));
    }

    /**
     * The connection the calling credential belongs to.
     *
     * Each connection's webhook token is minted for its own one-off Passport
     * client, and the abonnement row records which. Looking the client up there
     * is therefore the same question as "which connection is this", answered
     * without trusting the caller. Null when the client matches no abonnement,
     * which leaves the notification unattributed.
     */
    private function senderConnection(OpenNotificationRequest $request): ?string
    {
        $clientId = $request->attributes->get(IdentifyWebhookClient::CLIENT_ID_ATTRIBUTE);

        if (! is_string($clientId) || $clientId === '') {
            return null;
        }

        return ZgwAbonnement::query()->where('client_id', $clientId)->value('connection');
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

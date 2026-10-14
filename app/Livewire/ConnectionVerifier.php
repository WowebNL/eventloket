<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\MunicipalityZgwConnection;
use App\Services\Notificaties\AbonnementCheckStatus;
use App\Services\Notificaties\AbonnementHealthCheck;
use App\Services\Notificaties\AbonnementRegistrar;
use App\Services\Notificaties\AbonnementRegistrationOutcome;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;
use Woweb\Zgw\Facades\Zgw;

/**
 * Drives the "Verbinding testen" modal: one stepped flow that checks the
 * connection and verifies (and optionally registers) the notification
 * abonnement. On a fully successful run it stamps the connection's
 * last_verified_at.
 */
class ConnectionVerifier extends Component
{
    #[Locked]
    public int $connectionId;

    /**
     * Per-step state. status: pending | running | success | fail | skipped | action.
     *
     * @var array<string, array{status: string, message: string}>
     */
    public array $steps = [
        'urls' => ['status' => 'pending', 'message' => ''],
        'connection' => ['status' => 'pending', 'message' => ''],
        'apis' => ['status' => 'pending', 'message' => ''],
        'abonnement' => ['status' => 'pending', 'message' => ''],
    ];

    /**
     * The URL fields whose instance must agree: the zaaktype a zaak references is
     * read from the catalogi API and sent to the zaken API, so a row that points
     * these two at different instances produces zaken the receiving instance
     * cannot resolve. The remaining components are read-only surfaces and are
     * covered by the per-API reads in the next step.
     *
     * @var list<string>
     */
    private const COHERENT_URL_FIELDS = ['zaken', 'catalogi'];

    /**
     * The APIs checked individually after the base connection check, in the order
     * they are reported.
     *
     * @var list<string>
     */
    private const CHECKED_APIS = ['zaken', 'documenten', 'besluiten'];

    public bool $needsRegister = false;

    public bool $finished = false;

    public bool $success = false;

    public function mount(MunicipalityZgwConnection $connection): void
    {
        $this->authorize('verify', $connection);

        $this->connectionId = $connection->getKey();
    }

    /**
     * Run steps 1 and 2 once, triggered by wire:init after the first render.
     */
    public function start(): void
    {
        $this->runUrlsStep();

        if ($this->steps['urls']['status'] !== 'success') {
            $this->finish(false);

            return;
        }

        $this->runConnectionStep();

        if ($this->steps['connection']['status'] !== 'success') {
            $this->finish(false);

            return;
        }

        $this->runApisStep();

        if ($this->steps['apis']['status'] !== 'success') {
            $this->finish(false);

            return;
        }

        $this->runAbonnementStep();
    }

    public function register(): void
    {
        $this->needsRegister = false;
        $this->steps['abonnement'] = ['status' => 'running', 'message' => ''];

        try {
            $this->registerConfig();
            $outcome = app(AbonnementRegistrar::class)->register($this->name());
        } catch (Throwable $e) {
            $this->logFailure('abonnement registration failed', $e);
            $this->steps['abonnement'] = ['status' => 'action', 'message' => $this->trans('abonnement.error')];
            $this->needsRegister = true;

            return;
        }

        if ($outcome === AbonnementRegistrationOutcome::SkippedNoNotificatiesUrl) {
            $this->steps['abonnement'] = ['status' => 'fail', 'message' => $this->trans('abonnement.no_notificaties_url')];
            $this->finish(false);

            return;
        }

        $this->runAbonnementStep();
    }

    public function render(): View
    {
        return view('filament.zgw.connection-verifier');
    }

    /**
     * Check that the zaken and catalogi URLs point at the same instance before
     * anything is contacted.
     *
     * An empty URL field is inherited from the main connection, which is a
     * feature for a municipality that shares that instance. On a row that does
     * point at its own instance, however, leaving one of these two blank mixes
     * the two: zaaktypen are then read on one instance and the zaak created on
     * the other, which the receiving instance rejects. That never shows up in
     * the per-API reads, because a query on the inherited instance answers
     * HTTP 200 with no results.
     */
    private function runUrlsStep(): void
    {
        $this->steps['urls'] = ['status' => 'running', 'message' => ''];

        $inherited = $this->inheritedUrlFieldsOnOwnInstance();

        $this->steps['urls'] = $inherited === []
            ? ['status' => 'success', 'message' => $this->trans('urls.success')]
            : ['status' => 'fail', 'message' => __('municipality/resources/zgw_connection.actions.verify.urls.error', [
                'fields' => implode(', ', array_map(
                    static fn (string $field): string => __("municipality/resources/zgw_connection.actions.verify.urls.names.{$field}"),
                    $inherited,
                )),
            ])];
    }

    /**
     * The coherence-critical URL fields that this connection leaves empty while
     * its other URLs do point at an instance of its own. Empty when the row is
     * coherent: either it inherits everything, or it fills both fields in.
     *
     * @return list<string>
     */
    private function inheritedUrlFieldsOnOwnInstance(): array
    {
        $connection = $this->connection();

        $mainHosts = $this->hosts((array) config('zgw.connections.main.urls', []));

        $own = [];
        $inherited = [];

        foreach (self::COHERENT_URL_FIELDS as $field) {
            $url = $connection->{$field.'_url'};

            if (! is_string($url) || $url === '') {
                $inherited[] = $field;

                continue;
            }

            $own[] = $url;
        }

        $ownHosts = array_diff($this->hosts($own), $mainHosts);

        return $ownHosts === [] ? [] : $inherited;
    }

    /**
     * The distinct lowercase hosts of a list of URLs.
     *
     * @param  array<int|string, mixed>  $urls
     * @return list<string>
     */
    private function hosts(array $urls): array
    {
        $hosts = [];

        foreach ($urls as $url) {
            $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

            if (is_string($host) && $host !== '') {
                $hosts[strtolower($host)] = true;
            }
        }

        return array_keys($hosts);
    }

    private function runConnectionStep(): void
    {
        $this->steps['connection'] = ['status' => 'running', 'message' => ''];

        try {
            $this->registerConfig();
            Zgw::connection($this->name())->assertUsable();
            $this->steps['connection'] = ['status' => 'success', 'message' => $this->trans('connection.success')];
        } catch (Throwable $e) {
            $this->logFailure('connection step failed', $e);
            $this->steps['connection'] = ['status' => 'fail', 'message' => $this->trans('connection.error')];
        }
    }

    /**
     * One read per API this application uses at runtime. `assertUsable()` only
     * proves the catalogi API works, while a wrong or omitted base URL for the
     * zaken, documenten or besluiten API surfaces much later as an empty tab: an
     * omitted URL inherits the main connection's, and a query there for this
     * instance's zaak returns HTTP 200 with no results.
     */
    private function runApisStep(): void
    {
        $this->steps['apis'] = ['status' => 'running', 'message' => ''];

        $failed = [];
        foreach (self::CHECKED_APIS as $api) {
            try {
                $this->registerConfig();
                $this->readFrom($api);
            } catch (Throwable $e) {
                $this->logFailure("api check failed: {$api}", $e);
                $failed[] = __("municipality/resources/zgw_connection.actions.verify.apis.names.{$api}");
            }
        }

        $this->steps['apis'] = $failed === []
            ? ['status' => 'success', 'message' => $this->trans('apis.success')]
            : ['status' => 'fail', 'message' => __('municipality/resources/zgw_connection.actions.verify.apis.error', [
                'apis' => implode(', ', $failed),
            ])];
    }

    /**
     * A single, cheap list request against the given API. Any error (unreachable
     * host, wrong base URL, missing authorisation) throws.
     */
    private function readFrom(string $api): void
    {
        $connection = Zgw::connection($this->name());

        match ($api) {
            'zaken' => $connection->zaken()->zaken()->index(['page' => 1])->first(),
            'documenten' => $connection->documenten()->enkelvoudiginformatieobjecten()->index(['page' => 1])->first(),
            'besluiten' => $connection->besluiten()->besluiten()->index(['page' => 1])->first(),
            default => null,
        };
    }

    private function runAbonnementStep(): void
    {
        $this->needsRegister = false;
        $this->steps['abonnement'] = ['status' => 'running', 'message' => ''];

        try {
            $this->registerConfig();
            $result = app(AbonnementHealthCheck::class)->run($this->name());
        } catch (Throwable $e) {
            $this->logFailure('abonnement check failed', $e);
            $this->steps['abonnement'] = ['status' => 'fail', 'message' => $this->trans('abonnement.error')];
            $this->finish(false);

            return;
        }

        match ($result->status) {
            AbonnementCheckStatus::Healthy => $this->abonnementHealthy($this->trans('abonnement.healthy')),
            AbonnementCheckStatus::TokenExpiringSoon => $this->abonnementHealthy($this->trans('abonnement.expiring_soon')),
            AbonnementCheckStatus::NoNotificatiesUrl => $this->abonnementBlocked(),
            default => $this->abonnementNeedsRegister(),
        };
    }

    private function abonnementHealthy(string $message): void
    {
        $this->steps['abonnement'] = ['status' => 'success', 'message' => $message];
        $this->finish(true);
    }

    private function abonnementBlocked(): void
    {
        $this->steps['abonnement'] = ['status' => 'fail', 'message' => $this->trans('abonnement.no_notificaties_url')];
        $this->finish(false);
    }

    private function abonnementNeedsRegister(): void
    {
        $this->steps['abonnement'] = ['status' => 'action', 'message' => $this->trans('abonnement.needs_register')];
        $this->needsRegister = true;
    }

    private function finish(bool $success): void
    {
        $this->finished = true;
        $this->success = $success;

        if (! $success) {
            foreach ($this->steps as $key => $step) {
                if ($step['status'] === 'pending') {
                    $this->steps[$key]['status'] = 'skipped';
                }
            }

            return;
        }

        // Stamp the verification without firing the model observer (which would
        // needlessly restart Horizon on every successful check).
        $this->connection()->updateQuietly(['last_verified_at' => now()]);
    }

    private function connection(): MunicipalityZgwConnection
    {
        return MunicipalityZgwConnection::findOrFail($this->connectionId);
    }

    private function name(): string
    {
        return 'gemeente_'.$this->connection()->municipality_id;
    }

    private function registerConfig(): void
    {
        $connection = $this->connection();
        Config::set("zgw.connections.gemeente_{$connection->municipality_id}", $connection->buildConfig());
    }

    private function trans(string $key): string
    {
        return __("municipality/resources/zgw_connection.actions.verify.{$key}");
    }

    /**
     * Log the real failure for diagnostics. The exception message is never shown
     * to the user; the steps only carry a generic, translated message.
     */
    private function logFailure(string $context, Throwable $e): void
    {
        Log::warning("ConnectionVerifier: {$context}", [
            'connection_id' => $this->connectionId,
            'exception' => $e->getMessage(),
        ]);
    }
}

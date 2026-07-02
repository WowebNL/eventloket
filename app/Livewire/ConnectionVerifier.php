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
        'connection' => ['status' => 'pending', 'message' => ''],
        'abonnement' => ['status' => 'pending', 'message' => ''],
    ];

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
        $this->runConnectionStep();

        if ($this->steps['connection']['status'] !== 'success') {
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

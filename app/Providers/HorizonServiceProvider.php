<?php

namespace App\Providers;

use App\Models\Users\AdminUser;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        if ($webhookUrl = config('services.slack.horizon_webhook_url')) {
            Horizon::routeSlackNotificationsTo($webhookUrl);
        }
    }

    /**
     * Override to enforce auth in all environments, including local.
     * The parent bypasses the gate with `|| app()->environment('local')`.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn ($request) => Gate::check('viewHorizon', [$request->user()]));
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            return $user instanceof AdminUser;
        });
    }
}

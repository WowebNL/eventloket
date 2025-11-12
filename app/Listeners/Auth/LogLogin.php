<?php

namespace App\Listeners\Auth;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;

class LogLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        activity('auth')
            ->event('login')
            ->withProperties([
                'guard' => $event->guard,
                'remember' => $event->remember,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'login_path' => request()->path(),
                'panel' => Filament::getCurrentPanel()?->getId(),
            ])
            ->log('User logged in');
    }
}

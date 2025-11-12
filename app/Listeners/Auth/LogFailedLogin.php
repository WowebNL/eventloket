<?php

namespace App\Listeners\Auth;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
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
    public function handle(Failed $event): void
    {
        activity('auth')
            ->event('login_failed')
            ->withProperties([
                'guard' => $event->guard,
                'user' => $event->user,
                'credentials' => $event->credentials,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'login_path' => request()->path(),
                'panel' => Filament::getCurrentPanel()?->getId(),
            ])
            ->log('Failed login attempt');
    }
}

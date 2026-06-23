<?php

namespace App\Listeners\Auth;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;

class LogLogout
{
    public function handle(Logout $event): void
    {
        activity('auth')
            ->event('logout')
            ->causedBy($event->user instanceof Model ? $event->user : null)
            ->withProperties([
                'guard' => $event->guard,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'panel' => Filament::getCurrentPanel()?->getId(),
            ])
            ->log(__('activity/event.logout'));
    }
}

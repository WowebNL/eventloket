<?php

namespace App\Listeners\Auth;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\Model;

class LogPasswordReset
{
    public function handle(PasswordReset $event): void
    {
        activity('auth')
            ->event('password_reset')
            ->causedBy($event->user instanceof Model ? $event->user : null)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log(__('activity/event.password_reset'));
    }
}

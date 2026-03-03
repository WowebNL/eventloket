<?php

namespace App\Filament\Shared\Pages;

use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;

class Login extends \Filament\Auth\Pages\Login
{
    public function getHeading(): string|Htmlable
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return __('filament-panels::auth/pages/login.multi_factor.heading');
        }

        $panelId = Filament::getCurrentPanel()->getId();
        $label = __("shared/pages/login.type.{$panelId}");

        return __('shared/pages/login.heading', ['type' => $label]);
    }
}

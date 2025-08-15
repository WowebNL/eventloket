<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class OrganiserPanelSettings extends Settings
{
    public string $intro;

    public static function group(): string
    {
        return 'organiser';
    }
}

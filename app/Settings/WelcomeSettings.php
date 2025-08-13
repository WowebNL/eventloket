<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class WelcomeSettings extends Settings
{
    public string $title;

    public string $tagline;

    public ?string $preview_image;

    public string $intro;

    public ?array $usps;

    public ?string $outro;

    public ?array $faqs;

    public static function group(): string
    {
        return 'welcome';
    }
}

<?php

namespace App\Filament\Pages;

use Filament\Pages\SimplePage;
use Filament\Support\Enums\MaxWidth;

class Welcome extends SimplePage
{
    protected static string $view = 'filament.pages.welcome';

    public function getMaxWidth(): MaxWidth
    {
        return MaxWidth::ExtraLarge;
    }
}

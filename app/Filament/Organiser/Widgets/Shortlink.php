<?php

namespace App\Filament\Organiser\Widgets;

use Filament\Widgets\Widget;

class Shortlink extends Widget
{
    protected static ?int $sort = 2;

    protected string $view = 'filament.organiser.widgets.shortlink';
}

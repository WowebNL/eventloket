<?php

namespace App\Filament\Organiser\Widgets;

use App\Settings\OrganiserPanelSettings;
use Filament\Widgets\Widget;

class Intro extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public $introContent;

    protected string $view = 'filament.organiser.widgets.intro';

    public function mount(OrganiserPanelSettings $settings): void
    {
        $this->introContent = $settings->intro;
    }
}

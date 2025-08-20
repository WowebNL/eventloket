<?php

namespace App\Filament\Organiser\Pages;

use Filament\Pages\Page;

class NewRequest extends Page
{
    public $formId;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.organiser.pages.new-request';

    public function getTitle(): string
    {
        return '';
    }

    public static function getNavigationLabel(): string
    {
        return __('organiser/pages/new-request.navigation_label');
    }

    public function mount(): void
    {
        $this->formId = config('services.open_forms.main_form_uuid');
    }
}

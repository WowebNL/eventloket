<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Role;
use App\Settings\OrganiserPanelSettings;
use Filament\Forms\Components\RichEditor;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;

class ManageOrganiserPanel extends SettingsPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string $settings = OrganiserPanelSettings::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin/clusters/admin_settings.content_group_label');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->role === Role::Admin;
    }

    public function getHeading(): string
    {
        return __('admin/pages/manage-organiser-panel.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/pages/manage-organiser-panel.navigation_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                RichEditor::make('intro')
                    ->label(__('admin/pages/manage-organiser-panel.form.intro.label'))
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}

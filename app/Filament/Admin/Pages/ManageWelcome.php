<?php

namespace App\Filament\Admin\Pages;

use App\Enums\Role;
use App\Settings\WelcomeSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;

class ManageWelcome extends SettingsPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static string $settings = WelcomeSettings::class;

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
        return __('admin/pages/manage-welcome.heading');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin/pages/manage-welcome.navigation_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label(__('admin/pages/manage-welcome.form.title.label'))
                    ->required(),
                TextInput::make('tagline')
                    ->label(__('admin/pages/manage-welcome.form.tagline.label'))
                    ->required(),
                FileUpload::make('preview_image')
                    ->label(__('admin/pages/manage-welcome.form.welcome_image.label'))
                    ->acceptedFileTypes([
                        'image/png',
                        'image/jpeg'
                    ])
                    ->maxSize(10240) // 10MB
                    ->disk('public'),
                RichEditor::make('intro')
                    ->label(__('admin/pages/manage-welcome.form.intro.label'))
                    ->required()
                    ->columnSpanFull(),
                Repeater::make('usps')
                    ->label(__('admin/pages/manage-welcome.form.usps.label'))
                    ->addActionLabel(__('admin/pages/manage-welcome.form.usps.add_action_label'))
                    ->schema([
                        Select::make('icon')
                            ->label(__('admin/pages/manage-welcome.form.usps.items.icon.label'))
                            ->options([
                                'tabler-permit' => file_get_contents(resource_path('svg/tabler/permit.svg')),
                                'tabler-people' => file_get_contents(resource_path('svg/tabler/people.svg')),
                                'tabler-progress' => file_get_contents(resource_path('svg/tabler/progress.svg')),
                                'tabler-calendar' => file_get_contents(resource_path('svg/tabler/calendar.svg')),
                            ])
                            ->allowHtml()
                            ->native(false)
                            ->required(),
                        TextInput::make('title')
                            ->label(__('admin/pages/manage-welcome.form.usps.items.title.label'))
                            ->required(),
                        Textarea::make('description')
                            ->label(__('admin/pages/manage-welcome.form.usps.items.description.label'))
                            ->required(),
                    ])->columnSpanFull(),
                RichEditor::make('outro')
                    ->label(__('admin/pages/manage-welcome.form.outro.label'))
                    ->helperText(__('admin/pages/manage-welcome.form.outro.helper_text'))
                    ->columnSpanFull(),
                Repeater::make('faqs')
                    ->label(__('admin/pages/manage-welcome.form.faqs.label'))
                    ->addActionLabel(__('admin/pages/manage-welcome.form.faqs.add_action_label'))
                    ->schema([
                        TextInput::make('question')
                            ->label(__('admin/pages/manage-welcome.form.faqs.items.question.label'))
                            ->required(),
                        RichEditor::make('answer')
                            ->label(__('admin/pages/manage-welcome.form.faqs.items.answer.label'))
                            ->required(),

                    ])->columnSpanFull(),
            ]);
    }
}

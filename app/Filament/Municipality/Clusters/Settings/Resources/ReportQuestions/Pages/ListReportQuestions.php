<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions\ReportQuestionResource;
use App\Filament\Shared\Resources\ReportQuestions\Concerns\SafelyReordersReportQuestions;
use App\Models\Municipality;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

/**
 * @property Schema $municipalitySettingsForm
 */
class ListReportQuestions extends ListRecords
{
    use SafelyReordersReportQuestions;

    protected static string $resource = ReportQuestionResource::class;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $municipalitySettings = [];

    public function mount(): void
    {
        parent::mount();

        /** @var Municipality $municipality */
        $municipality = Filament::getTenant();

        $this->municipalitySettingsForm->fill([
            'use_new_report_questions' => $municipality->use_new_report_questions,
        ]);
    }

    public function defaultMunicipalitySettingsForm(Schema $schema): Schema
    {
        return $schema->statePath('municipalitySettings');
    }

    public function municipalitySettingsForm(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('use_new_report_questions')
                ->label(__('municipality/pages/settings/general.form.use_new_report_questions.label'))
                ->helperText(__('municipality/pages/settings/general.form.use_new_report_questions.helper_text'))
                ->live()
                ->afterStateUpdated(function (bool $state): void {
                    /** @var Municipality $municipality */
                    $municipality = Filament::getTenant();
                    $municipality->update(['use_new_report_questions' => $state]);

                    Notification::make()
                        ->success()
                        ->title(__('municipality/pages/settings/general.notifications.saved'))
                        ->send();
                }),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getTabsContentComponent(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            EmbeddedTable::make(),
            Section::make()
                ->schema([EmbeddedSchema::make('municipalitySettingsForm')])
                ->compact(),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }
}

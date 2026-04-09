<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions\Pages;

use App\Filament\Municipality\Clusters\Settings\Resources\ReportQuestions\ReportQuestionResource;
use App\Models\Municipality;
use App\Models\ReportQuestion;
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
use Illuminate\Support\Facades\DB;

/**
 * @property \Filament\Schemas\Schema $municipalitySettingsForm
 */
class ListReportQuestions extends ListRecords
{
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

    /**
     * Override Filament's default bulk CASE WHEN update to avoid unique constraint
     * violations on (municipality_id, order). Works on both PostgreSQL and MySQL.
     *
     * Strategy: first shift all affected orders to a temporary high value (no conflicts
     * within the same municipality), then set the final values one by one.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        $this->getTable()->callBeforeReordering($order);

        DB::transaction(function () use ($order): void {
            $ids = array_values($order);

            // Pass 1: shift all to high values so none of the 1-10 slots are occupied.
            // Max 10 records with order values 1–10; adding 200 safely stays within
            // the unsignedTinyInteger range (max 255).
            ReportQuestion::whereIn('id', $ids)->increment('order', 200);

            // Pass 2: write the final positions. No conflicts because all targeted
            // records are currently sitting at 201–210.
            foreach ($order as $index => $id) {
                ReportQuestion::where('id', $id)->update(['order' => $index + 1]);
            }
        });

        $this->getTable()->callAfterReordering($order);
    }
}

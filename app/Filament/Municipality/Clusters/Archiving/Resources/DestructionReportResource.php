<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources;

use App\Filament\Municipality\Clusters\Archiving;
use App\Filament\Municipality\Clusters\Archiving\Actions\RegenerateDestructionReportAction;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionReportResource\Pages\ListDestructionReports;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionReportResource\Pages\ViewDestructionReport;
use App\Models\Archiving\DestructionReport;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DestructionReportResource extends Resource
{
    protected static ?string $model = DestructionReport::class;

    protected static ?string $cluster = Archiving::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('municipality/resources/destruction_report.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/destruction_report.plural_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('batch_number')
                    ->label(__('municipality/resources/destruction_report.columns.batch_number.label')),
                TextEntry::make('destruction_date')
                    ->label(__('municipality/resources/destruction_report.columns.destruction_date.label'))
                    ->dateTime(),
                TextEntry::make('destruction_method')
                    ->label(__('municipality/resources/destruction_report.columns.destruction_method.label')),
                TextEntry::make('coordinator_name')
                    ->label(__('municipality/resources/destruction_report.columns.coordinator_name.label')),
                TextEntry::make('coordinator_function')
                    ->label(__('municipality/resources/destruction_report.columns.coordinator_function.label')),
                TextEntry::make('total_count')
                    ->label(__('municipality/resources/destruction_report.columns.total_count.label')),
                TextEntry::make('deleted_count')
                    ->label(__('municipality/resources/destruction_report.columns.deleted_count.label')),
                TextEntry::make('skipped_count')
                    ->label(__('municipality/resources/destruction_report.columns.skipped_count.label')),
                TextEntry::make('failed_count')
                    ->label(__('municipality/resources/destruction_report.columns.failed_count.label')),
                RepeatableEntry::make('items')
                    ->label(__('municipality/resources/destruction_report.columns.items.label'))
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('zaaknummer')
                            ->label(__('municipality/resources/destruction_list.items.columns.zaaknummer.label')),
                        TextEntry::make('zaaktype')
                            ->label(__('municipality/resources/destruction_list.items.columns.zaaktype_naam.label')),
                        TextEntry::make('selectielijst_categorie')
                            ->label(__('municipality/resources/destruction_list.items.columns.selectielijst_categorie.label')),
                        TextEntry::make('status')
                            ->label(__('municipality/resources/destruction_list.items.columns.status.label'))
                            ->formatStateUsing(fn (string $state): string => __("enums/destruction_item_status.{$state}.label")),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('batch_number')
                    ->label(__('municipality/resources/destruction_report.columns.batch_number.label'))
                    ->searchable(),
                TextColumn::make('destruction_date')
                    ->label(__('municipality/resources/destruction_report.columns.destruction_date.label'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('coordinator_name')
                    ->label(__('municipality/resources/destruction_report.columns.coordinator_name.label')),
                TextColumn::make('total_count')
                    ->label(__('municipality/resources/destruction_report.columns.total_count.label')),
                TextColumn::make('deleted_count')
                    ->label(__('municipality/resources/destruction_report.columns.deleted_count.label')),
            ])
            ->defaultSort('destruction_date', 'desc')
            ->recordActions([
                ViewAction::make(),
                static::getDownloadPdfAction(),
                RegenerateDestructionReportAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getDownloadPdfAction(): Action
    {
        return Action::make('download_pdf')
            ->label(__('municipality/resources/destruction_report.actions.download_pdf.label'))
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (DestructionReport $record): bool => $record->hasPdf())
            ->action(fn (DestructionReport $record): StreamedResponse => Storage::disk(config('archiving.report_disk'))->download($record->pdf_path));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDestructionReports::route('/'),
            'view' => ViewDestructionReport::route('/{record}'),
        ];
    }
}

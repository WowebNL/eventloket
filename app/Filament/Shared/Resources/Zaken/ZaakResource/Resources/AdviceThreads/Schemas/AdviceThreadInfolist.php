<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\Schemas;

use App\Enums\AdviceStatus;
use App\Filament\Shared\Infolists\Components\ThreadMessagesEntry;
use App\Models\Threads\AdviceThread;
use App\Models\Users\MunicipalityUser;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Enums\Width;

class AdviceThreadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        TextEntry::make('advice_status')
                            ->label(__('resources/advice_thread.columns.advice_status.label'))
                            ->badge()
                            ->size(TextSize::Large)
                            ->hintAction(
                                Action::make('editAdviceStatus')
                                    ->visible(fn () => auth()->user() instanceof MunicipalityUser)
                                    ->label('Wijzigen')
                                    ->modalHeading('Wijzig advies status')
                                    ->icon('heroicon-o-pencil-square')
                                    ->fillForm(fn (AdviceThread $record) => ['advice_status' => $record->advice_status])
                                    ->schema([
                                        Select::make('advice_status')
                                            ->label(__('resources/advice_thread.columns.advice_status.label'))
                                            ->options(AdviceStatus::class)
                                            ->selectablePlaceholder(false)
                                            ->required(),
                                    ])
                                    ->action(fn (array $data, AdviceThread $record) => $record->update(['advice_status' => $data['advice_status']]))
                                    ->modalWidth(Width::Medium),
                            ),
                        TextEntry::make('advisory.name')
                            ->label(__('resources/advice_thread.columns.advisory.label'))
                            ->icon('heroicon-o-lifebuoy'),
                        TextEntry::make('advice_due_at')
                            ->label(__('resources/advice_thread.columns.advice_due_at.label'))
                            ->dateTime()
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('createdBy.name')
                            ->label(__('resources/advice_thread.columns.created_by.label'))
                            ->icon('heroicon-o-user-circle'),
                    ]),

                ThreadMessagesEntry::make('messages'),
            ]);
    }
}

<?php

namespace App\Livewire\Zaken;

use App\Models\Zaak;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ZaakDocumentsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public Zaak $zaak;

    public function mount(Zaak $zaak): void
    {
        $this->zaak = $zaak;
    }

    public function table(Table $table): Table
    {
        return $table
            /** @phpstan-ignore-next-line */
            ->records(fn (): Collection => $this->zaak->documenten->map(fn ($item) => $item->toArray()))
            ->columns([
                TextColumn::make('titel'),
                TextColumn::make('creatiedatum')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('versie')
                    ->sortable(),
                TextColumn::make('auteur')
                    ->sortable(),
                TextColumn::make('bestandsnaam'),
            ])
            ->filters([
                // ...
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('Bekijken'))
                    ->url(fn (array $record): string => route('zaak.documents.view', [
                        'zaak' => $this->zaak->id,
                        'documentuuid' => $record['uuid'],
                        'type' => 'view',
                    ]))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-eye'),
                Action::make('downloaden')
                    // ->label(__('municipality/resources/zaak.actions.download.label'))
                    ->url(fn (array $record): string => route('zaak.documents.view', [
                        'zaak' => $this->zaak->id,
                        'documentuuid' => $record['uuid'],
                        'type' => 'download',
                    ]))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->toolbarActions([
                // ...
            ]);
    }

    public function render()
    {
        return view('livewire.shared.table');
    }
}

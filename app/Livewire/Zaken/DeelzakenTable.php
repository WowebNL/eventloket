<?php

namespace App\Livewire\Zaken;

use App\Models\Zaak;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DeelzakenTable extends Component implements HasActions, HasSchemas, HasTable
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
            ->query(
                Zaak::where('data_object_url', $this->zaak->data_object_url)
                    ->where('id', '!=', $this->zaak->id)
                    ->with('zaaktype')
            )
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('municipality/resources/zaak.columns.public_id.label')),
                TextColumn::make('zaaktype.name')
                    ->label(__('municipality/resources/zaak.columns.zaaktype.label')),
                TextColumn::make('reference_data.status_name')
                    ->label(__('municipality/resources/zaak.columns.status.label'))
                    ->placeholder('-'),
                TextColumn::make('reference_data.resultaat')
                    ->label(__('municipality/resources/zaak.columns.resultaat.label'))
                    ->placeholder('-'),
                TextColumn::make('besluit')
                    ->label(__('municipality/resources/zaak.columns.besluit.label'))
                    ->getStateUsing(fn (Zaak $record) => $record->besluiten->first()?->name)
                    ->placeholder('-'),
            ])
            ->paginated(false);
    }

    public function render(): View
    {
        return view('livewire.shared.table');
    }
}

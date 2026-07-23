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
use Illuminate\Database\Eloquent\Builder;
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
        // Relate doorkomst zaken through the local hoofdzaak link: from the
        // hoofdzaak we see its deelzaken, from a deelzaak we see the hoofdzaak and
        // its siblings. A non-empty data_object_url is kept as a fallback so legacy
        // Objects-API deelzaken still group.
        $rootId = $this->zaak->hoofdzaak_id ?? $this->zaak->id;
        $dataObjectUrl = $this->zaak->data_object_url;

        return $table
            ->query(
                Zaak::where('id', '!=', $this->zaak->id)
                    ->where(function (Builder $query) use ($rootId, $dataObjectUrl) {
                        $query->where('hoofdzaak_id', $rootId)
                            ->orWhere('id', $rootId);

                        if (! empty($dataObjectUrl)) {
                            $query->orWhere('data_object_url', $dataObjectUrl);
                        }
                    })
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

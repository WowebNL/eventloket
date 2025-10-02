<?php

namespace App\Livewire\Zaken;

use App\ValueObjects\ZGW\DocumentAuditTrail;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Livewire\Component;

class ListDocumentAuditTrails extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public array $audittrail = [];

    public function mount($audittrail)
    {
        $this->audittrail = $audittrail;
        // dd($this->audittrail);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => collect($this->audittrail)->map(fn (array $item) => (new DocumentAuditTrail(...$item))->toArray()))
            ->columns([
                TextColumn::make('aanmaakdatum')->label(__('Datum'))->dateTime(app('app.datetime_format')),
                TextColumn::make('friendlyAction')->label(__('Actie')),
                TextColumn::make('applicatieWeergave')->label(__('Applicatie')),
                TextColumn::make('gebruikersWeergave')->label(__('Gebruiker')),
            ])
            ->defaultSort('timestamp', 'desc')
            ->filters([]);
    }

    public function render()
    {
        return view('livewire.shared.table');
    }
}

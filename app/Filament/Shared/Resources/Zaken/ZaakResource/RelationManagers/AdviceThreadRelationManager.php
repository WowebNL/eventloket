<?php

namespace App\Filament\Shared\Resources\Zaken\ZaakResource\RelationManagers;

use App\Enums\AdviceStatus;
use App\Filament\Shared\Resources\Zaken\ZaakResource\Resources\AdviceThreads\AdviceThreadResource;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdviceThreadRelationManager extends RelationManager
{
    protected static string $relationship = 'adviceThreads';

    protected static ?string $relatedResource = AdviceThreadResource::class;

    public function filterTableQuery(Builder $query): Builder
    {
        $query = parent::filterTableQuery($query);

        if (Filament::getCurrentPanel()->getId() === 'advisor') {
            /** @var \App\Models\Advisory $tenant */
            $tenant = Filament::getTenant();

            //            $query->where('advisory_id', $tenant->id);
            $query->where('advice_status', '!=', AdviceStatus::Concept);

            return $query;
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->authorize('create'),
            ]);
    }

    public function getTabs(): array
    {
        if (Filament::getCurrentPanel()->getId() !== 'advisor') {
            return [];
        }

        return [
            'mine' => Tab::make()
                ->label(__('resources/advice_thread.tabs.mine'))
                ->badge(fn () => $this->filterTableQuery($this->getRelationship()->getQuery())
                    ->whereHas('assignedUsers', fn (Builder $query) => $query->where('user_id', auth()->id()))
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('assignedUsers', fn (Builder $query) => $query->where('user_id', auth()->id()))),
            'all' => Tab::make()
                ->label(__('resources/advice_thread.tabs.all'))
                ->badge(fn () => $this->filterTableQuery($this->getRelationship()->getQuery())->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        if (Filament::getCurrentPanel()->getId() !== 'advisor') {
            return null;
        }

        /** @var \App\Models\Zaak $zaak */
        $zaak = $this->getOwnerRecord();

        $mineCount = $zaak->adviceThreads()
            ->where('advice_status', '!=', AdviceStatus::Concept)
            ->whereHas('assignedUsers', fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->count();

        return $mineCount === 0 ? 'all' : 'mine';
    }
}

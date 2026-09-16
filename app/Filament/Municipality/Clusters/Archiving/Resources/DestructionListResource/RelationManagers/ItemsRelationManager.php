<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\RelationManagers;

use App\Models\Archiving\DestructionList;
use App\Services\Archiving\EligibleZaakFinder;
use App\ValueObjects\Archiving\EligibleZaak;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('municipality/resources/destruction_list.items.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('municipality/resources/destruction_list.items.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/destruction_list.items.plural_label');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('zaaknummer')
                    ->label(__('municipality/resources/destruction_list.items.columns.zaaknummer.label'))
                    ->searchable(),
                TextColumn::make('naam_evenement')
                    ->label(__('municipality/resources/destruction_list.items.columns.naam_evenement.label')),
                TextColumn::make('zaaktype_naam')
                    ->label(__('municipality/resources/destruction_list.items.columns.zaaktype_naam.label')),
                TextColumn::make('archiefactiedatum')
                    ->label(__('municipality/resources/destruction_list.items.columns.archiefactiedatum.label'))
                    ->date(),
                TextColumn::make('selectielijst_categorie')
                    ->label(__('municipality/resources/destruction_list.items.columns.selectielijst_categorie.label'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bewaartermijn')
                    ->label(__('municipality/resources/destruction_list.items.columns.bewaartermijn.label'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('municipality/resources/destruction_list.items.columns.status.label'))
                    ->badge(),
                TextColumn::make('failure_reason')
                    ->label(__('municipality/resources/destruction_list.items.columns.failure_reason.label'))
                    ->limit(50)
                    ->toggleable(),
            ])
            ->headerActions([
                $this->getAddZakenAction(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    private function getAddZakenAction(): Action
    {
        return Action::make('add_zaken')
            ->label(__('municipality/resources/destruction_list.actions.add_zaken.label'))
            ->icon('heroicon-o-plus')
            ->visible(function (RelationManager $livewire): bool {
                /** @var DestructionList $list */
                $list = $livewire->getOwnerRecord();

                return auth()->user()->can('update', $list);
            })
            ->schema([
                CheckboxList::make('zaak_ids')
                    ->label(__('municipality/resources/destruction_list.actions.add_zaken.form.zaak_ids.label'))
                    ->helperText(__('municipality/resources/destruction_list.actions.add_zaken.form.zaak_ids.helper_text'))
                    ->options(function (RelationManager $livewire): array {
                        /** @var DestructionList $list */
                        $list = $livewire->getOwnerRecord();

                        return app(EligibleZaakFinder::class)->find($list->municipality)
                            ->mapWithKeys(fn (EligibleZaak $eligibleZaak): array => [
                                $eligibleZaak->zaak->id => implode(' — ', array_filter([
                                    $eligibleZaak->zaak->public_id,
                                    $eligibleZaak->zaak->reference_data->naam_evenement ?? null,
                                    $eligibleZaak->archiefactiedatum?->format('d-m-Y'),
                                ])),
                            ])
                            ->all();
                    })
                    ->required(),
            ])
            ->action(function (array $data, RelationManager $livewire) {
                /** @var DestructionList $list */
                $list = $livewire->getOwnerRecord();

                // Refetch instead of trusting the submitted ids, so the stored
                // snapshot is always based on fresh, validated OpenZaak data.
                $eligibleZaken = app(EligibleZaakFinder::class)->find($list->municipality)
                    ->keyBy(fn (EligibleZaak $eligibleZaak): string => $eligibleZaak->zaak->id);

                $added = 0;

                foreach ($data['zaak_ids'] as $zaakId) {
                    /** @var ?EligibleZaak $eligibleZaak */
                    $eligibleZaak = $eligibleZaken->get($zaakId);

                    if (! $eligibleZaak) {
                        continue;
                    }

                    $list->items()->firstOrCreate(
                        ['zgw_zaak_url' => $eligibleZaak->zaak->zgw_zaak_url],
                        $eligibleZaak->toItemAttributes(),
                    );

                    $added++;
                }

                Notification::make()
                    ->title(__('municipality/resources/destruction_list.actions.add_zaken.notification.title', ['count' => $added]))
                    ->success()
                    ->send();
            });
    }
}

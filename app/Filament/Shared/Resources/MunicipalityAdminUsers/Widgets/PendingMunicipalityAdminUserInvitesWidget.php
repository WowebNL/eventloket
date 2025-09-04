<?php

namespace App\Filament\Shared\Resources\MunicipalityAdminUsers\Widgets;

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingMunicipalityAdminUserInvitesWidget extends TableWidget
{
    public ?Municipality $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var \App\Models\Municipality $tenant */
                $tenant = Filament::getTenant();

                return MunicipalityInvite::query()
                    ->whereIn('role', [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin])
                    ->whereHas('municipalities', fn (Builder $query): Builder => $query->where('id', $tenant->id ?? $this->record->id));
            })
            ->modelLabel(__('municipality/resources/municipality_admin.widgets.pending_invites.label'))
            ->pluralModelLabel(__('municipality/resources/municipality_admin.widgets.pending_invites.plural_label'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('municipality/resources/municipality_admin.widgets.pending_invites.columns.email.label'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('municipality/resources/municipality_admin.widgets.pending_invites.columns.name.label'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('municipality/resources/municipality_admin.widgets.pending_invites.columns.role.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('municipality/resources/municipality_admin.widgets.pending_invites.columns.created_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                DeleteAction::make()
                    ->authorize('delete'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->authorizeIndividualRecords('delete'),
            ]);
    }
}

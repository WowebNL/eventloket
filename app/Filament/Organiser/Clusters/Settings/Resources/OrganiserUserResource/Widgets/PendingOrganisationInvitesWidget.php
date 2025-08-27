<?php

namespace App\Filament\Organiser\Clusters\Settings\Resources\OrganiserUserResource\Widgets;

use App\Models\OrganisationInvite;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingOrganisationInvitesWidget extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var \App\Models\Organisation $tenant */
                $tenant = Filament::getTenant();

                return OrganisationInvite::query()->where('organisation_id', $tenant->id);
            })
            ->modelLabel(__('organiser/resources/user.widgets.pending_invites.label'))
            ->pluralModelLabel(__('organiser/resources/user.widgets.pending_invites.plural_label'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('organiser/resources/user.widgets.pending_invites.columns.email.label'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('organiser/resources/user.widgets.pending_invites.columns.name.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('organiser/resources/user.widgets.pending_invites.columns.created_at.label'))
                    ->dateTime()
                    ->sortable(),
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

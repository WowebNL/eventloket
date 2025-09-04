<?php

namespace App\Filament\Shared\Resources\OrganiserUsers\Widgets;

use App\Models\Organisation;
use App\Models\OrganisationInvite;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingOrganisationInvitesWidget extends TableWidget
{
    public ?Organisation $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => OrganisationInvite::query()->where('organisation_id', $this->record->id))
            ->modelLabel(__('admin/resources/organisation.widgets.pending_invites.label'))
            ->pluralModelLabel(__('admin/resources/organisation.widgets.pending_invites.plural_label'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('admin/resources/organisation.widgets.pending_invites.columns.email.label'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('admin/resources/organisation.widgets.pending_invites.columns.name.label'))
                    ->searchable(),
                TextColumn::make('role')
                    ->label(__('admin/resources/organisation.widgets.pending_invites.columns.role.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin/resources/organisation.widgets.pending_invites.columns.created_at.label'))
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

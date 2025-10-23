<?php

namespace App\Filament\Shared\Resources\AdvisorUsers\Widgets;

use App\Models\Advisory;
use App\Models\AdvisoryInvite;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingAdvisoryInvitesWidget extends TableWidget
{
    public ?Advisory $record = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AdvisoryInvite::query()->where('advisory_id', $this->record->id))
            ->modelLabel(__('admin/resources/advisory.widgets.pending_invites.label'))
            ->pluralModelLabel(__('admin/resources/advisory.widgets.pending_invites.plural_label'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('admin/resources/advisory.widgets.pending_invites.columns.email.label'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('admin/resources/advisory.widgets.pending_invites.columns.email.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin/resources/advisory.widgets.pending_invites.columns.created_at.label'))
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

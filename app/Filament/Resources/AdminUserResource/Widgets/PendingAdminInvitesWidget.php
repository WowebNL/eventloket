<?php

namespace App\Filament\Resources\AdminUserResource\Widgets;

use App\Models\AdminInvite;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingAdminInvitesWidget extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AdminInvite::query())
            ->modelLabel(__('admin/resources/admin.widgets.pending_invites.label'))
            ->pluralModelLabel(__('admin/resources/admin.widgets.pending_invites.plural_label'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('admin/resources/admin.widgets.pending_invites.columns.email.label'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('admin/resources/admin.widgets.pending_invites.columns.name.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('admin/resources/admin.widgets.pending_invites.columns.created_at.label'))
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

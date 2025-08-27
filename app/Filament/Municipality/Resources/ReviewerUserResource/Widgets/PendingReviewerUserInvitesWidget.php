<?php

namespace App\Filament\Municipality\Resources\ReviewerUserResource\Widgets;

use App\Enums\Role;
use App\Models\MunicipalityInvite;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingReviewerUserInvitesWidget extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var \App\Models\Municipality $tenant */
                $tenant = Filament::getTenant();

                return MunicipalityInvite::query()
                    ->where('role', Role::Reviewer)
                    ->whereHas('municipalities', fn (Builder $query): Builder => $query->where('id', $tenant->id));
            })
            ->modelLabel(__('municipality/resources/user.widgets.pending_invites.label'))
            ->pluralModelLabel(__('municipality/resources/user.widgets.pending_invites.plural_label'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('municipality/resources/user.widgets.pending_invites.columns.email.label'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('municipality/resources/user.widgets.pending_invites.columns.name.label'))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label(__('municipality/resources/user.widgets.pending_invites.columns.created_at.label'))
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

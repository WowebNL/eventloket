<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewerUserResource\Pages\CreateReviewerUser;
use App\Filament\Resources\ReviewerUserResource\Pages\EditReviewerUser;
use App\Filament\Resources\ReviewerUserResource\Pages\ListReviewerUsers;
use App\Models\Users\ReviewerUser;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReviewerUserResource extends Resource
{
    protected static ?string $model = ReviewerUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 0;

    protected static ?string $tenantOwnershipRelationshipName = 'municipalities';

    public static function getModelLabel(): string
    {
        return __('admin/resources/user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/user.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/user.columns.name.label'))
                    ->description(fn (ReviewerUser $record): string => $record->email)
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReviewerUsers::route('/'),
            'create' => CreateReviewerUser::route('/create'),
            'edit' => EditReviewerUser::route('/{record}/edit'),
        ];
    }
}

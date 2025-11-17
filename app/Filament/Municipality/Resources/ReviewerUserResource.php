<?php

namespace App\Filament\Municipality\Resources;

use App\Filament\Municipality\Resources\ReviewerUserResource\Pages\CreateReviewerUser;
use App\Filament\Municipality\Resources\ReviewerUserResource\Pages\EditReviewerUser;
use App\Filament\Municipality\Resources\ReviewerUserResource\Pages\ListReviewerUsers;
use App\Filament\Shared\Resources\ReviewerUsers\Schemas\ReviewerUserForm;
use App\Filament\Shared\Resources\ReviewerUsers\Tables\ReviewerUsersTable;
use App\Models\Users\MunicipalityUser;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ReviewerUserResource extends Resource
{
    protected static ?string $model = MunicipalityUser::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Overig';

    protected static ?int $navigationSort = 0;

    protected static ?string $tenantOwnershipRelationshipName = 'municipalities';

    public static function getModelLabel(): string
    {
        return __('resources/reviewer_user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/reviewer_user.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return ReviewerUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReviewerUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        /** @phpstan-ignore-next-line */
        return parent::getEloquentQuery()->reviewers();
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

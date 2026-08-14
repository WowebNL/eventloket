<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions;

use App\Filament\Municipality\Clusters\Settings;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages\CreateMunicipalityFormQuestion;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages\EditMunicipalityFormQuestion;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityFormQuestions\Pages\ListMunicipalityFormQuestions;
use App\Filament\Shared\Resources\MunicipalityFormQuestions\Schemas\MunicipalityFormQuestionForm;
use App\Filament\Shared\Resources\MunicipalityFormQuestions\Tables\MunicipalityFormQuestionsTable;
use App\Models\Municipality;
use App\Models\MunicipalityFormQuestion;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MunicipalityFormQuestionResource extends Resource
{
    protected static ?string $model = MunicipalityFormQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $cluster = Settings::class;

    protected static ?int $navigationSort = 3;

    /**
     * Scopes the list to the current municipality and fills `municipality_id`
     * on create. Without this the panel would show and create records across
     * tenants.
     */
    protected static ?string $tenantOwnershipRelationshipName = 'municipality';

    public static function getModelLabel(): string
    {
        return __('resources/municipality_form_question.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/municipality_form_question.plural_label');
    }

    /**
     * On top of the policy: block the create page once the municipality has
     * hit its cap, so the route cannot be opened when the button is hidden.
     */
    public static function canCreate(): bool
    {
        if (! parent::canCreate()) {
            return false;
        }

        $tenant = Filament::getTenant();

        if (! $tenant instanceof Municipality) {
            return true;
        }

        return $tenant->formQuestions()->count() < MunicipalityFormQuestion::maxPerMunicipality();
    }

    public static function form(Schema $schema): Schema
    {
        return MunicipalityFormQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MunicipalityFormQuestionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMunicipalityFormQuestions::route('/'),
            'create' => CreateMunicipalityFormQuestion::route('/create'),
            'edit' => EditMunicipalityFormQuestion::route('/{record}/edit'),
        ];
    }
}

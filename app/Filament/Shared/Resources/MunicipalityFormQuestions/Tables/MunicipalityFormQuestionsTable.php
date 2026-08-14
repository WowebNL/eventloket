<?php

namespace App\Filament\Shared\Resources\MunicipalityFormQuestions\Tables;

use App\Filament\Shared\Resources\MunicipalityFormQuestions\Schemas\MunicipalityFormQuestionForm;
use App\Models\Municipality;
use App\Models\MunicipalityFormQuestion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MunicipalityFormQuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading(__('resources/municipality_form_question.plural_label'))
            ->description(__('resources/municipality_form_question.table.description', [
                'max' => MunicipalityFormQuestion::maxPerMunicipality(),
            ]))
            ->columns([
                TextColumn::make('order')
                    ->label(__('resources/municipality_form_question.columns.order.label'))
                    ->numeric()
                    ->sortable()
                    ->badge(),
                TextColumn::make('label')
                    ->label(__('resources/municipality_form_question.columns.label.label'))
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('type')
                    ->label(__('resources/municipality_form_question.columns.type.label'))
                    ->badge(),
                TextColumn::make('show_for_aanvraag_types')
                    ->label(__('resources/municipality_form_question.columns.show_for_aanvraag_types.label'))
                    ->badge()
                    ->state(fn (MunicipalityFormQuestion $record): array => self::aanvraagTypeLabels($record))
                    ->color(fn (string $state): string => $state === self::allAanvraagTypesLabel() ? 'gray' : 'primary'),
                IconColumn::make('is_required')
                    ->label(__('resources/municipality_form_question.columns.is_required.label'))
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label(__('resources/municipality_form_question.columns.is_active.label'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('resources/municipality_form_question.columns.updated_at.label'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('show_for_aanvraag_types')
                    ->label(__('resources/municipality_form_question.filters.show_for_aanvraag_types.label'))
                    ->options(MunicipalityFormQuestionForm::aanvraagTypeOptions())
                    // Een vraag zonder padselectie geldt voor ieder pad, dus
                    // die hoort ook bij elk gefilterd pad thuis. Zonder die
                    // arm zou het filter suggereren dat er bij een melding
                    // niets gevraagd wordt terwijl dat wel zo is.
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value)) {
                            return $query;
                        }

                        return $query->where(fn (Builder $query): Builder => $query
                            ->whereJsonContains('show_for_aanvraag_types', $value)
                            ->orWhereNull('show_for_aanvraag_types'));
                    }),
            ])
            ->defaultSort('order', 'asc')
            ->reorderable('order')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering
                        ? __('resources/municipality_form_question.actions.disable_reordering.label')
                        : __('resources/municipality_form_question.actions.enable_reordering.label')),
            )
            ->headerActions([
                CreateAction::make()
                    ->label(__('resources/municipality_form_question.actions.create.label'))
                    ->visible(fn ($livewire): bool => ! self::hasReachedLimit($livewire)),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading(__('resources/municipality_form_question.table.empty_heading'))
            ->emptyStateDescription(__('resources/municipality_form_question.table.empty_description'));
    }

    /**
     * The aanvraag paths this question applies to, as human labels. An empty
     * selection means every path, which reads better than an empty cell.
     *
     * @return list<string>
     */
    private static function aanvraagTypeLabels(MunicipalityFormQuestion $record): array
    {
        $types = $record->show_for_aanvraag_types ?? [];

        if (! is_array($types) || $types === []) {
            return [self::allAanvraagTypesLabel()];
        }

        $options = MunicipalityFormQuestionForm::aanvraagTypeOptions();

        return array_values(array_map(
            fn ($type): string => $options[(string) $type] ?? (string) $type,
            $types,
        ));
    }

    private static function allAanvraagTypesLabel(): string
    {
        return __('resources/municipality_form_question.columns.show_for_aanvraag_types.all');
    }

    /**
     * Whether the municipality in this context already has the maximum number
     * of questions. Used to hide the create button; the hard guard sits in
     * `MunicipalityFormQuestionObserver`.
     */
    public static function hasReachedLimit(object $livewire): bool
    {
        $municipality = self::municipalityFor($livewire);

        if (! $municipality instanceof Municipality) {
            return false;
        }

        return $municipality->formQuestions()->count() >= MunicipalityFormQuestion::maxPerMunicipality();
    }

    /**
     * The municipality these questions belong to: the owner record in the
     * admin panel's relation manager, the tenant in the municipality panel.
     */
    private static function municipalityFor(object $livewire): ?Municipality
    {
        if (method_exists($livewire, 'getOwnerRecord')) {
            $owner = $livewire->getOwnerRecord();

            return $owner instanceof Municipality ? $owner : null;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Municipality ? $tenant : null;
    }
}

<?php

namespace App\Filament\Municipality\Clusters\Archiving\Resources;

use App\Enums\DestructionListStatus;
use App\Enums\Role;
use App\Filament\Municipality\Clusters\Archiving;
use App\Filament\Municipality\Clusters\Archiving\Actions\RegenerateDestructionReportAction;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages\CreateDestructionList;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages\EditDestructionList;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages\ListDestructionLists;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\Pages\ViewDestructionList;
use App\Filament\Municipality\Clusters\Archiving\Resources\DestructionListResource\RelationManagers\ItemsRelationManager;
use App\Jobs\Archiving\StartDestructionListDeletion;
use App\Models\Archiving\DestructionList;
use App\Models\User;
use App\Notifications\DestructionListReadyForReview;
use App\Notifications\DestructionListReviewed;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DestructionListResource extends Resource
{
    protected static ?string $model = DestructionList::class;

    protected static ?string $cluster = Archiving::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trash';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('municipality/resources/destruction_list.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('municipality/resources/destruction_list.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('municipality/resources/destruction_list.columns.name.label'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('review_feedback')
                    ->label(__('municipality/resources/destruction_list.columns.review_feedback.label'))
                    ->disabled()
                    ->visibleOn('edit')
                    ->visible(fn (?DestructionList $record): bool => filled($record?->review_feedback)),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label(__('municipality/resources/destruction_list.columns.name.label')),
                TextEntry::make('status')
                    ->label(__('municipality/resources/destruction_list.columns.status.label'))
                    ->badge(),
                TextEntry::make('createdBy.name')
                    ->label(__('municipality/resources/destruction_list.columns.created_by.label')),
                TextEntry::make('created_at')
                    ->label(__('municipality/resources/destruction_list.columns.created_at.label'))
                    ->dateTime(),
                TextEntry::make('reviewedBy.name')
                    ->label(__('municipality/resources/destruction_list.columns.reviewed_by.label'))
                    ->visible(fn (DestructionList $record): bool => $record->reviewed_by_user_id !== null),
                TextEntry::make('reviewed_at')
                    ->label(__('municipality/resources/destruction_list.columns.reviewed_at.label'))
                    ->dateTime()
                    ->visible(fn (DestructionList $record): bool => $record->reviewed_at !== null),
                TextEntry::make('review_feedback')
                    ->label(__('municipality/resources/destruction_list.columns.review_feedback.label'))
                    ->columnSpanFull()
                    ->visible(fn (DestructionList $record): bool => filled($record->review_feedback)),
                TextEntry::make('confirmed_at')
                    ->label(__('municipality/resources/destruction_list.columns.confirmed_at.label'))
                    ->dateTime()
                    ->visible(fn (DestructionList $record): bool => $record->confirmed_at !== null),
                TextEntry::make('coordinator_name')
                    ->label(__('municipality/resources/destruction_list.columns.coordinator_name.label'))
                    ->visible(fn (DestructionList $record): bool => filled($record->coordinator_name)),
                TextEntry::make('destruction_method')
                    ->label(__('municipality/resources/destruction_list.columns.destruction_method.label'))
                    ->visible(fn (DestructionList $record): bool => filled($record->destruction_method)),
                TextEntry::make('report.batch_number')
                    ->label(__('municipality/resources/destruction_list.columns.report.label'))
                    ->url(fn (DestructionList $record): ?string => $record->destruction_report_id
                        ? DestructionReportResource::getUrl('view', ['record' => $record->destruction_report_id])
                        : null)
                    ->visible(fn (DestructionList $record): bool => $record->destruction_report_id !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('municipality/resources/destruction_list.columns.name.label'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('municipality/resources/destruction_list.columns.status.label'))
                    ->badge(),
                TextColumn::make('items_count')
                    ->label(__('municipality/resources/destruction_list.columns.items_count.label'))
                    ->counts('items'),
                TextColumn::make('createdBy.name')
                    ->label(__('municipality/resources/destruction_list.columns.created_by.label')),
                TextColumn::make('created_at')
                    ->label(__('municipality/resources/destruction_list.columns.created_at.label'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDestructionLists::route('/'),
            'create' => CreateDestructionList::route('/create'),
            'view' => ViewDestructionList::route('/{record}'),
            'edit' => EditDestructionList::route('/{record}/edit'),
        ];
    }

    /**
     * The workflow actions of the destruction process, shown in the header
     * of the view and edit pages. Visibility is handled by the
     * DestructionListPolicy abilities.
     *
     * @return array<Action>
     */
    public static function getWorkflowActions(): array
    {
        return [
            static::getSubmitForReviewAction(),
            static::getApproveAction(),
            static::getRequestChangesAction(),
            static::getConfirmDestructionAction(),
            static::getRetryAction(),
            RegenerateDestructionReportAction::make(),
        ];
    }

    public static function getSubmitForReviewAction(): Action
    {
        return Action::make('submit_for_review')
            ->label(__('municipality/resources/destruction_list.actions.submit_for_review.label'))
            ->icon('heroicon-o-paper-airplane')
            ->visible(fn (DestructionList $record): bool => auth()->user()->can('submitForReview', $record))
            ->requiresConfirmation()
            ->modalDescription(__('municipality/resources/destruction_list.actions.submit_for_review.modal_description'))
            ->action(function (DestructionList $record, Page $livewire) {
                if (! $record->items()->exists()) {
                    Notification::make()
                        ->title(__('municipality/resources/destruction_list.actions.submit_for_review.empty_notification.title'))
                        ->danger()
                        ->send();

                    return;
                }

                $record->transitionTo(DestructionListStatus::ReadyToReview, [
                    'review_feedback' => null,
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                ]);

                $record->municipality->users()
                    ->where('role', Role::ArchiveReviewer)
                    ->get()
                    ->each(fn (User $reviewer) => $reviewer->notify(new DestructionListReadyForReview($record)));

                Notification::make()
                    ->title(__('municipality/resources/destruction_list.actions.submit_for_review.notification.title'))
                    ->success()
                    ->send();

                $livewire->redirect(static::getUrl('view', ['record' => $record]));
            });
    }

    public static function getApproveAction(): Action
    {
        return Action::make('approve')
            ->label(__('municipality/resources/destruction_list.actions.approve.label'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (DestructionList $record): bool => auth()->user()->can('review', $record))
            ->requiresConfirmation()
            ->modalDescription(__('municipality/resources/destruction_list.actions.approve.modal_description'))
            ->action(function (DestructionList $record) {
                $record->transitionTo(DestructionListStatus::Approved, [
                    'reviewed_by_user_id' => auth()->id(),
                    'reviewed_at' => now(),
                    'approved_at' => now(),
                ]);

                $record->createdBy?->notify(new DestructionListReviewed($record, approved: true));

                Notification::make()
                    ->title(__('municipality/resources/destruction_list.actions.approve.notification.title'))
                    ->success()
                    ->send();
            });
    }

    public static function getRequestChangesAction(): Action
    {
        return Action::make('request_changes')
            ->label(__('municipality/resources/destruction_list.actions.request_changes.label'))
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('warning')
            ->visible(fn (DestructionList $record): bool => auth()->user()->can('review', $record))
            ->schema([
                Textarea::make('review_feedback')
                    ->label(__('municipality/resources/destruction_list.actions.request_changes.form.review_feedback.label'))
                    ->required(),
            ])
            ->action(function (array $data, DestructionList $record) {
                $record->transitionTo(DestructionListStatus::ChangesRequested, [
                    'review_feedback' => $data['review_feedback'],
                    'reviewed_by_user_id' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                $record->createdBy?->notify(new DestructionListReviewed($record, approved: false));

                Notification::make()
                    ->title(__('municipality/resources/destruction_list.actions.request_changes.notification.title'))
                    ->success()
                    ->send();
            });
    }

    public static function getConfirmDestructionAction(): Action
    {
        return Action::make('confirm_destruction')
            ->label(__('municipality/resources/destruction_list.actions.confirm_destruction.label'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn (DestructionList $record): bool => auth()->user()->can('confirm', $record))
            ->requiresConfirmation()
            ->modalHeading(__('municipality/resources/destruction_list.actions.confirm_destruction.modal_heading'))
            ->modalDescription(__('municipality/resources/destruction_list.actions.confirm_destruction.modal_description'))
            ->schema([
                TextInput::make('confirmation')
                    ->label(__('municipality/resources/destruction_list.actions.confirm_destruction.form.confirmation.label'))
                    ->required()
                    ->rule(fn (DestructionList $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                        if ($value !== $record->name) {
                            $fail(__('municipality/resources/destruction_list.actions.confirm_destruction.form.confirmation.validation'));
                        }
                    }),
                TextInput::make('coordinator_function')
                    ->label(__('municipality/resources/destruction_list.actions.confirm_destruction.form.coordinator_function.label'))
                    ->required()
                    ->default(fn (): string => auth()->user()->role->getLabel()),
                TextInput::make('destruction_method')
                    ->label(__('municipality/resources/destruction_list.actions.confirm_destruction.form.destruction_method.label'))
                    ->required()
                    ->default(config('archiving.destruction_method')),
            ])
            ->action(function (array $data, DestructionList $record) {
                $record->transitionTo(DestructionListStatus::Deleting, [
                    'confirmed_at' => now(),
                    'coordinator_name' => auth()->user()->name,
                    'coordinator_function' => $data['coordinator_function'],
                    'destruction_method' => $data['destruction_method'],
                ]);

                StartDestructionListDeletion::dispatch($record);

                Notification::make()
                    ->title(__('municipality/resources/destruction_list.actions.confirm_destruction.notification.title'))
                    ->success()
                    ->send();
            });
    }

    public static function getRetryAction(): Action
    {
        return Action::make('retry')
            ->label(__('municipality/resources/destruction_list.actions.retry.label'))
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->visible(fn (DestructionList $record): bool => auth()->user()->can('retry', $record))
            ->requiresConfirmation()
            ->modalDescription(__('municipality/resources/destruction_list.actions.retry.modal_description'))
            ->action(function (DestructionList $record) {
                $record->transitionTo(DestructionListStatus::Deleting);

                StartDestructionListDeletion::dispatch($record);

                Notification::make()
                    ->title(__('municipality/resources/destruction_list.actions.retry.notification.title'))
                    ->success()
                    ->send();
            });
    }
}

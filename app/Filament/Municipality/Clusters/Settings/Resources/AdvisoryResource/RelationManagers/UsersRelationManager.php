<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\AdvisoryResource\RelationManagers;

use App\Mail\AdvisoryInviteMail;
use App\Models\Advisory;
use App\Models\AdvisoryInvite;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin/resources/advisory.user.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin/resources/advisory.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin/resources/advisory.user.plural_label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin/resources/user.columns.name.label'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin/resources/user.columns.name.label')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make(),
                Action::make('invite')
                    ->label(__('admin/resources/advisory.actions.invite.label'))
                    ->icon('heroicon-o-envelope')
                    ->modalSubmitActionLabel(__('admin/resources/advisory.actions.invite.modal_submit_action_label'))
                    ->modalWidth(Width::Medium)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin/resources/advisory.actions.invite.form.name.label'))
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('admin/resources/advisory.actions.invite.form.email.label'))
                            ->email()
                            ->required()
                            ->unique(table: User::class)
                            ->rules([
                                fn () => function (string $attribute, $value, Closure $fail) {
                                    /** @var Advisory $advisory */
                                    $advisory = $this->ownerRecord;

                                    if (AdvisoryInvite::where('advisory_id', $advisory->id)->where('email', $value)->exists()) {
                                        $fail(__('admin/resources/advisory.actions.invite.form.email.validation.already_invited'));
                                    }
                                },
                            ])
                            ->maxLength(255),
                    ])
                    ->action(function ($data) {
                        /** @var Advisory $advisory */
                        $advisory = $this->ownerRecord;

                        $advisoryInvite = AdvisoryInvite::create([
                            'advisory_id' => $advisory->id,
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'token' => Str::uuid(),
                        ]);

                        Mail::to($advisoryInvite->email)
                            ->send(new AdvisoryInviteMail($advisoryInvite));

                        Notification::make()
                            ->title(__('admin/resources/advisory.actions.invite.notification.title'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}

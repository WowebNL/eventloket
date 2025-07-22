<?php

namespace App\Filament\Clusters\AdminSettings\Resources\AdvisoryResource\RelationManagers;

use App\Mail\AdvisoryInviteMail;
use App\Models\AdvisoryInvite;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
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

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make(),
                Tables\Actions\Action::make('invite')
                    ->label(__('admin/resources/advisory.actions.invite.label'))
                    ->icon('heroicon-o-envelope')
                    ->modalSubmitActionLabel(__('admin/resources/advisory.actions.invite.modal_submit_action_label'))
                    ->modalWidth(MaxWidth::Medium)
                    ->form([
                        TextInput::make('email')
                            ->label(__('admin/resources/advisory.actions.invite.form.email.label'))
                            ->email()
                            ->required(),
                    ])
                    ->action(function ($data) {
                        /** @var \App\Models\Advisory $advisory */
                        $advisory = $this->ownerRecord;

                        $advisoryInvite = AdvisoryInvite::create([
                            'advisory_id' => $advisory->id,
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}

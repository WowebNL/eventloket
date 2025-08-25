<?php

namespace App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages;

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource;
use App\Mail\MunicipalityInviteMail;
use App\Models\MunicipalityInvite;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListMunicipalityAdminUsers extends ListRecords
{
    protected static string $resource = MunicipalityAdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('admin/resources/admin.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('admin/resources/admin.actions.invite.modal_submit_action_label'))
                ->modalWidth(Width::Medium)
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin/resources/admin.actions.invite.form.name.label'))
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('admin/resources/admin.actions.invite.form.email.label'))
                        ->email()
                        ->required()
                        ->unique(table: User::class)
                        ->maxLength(255),
                    Select::make('municipalities')
                        ->multiple()
                        ->options(function () {
                            /** @var \App\Models\Users\MunicipalityAdminUser|\App\Models\Users\ReviewerMunicipalityAdminUser $user */
                            $user = auth()->user();

                            return $user->municipalities->pluck('name', 'id');
                        })
                        ->label(__('admin/resources/admin.actions.invite.form.municipalities.label'))
                        ->preload()
                        ->required(),
                    Checkbox::make('can_review')
                        ->label(__('admin/resources/admin.actions.invite.form.can_review.label'))
                        ->helperText(__('admin/resources/admin.actions.invite.form.can_review.helper_text')),
                ])
                ->action(function ($data) {

                    $municipalityInvite = MunicipalityInvite::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => $data['can_review'] ? Role::ReviewerMunicipalityAdmin : Role::MunicipalityAdmin,
                        'token' => Str::uuid(),
                    ]);

                    if (isset($data['municipalities']) && $data['municipalities']) {
                        $municipalityInvite->municipalities()->attach($data['municipalities']);
                    }

                    Mail::to($municipalityInvite->email)
                        ->send(new MunicipalityInviteMail($municipalityInvite));

                    Notification::make()
                        ->title(__('admin/resources/admin.actions.invite.notification.title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

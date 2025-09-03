<?php

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Filament\Actions\PendingInvitesAction;
use App\Filament\Resources\AdminUserResource;
use App\Mail\AdminInviteMail;
use App\Models\AdminInvite;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListAdminUsers extends ListRecords
{
    protected static string $resource = AdminUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PendingInvitesAction::make()
                ->modalHeading(__('admin/resources/admin.widgets.pending_invites.heading'))
                ->widget(AdminUserResource\Widgets\PendingAdminInvitesWidget::class),
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
                        ->rules([
                            fn () => function (string $attribute, $value, Closure $fail) {
                                if (AdminInvite::where('email', $value)->exists()) {
                                    $fail(__('admin/resources/admin.actions.invite.form.email.validation.already_invited'));
                                }
                            },
                        ])
                        ->maxLength(255),
                ])
                ->action(function ($data) {

                    $adminInvite = AdminInvite::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'token' => Str::uuid(),
                    ]);

                    Mail::to($adminInvite->email)
                        ->send(new AdminInviteMail($adminInvite));

                    Notification::make()
                        ->title(__('admin/resources/admin.actions.invite.notification.title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

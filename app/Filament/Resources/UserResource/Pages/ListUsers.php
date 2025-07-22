<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\ReviewerInviteMail;
use App\Models\ReviewerInvite;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invite')
                ->label(__('admin/resources/user.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('admin/resources/user.actions.invite.modal_submit_action_label'))
                ->modalWidth(MaxWidth::Medium)
                ->form([
                    TextInput::make('email')
                        ->label(__('admin/resources/user.actions.invite.form.email.label'))
                        ->email()
                        ->required(),
                ])
                ->action(function ($data) {
                    /** @var \App\Models\Organisation $tenant */
                    $tenant = Filament::getTenant();

                    $reviewerInvite = ReviewerInvite::create([
                        'municipality_id' => $tenant->id,
                        'email' => $data['email'],
                        'token' => Str::uuid(),
                    ]);

                    Mail::to($reviewerInvite->email)
                        ->send(new ReviewerInviteMail($reviewerInvite));

                    Notification::make()
                        ->title(__('admin/resources/user.actions.invite.notification.title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

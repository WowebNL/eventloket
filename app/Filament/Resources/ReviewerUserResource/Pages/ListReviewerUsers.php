<?php

namespace App\Filament\Resources\ReviewerUserResource\Pages;

use App\Enums\Role;
use App\Filament\Resources\ReviewerUserResource;
use App\Mail\AdminInviteMail;
use App\Models\AdminInvite;
use App\Models\Organisation;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ListReviewerUsers extends ListRecords
{
    protected static string $resource = ReviewerUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('admin/resources/user.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('admin/resources/user.actions.invite.modal_submit_action_label'))
                ->modalWidth(Width::Medium)
                ->visible(fn (): bool => in_array(auth()->user()->role, [Role::Admin, Role::MunicipalityAdmin]))
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin/resources/user.actions.invite.form.name.label'))
                        ->maxLength(255),
                    TextInput::make('email')
                        ->label(__('admin/resources/user.actions.invite.form.email.label'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                ])
                ->action(function ($data) {
                    /** @var Organisation $tenant */
                    $tenant = Filament::getTenant();

                    $adminInvite = AdminInvite::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'role' => Role::Reviewer,
                        'token' => Str::uuid(),
                    ]);

                    $adminInvite->municipalities()->attach($tenant->id);

                    Mail::to($adminInvite->email)
                        ->send(new AdminInviteMail($adminInvite));

                    Notification::make()
                        ->title(__('admin/resources/user.actions.invite.notification.title'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

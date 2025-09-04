<?php

namespace App\Filament\Shared\Resources\ReviewerUsers\Actions;

use App\Enums\Role;
use App\Filament\Shared\Actions\InviteAction;
use App\Mail\MunicipalityInviteMail;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ReviewerUserInviteAction
{
    public static function make(?Municipality $municipality = null): Action
    {
        return InviteAction::make()
            ->modelLabel(__('resources/reviewer_user.label'))
            ->visible(fn (): bool => in_array(auth()->user()->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]))
            ->schema([
                TextInput::make('name')
                    ->label(__('admin/resources/user.actions.invite.form.name.label'))
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(__('admin/resources/user.actions.invite.form.email.label'))
                    ->email()
                    ->required()
                    ->unique(table: User::class)
                    ->rules([
                        fn () => function (string $attribute, $value, Closure $fail) {
                            if (MunicipalityInvite::where('email', $value)->exists()) {
                                $fail(__('admin/resources/user.actions.invite.form.email.validation.already_invited'));
                            }
                        },
                    ])
                    ->maxLength(255),
            ])
            ->action(function ($data) use ($municipality) {
                /** @var Municipality $tenant */
                $tenant = Filament::getTenant();

                if (! $tenant) {
                    $tenant = $municipality;
                }

                $municipalityInvite = MunicipalityInvite::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => Role::Reviewer,
                    'token' => Str::uuid(),
                ]);

                $municipalityInvite->municipalities()->attach($tenant->id);

                Mail::to($municipalityInvite->email)
                    ->send(new MunicipalityInviteMail($municipalityInvite));

                Notification::make()
                    ->title(__('admin/resources/user.actions.invite.notification.title'))
                    ->success()
                    ->send();
            });
    }
}

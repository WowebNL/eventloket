<?php

namespace App\Filament\Shared\Resources\AdvisorUsers\Actions;

use App\Enums\AdvisoryRole;
use App\Filament\Shared\Actions\InviteAction;
use App\Mail\AdvisoryInviteMail;
use App\Models\Advisory;
use App\Models\AdvisoryInvite;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdvisorUserInviteAction
{
    public static function make(?Advisory $advisory = null): Action
    {
        return InviteAction::make()
            ->modelLabel(__('resources/advisor_user.label'))
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
                        fn () => function (string $attribute, $value, Closure $fail) use ($advisory) {
                            $advisory = $advisory ?? static::getAdvisory();

                            if (AdvisoryInvite::where('advisory_id', $advisory->id)->where('email', $value)->exists()) {
                                $fail(__('admin/resources/advisory.actions.invite.form.email.validation.already_invited'));
                            }
                        },
                    ])
                    ->maxLength(255),
                Checkbox::make('makeAdmin')
                    ->label(__('admin/resources/advisory.actions.invite.form.make_admin.label'))
                    ->helperText(__('admin/resources/advisory.actions.invite.form.make_admin.helper_text')),
            ])
            ->action(function ($data) use ($advisory) {
                $advisory = $advisory ?? static::getAdvisory();

                $advisoryInvite = AdvisoryInvite::create([
                    'advisory_id' => $advisory->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'role' => $data['makeAdmin'] ? AdvisoryRole::Admin : AdvisoryRole::Member,
                    'token' => Str::uuid(),
                ]);

                Mail::to($advisoryInvite->email)
                    ->send(new AdvisoryInviteMail($advisoryInvite));

                Notification::make()
                    ->title(__('admin/resources/advisory.actions.invite.notification.title'))
                    ->success()
                    ->send();
            });
    }

    protected static function getAdvisory(): Advisory
    {
        if (Filament::hasTenancy()) {
            /** @var Advisory $tenant */
            $tenant = Filament::getTenant();

            return $tenant;
        }

        throw new \Exception('No advisory context available');
    }
}

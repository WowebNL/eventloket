<?php

namespace App\Filament\Municipality\Resources\ReviewerUserResource\Pages;

use App\Enums\Role;
use App\Filament\Actions\PendingInvitesAction;
use App\Filament\Municipality\Resources\ReviewerUserResource;
use App\Filament\Municipality\Resources\ReviewerUserResource\Widgets\PendingReviewerUserInvitesWidget;
use App\Mail\MunicipalityInviteMail;
use App\Models\MunicipalityInvite;
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
            PendingInvitesAction::make()
                ->modalHeading(__('municipality/resources/user.widgets.pending_invites.heading'))
                ->widget(PendingReviewerUserInvitesWidget::class),
            Action::make('invite')
                ->label(__('admin/resources/user.actions.invite.label'))
                ->icon('heroicon-o-envelope')
                ->modalSubmitActionLabel(__('admin/resources/user.actions.invite.modal_submit_action_label'))
                ->modalWidth(Width::Medium)
                ->visible(fn (): bool => in_array(auth()->user()->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]))
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
                }),
        ];
    }
}

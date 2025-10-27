<?php

namespace App\Filament\Shared\Pages;

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Models\NotificationPreference;
use App\Notifications\AdviceReminder;
use App\Notifications\AssignedToAdviceThread;
use App\Notifications\NewAdviceThread;
use App\Notifications\NewAdviceThreadMessage;
use App\Notifications\NewOrganiserThread;
use App\Notifications\NewOrganiserThreadMessage;
use App\Notifications\NewZaakDocument;
use App\Notifications\Result;
use App\Notifications\ZaakStatusChanged;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;

class EditProfile extends \Filament\Auth\Pages\EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFirstNameFormComponent(),
                $this->getLastNameFormComponent(),
                $this->getEmailFormComponent(),
                /** @phpstan-ignore-next-line */
                $this->getPasswordFormComponent()->helperText(app()->isProduction() ? __('organiser/pages/auth/register.form.password.helper_text') : null),
                $this->getPasswordConfirmationFormComponent(),
                $this->getNotificationPreferencesComponent(),
            ]);
    }

    public static function getFirstNameFormComponent(): Component
    {
        return TextInput::make('first_name')
            ->label(__('shared/pages/edit-profile.form.first_name.label'))
            ->required()
            ->maxLength(255)
            ->autofocus();
    }

    public static function getLastNameFormComponent(): Component
    {
        return TextInput::make('last_name')
            ->label(__('shared/pages/edit-profile.form.last_name.label'))
            ->required()
            ->maxLength(255);
    }

    protected function getNotifications(): array
    {
        $user = auth()->user();

        return match ($user->role) {
            Role::Admin => [],
            Role::MunicipalityAdmin => [],
            Role::ReviewerMunicipalityAdmin,
            Role::Reviewer => [
                NewAdviceThreadMessage::class,
                NewOrganiserThread::class,
                NewOrganiserThreadMessage::class,
                NewZaakDocument::class,
                Result::class,
            ],
            Role::Advisor => [
                /** @phpstan-ignore-next-line */
                ...($user->advisories()->wherePivot('role', AdvisoryRole::Admin)->exists() ? [NewAdviceThread::class] : []),
                AssignedToAdviceThread::class,
                NewAdviceThreadMessage::class,
                AdviceReminder::class,
                NewZaakDocument::class,
            ],
            Role::Organiser => [
                NewOrganiserThread::class,
                NewOrganiserThreadMessage::class,
                ZaakStatusChanged::class,
                NewZaakDocument::class,
                Result::class,
            ],
            default => throw new \Exception('Unknown role'),
        };
    }

    protected function getNotificationPreferencesComponent(): Component
    {
        $schema = [];

        foreach ($this->getNotifications() as $notificationClass) {
            $schema[] = CheckboxList::make("{$notificationClass}_channels")
                ->label($notificationClass::getLabel())
                ->options([
                    'mail' => __('shared/pages/edit-profile.form.notification_preferences.options.mail'),
                    'database' => __('shared/pages/edit-profile.form.notification_preferences.options.database'),
                ])
                ->default(['mail', 'database'])
                ->afterStateHydrated(function (CheckboxList $component) use ($notificationClass) {
                    $preference = auth()->user()->notificationPreferences()
                        ->where('notification_class', $notificationClass)
                        ->first();

                    $component->state($preference ? $preference->channels : ['mail', 'database']);
                });
        }

        return Fieldset::make(__('shared/pages/edit-profile.form.notification_preferences.label'))
            ->schema($schema)
            ->hidden(fn () => count($this->getNotifications()) === 0);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle notification preferences separately
        $this->saveNotificationPreferences($data);

        // Remove notification preferences from the main data array
        foreach ($this->getNotifications() as $notificationClass) {
            unset($data["{$notificationClass}_channels"]);
        }

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function saveNotificationPreferences(array $data): void
    {
        $user = auth()->user();

        foreach ($this->getNotifications() as $notificationClass) {
            if (isset($data["{$notificationClass}_channels"])) {
                NotificationPreference::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'notification_class' => $notificationClass,
                    ],
                    [
                        'channels' => $data["{$notificationClass}_channels"] ?? [],
                    ]
                );
            }
        }
    }
}

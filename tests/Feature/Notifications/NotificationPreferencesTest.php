
<?php

use App\Enums\Role;
use App\Models\Advisory;
use App\Models\NotificationPreference;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\AdviceReminder;
use App\Notifications\NewAdviceThread;
use App\Notifications\NewAdviceThreadMessage;
use App\Notifications\NewOrganiserThread;
use App\Notifications\NewOrganiserThreadMessage;
use App\Notifications\Result;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;

describe('User Role Based Notification Preferences', function () {
    beforeEach(function () {
        Notification::fake();
    });

    test('admin users cannot see notification preferences', function () {
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(route('filament.admin.auth.profile'))
            ->assertOk()
            ->assertDontSee(__('shared/pages/edit-profile.form.notification_preferences.label'));
    });

    test('municipality admin users cannot see notification preferences', function () {
        $municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);

        $this->actingAs($municipalityAdmin);

        Filament::setCurrentPanel(Filament::getPanel('municipality'));

        $this->get(route('filament.municipality.auth.profile'))
            ->assertOk()
            ->assertDontSee(__('shared/pages/edit-profile.form.notification_preferences.label'));
    });

    test('advisor users can see correct notification preferences', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $this->actingAs($advisor);

        Filament::setCurrentPanel(Filament::getPanel('advisor'));

        $response = $this->get(route('filament.advisor.auth.profile'))
            ->assertOk()
            ->assertSee(__('shared/pages/edit-profile.form.notification_preferences.label'))
            ->assertDontSee(NewAdviceThread::getLabel())
            ->assertSee(NewAdviceThreadMessage::getLabel())
            ->assertDontSee(NewOrganiserThread::getLabel())
            ->assertDontSee(NewOrganiserThreadMessage::getLabel())
            ->assertDontSee(Result::getLabel());
    });

    test('reviewer users can see all review-related notification preferences', function () {
        $reviewer = User::factory()->create(['role' => Role::Reviewer]);

        $this->actingAs($reviewer);

        Filament::setCurrentPanel(Filament::getPanel('municipality'));

        $this->get(route('filament.municipality.auth.profile'))
            ->assertOk()
            ->assertSee(__('shared/pages/edit-profile.form.notification_preferences.label'))
            ->assertSee(NewAdviceThreadMessage::getLabel())
            ->assertSee(NewOrganiserThread::getLabel())
            ->assertSee(NewOrganiserThreadMessage::getLabel())
            ->assertDontSee(NewAdviceThread::getLabel())
            ->assertDontSee(Result::getLabel());
    });

    test('reviewer municipality admin users can see all review-related notification preferences', function () {
        $reviewerMunicipalityAdmin = User::factory()->create(['role' => Role::ReviewerMunicipalityAdmin]);

        $this->actingAs($reviewerMunicipalityAdmin);

        Filament::setCurrentPanel(Filament::getPanel('municipality'));

        $this->get(route('filament.municipality.auth.profile'))
            ->assertOk()
            ->assertSee(__('shared/pages/edit-profile.form.notification_preferences.label'))
            ->assertSee(NewAdviceThreadMessage::getLabel())
            ->assertSee(NewOrganiserThread::getLabel())
            ->assertSee(NewOrganiserThreadMessage::getLabel())
            ->assertDontSee(NewAdviceThread::getLabel())
            ->assertDontSee(Result::getLabel());
    });

    test('organiser users can see organiser-related notification preferences', function () {
        $organisation = Organisation::factory()->create();
        $organiser = User::factory()->create(['role' => Role::Organiser]);

        $this->actingAs($organiser);

        Filament::setCurrentPanel(Filament::getPanel('organiser'));
        Filament::setTenant($organisation);

        $this->get(route('filament.organiser.auth.profile', ['tenant' => $organisation]))
            ->assertOk()
            ->assertSee(__('shared/pages/edit-profile.form.notification_preferences.label'))
            ->assertSee(NewOrganiserThread::getLabel())
            ->assertSee(NewOrganiserThreadMessage::getLabel())
            ->assertSee(Result::getLabel())
            ->assertDontSee(NewAdviceThread::getLabel())
            ->assertDontSee(NewAdviceThreadMessage::getLabel());
    });
});

describe('Notification Preferences Management', function () {
    beforeEach(function () {
        Notification::fake();
    });

    test('advisor can update their notification preferences', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        $advisory = Advisory::factory()->create();
        $advisory->users()->attach($advisor, ['role' => 'admin']);

        $this->actingAs($advisor);

        Filament::setCurrentPanel(Filament::getPanel('advisor'));

        // Test updating via Livewire component
        $this->livewire(\App\Filament\Shared\Pages\EditProfile::class)
            ->fillForm([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'App\Notifications\NewAdviceThread_channels' => ['database'],
                'App\Notifications\NewAdviceThreadMessage_channels' => ['mail'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Verify preferences were saved
        $adviceThreadPreference = NotificationPreference::where([
            'user_id' => $advisor->id,
            'notification_class' => NewAdviceThread::class,
        ])->first();

        $adviceMessagePreference = NotificationPreference::where([
            'user_id' => $advisor->id,
            'notification_class' => NewAdviceThreadMessage::class,
        ])->first();

        expect($adviceThreadPreference->channels)->toBe(['database'])
            ->and($adviceMessagePreference->channels)->toBe(['mail']);
    });

    test('organiser can update their notification preferences', function () {
        $organisation = Organisation::factory()->create();
        $organiser = User::factory()->create(['role' => Role::Organiser]);

        $this->actingAs($organiser);

        Filament::setCurrentPanel(Filament::getPanel('organiser'));
        Filament::setTenant($organisation);

        $this->livewire(\App\Filament\Organiser\Pages\EditProfile::class)
            ->fillForm([
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane@example.com',
                'phone' => '1234567890',
                'App\Notifications\NewOrganiserThread_channels' => ['mail'],
                'App\Notifications\NewOrganiserThreadMessage_channels' => ['database'],
                'App\Notifications\Result_channels' => [],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Verify preferences were saved
        $threadPreference = NotificationPreference::where([
            'user_id' => $organiser->id,
            'notification_class' => NewOrganiserThread::class,
        ])->first();

        $messagePreference = NotificationPreference::where([
            'user_id' => $organiser->id,
            'notification_class' => NewOrganiserThreadMessage::class,
        ])->first();

        $resultPreference = NotificationPreference::where([
            'user_id' => $organiser->id,
            'notification_class' => Result::class,
        ])->first();

        expect($threadPreference->channels)->toBe(['mail'])
            ->and($messagePreference->channels)->toBe(['database'])
            ->and($resultPreference->channels)->toBe([]);
    });

    test('loads existing notification preferences correctly', function () {
        $advisor = User::factory()->create(['role' => Role::Advisor]);

        // Create existing preferences
        NotificationPreference::create([
            'user_id' => $advisor->id,
            'notification_class' => AdviceReminder::class,
            'channels' => ['mail'],
        ]);

        NotificationPreference::create([
            'user_id' => $advisor->id,
            'notification_class' => NewAdviceThreadMessage::class,
            'channels' => ['database'],
        ]);

        $this->actingAs($advisor);

        Filament::setCurrentPanel(Filament::getPanel('advisor'));

        $this->livewire(\App\Filament\Shared\Pages\EditProfile::class)
            ->assertFormSet([
                'App\Notifications\AdviceReminder_channels' => ['mail'],
                'App\Notifications\NewAdviceThreadMessage_channels' => ['database'],
            ]);
    });
});

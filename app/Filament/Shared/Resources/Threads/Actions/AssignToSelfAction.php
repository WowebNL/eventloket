<?php

namespace App\Filament\Shared\Resources\Threads\Actions;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Models\Threads\AdviceThread;
use App\Models\Users\AdvisorUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Component;

class AssignToSelfAction
{
    public static function make(): Action
    {
        return Action::make('assign_to_self')
            ->label(__('resources/advice_thread.actions.assign_to_self.label'))
            ->visible(function (AdviceThread $record) {
                if (auth()->user()->role === Role::Advisor) {
                    /** @var AdvisorUser $user */
                    $user = auth()->user();

                    return $user->canAccessAdvisory($record->advisory_id) && $record->assignedUsers->doesntContain(auth()->id());
                }

                return false;
            })
            ->action(function (AdviceThread $record, Component $livewire) {
                auth()->user()->can('assign-advisor', [$record, auth()->user()]);

                $record->assignedUsers()->attach(auth()->id());

                if ($record->advice_status === AdviceStatus::Asked) {
                    $record->update(['advice_status' => AdviceStatus::InProgress]);
                }

                Notification::make()
                    ->title('Je bent toegewezen aan deze adviesvraag')
                    ->success()
                    ->send();

                $livewire->dispatch('thread-updated');
            });
    }
}

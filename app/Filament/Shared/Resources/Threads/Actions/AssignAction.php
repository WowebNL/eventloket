<?php

namespace App\Filament\Shared\Resources\Threads\Actions;

use App\Enums\AdviceStatus;
use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Models\Advisory;
use App\Models\Threads\AdviceThread;
use App\Models\Users\AdvisorUser;
use App\Notifications\AssignedToAdviceThread;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\Width;
use Livewire\Component;

class AssignAction
{
    public static function make(): Action
    {
        return Action::make('assign')
            ->label(__('resources/advice_thread.actions.assign.label'))
            ->modalWidth(Width::Small)
            ->visible(function (AdviceThread $record) {
                /** @var Advisory $tenant */
                $tenant = Filament::getTenant();

                /** @var AdvisorUser $user */
                $user = auth()->user();

                if ($user->role === Role::Advisor) {
                    return $user->canAccessAdvisory($record->advisory_id, as: AdvisoryRole::Admin);
                }

                return false;
            })
            ->schema([
                Select::make('advisors')
                    ->label(__('resources/advice_thread.actions.assign.form.advisors.label'))
                    ->required()
                    ->multiple()
                    ->preload()
                    ->options(function () {
                        /** @var Advisory $tenant */
                        $tenant = Filament::getTenant();

                        return $tenant->users->pluck('name', 'id');
                    }),
            ])->action(function (AdviceThread $record, array $data, Component $livewire) {
                $record->assignedUsers()->sync($data['advisors']);

                /** @var Advisory $tenant */
                $tenant = Filament::getTenant();

                /** @var AdvisorUser $advisor */
                foreach ($tenant->users->whereIn('id', $data['advisors']) as $advisor) {
                    $advisor->notify(new AssignedToAdviceThread($record));
                }

                if ($record->advice_status === AdviceStatus::Asked) {
                    $record->update(['advice_status' => AdviceStatus::InProgress]);
                }

                $livewire->dispatch('thread-updated');
            });
    }
}

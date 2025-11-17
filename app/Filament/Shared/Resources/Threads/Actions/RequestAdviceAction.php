<?php

namespace App\Filament\Shared\Resources\Threads\Actions;

use App\Enums\AdviceStatus;
use App\Models\Threads\AdviceThread;
use App\Observers\AdviceThreadObserver;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class RequestAdviceAction
{
    public static function make(): Action
    {
        return Action::make('request_advice')
            ->label(__('resources/thread.actions.request_advice.label'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (Model $record) => $record instanceof AdviceThread && $record->advice_status === AdviceStatus::Concept)
            ->action(function (AdviceThread $record) {
                // Prevent reverting to concept - only allow transition to 'asked'
                if ($record->advice_status !== AdviceStatus::Concept) {
                    Notification::make()
                        ->danger()
                        ->title(__('resources/thread.actions.request_advice.already_requested'))
                        ->send();

                    return;
                }

                // Update status to 'asked'
                $record->advice_status = AdviceStatus::Asked;

                // Calculate and set deadline based on response_deadline_days
                if ($record->response_deadline_days) {
                    $record->advice_due_at = now()->addDays($record->response_deadline_days);
                }

                // Update the first message timestamp to current time
                $firstMessage = $record->messages()->oldest()->first();
                if ($firstMessage) {
                    $firstMessage->updated_at = now();
                    $firstMessage->created_at = now();
                    $firstMessage->saveQuietly(); // Save without triggering observers
                }

                $record->save();

                // The AdviceThreadObserver will handle notifications

                Notification::make()
                    ->success()
                    ->title(__('resources/thread.actions.request_advice.success'))
                    ->send();
            });
    }
}

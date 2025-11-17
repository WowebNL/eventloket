<?php

namespace App\Filament\Shared\Resources\Threads\Actions;

use App\Enums\AdviceStatus;
use App\Models\Message;
use App\Models\Threads\AdviceThread;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
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
            ->visible(fn (Model $record) => Filament::getCurrentPanel()->getId() === 'municipality' && $record instanceof AdviceThread && $record->advice_status === AdviceStatus::Concept)
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
                $record->advice_status = AdviceStatus::Asked->value;
                $record->created_by = auth()->id();

                // Calculate and set deadline based on diff between created_at and due_at
                if ($record->advice_due_at) {
                    $record->advice_due_at = now()->addDays(round($record->created_at->diffInDays($record->advice_due_at)));
                }

                // Update the first message timestamp to current time
                /** @var Message $firstMessage */
                $firstMessage = $record->messages()->oldest()->first();
                if ($firstMessage) {
                    $firstMessage->user_id = auth()->id();
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

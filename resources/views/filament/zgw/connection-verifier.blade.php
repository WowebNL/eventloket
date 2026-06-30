@php
    $stepLabels = [
        'connection' => __('municipality/resources/zgw_connection.actions.verify.steps.connection'),
        'abonnement' => __('municipality/resources/zgw_connection.actions.verify.steps.abonnement'),
    ];
@endphp

<div wire:init="start" class="space-y-4">
    <ul class="space-y-4">
        @foreach ($steps as $key => $step)
            <li class="flex items-start gap-3">
                <span class="mt-0.5 shrink-0">
                    @switch($step['status'])
                        @case('success')
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-success-500" />
                            @break
                        @case('fail')
                            <x-filament::icon icon="heroicon-o-x-circle" class="h-6 w-6 text-danger-500" />
                            @break
                        @case('skipped')
                            <x-filament::icon icon="heroicon-o-minus-circle" class="h-6 w-6 text-gray-400" />
                            @break
                        @case('running')
                            <x-filament::loading-indicator class="h-6 w-6 text-primary-500" />
                            @break
                        @case('action')
                            <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-6 w-6 text-warning-500" />
                            @break
                        @default
                            <x-filament::icon icon="heroicon-o-clock" class="h-6 w-6 text-gray-300 dark:text-gray-600" />
                    @endswitch
                </span>

                <div class="min-w-0 flex-1">
                    <p class="font-medium text-gray-950 dark:text-white">{{ $stepLabels[$key] }}</p>

                    @if ($step['message'])
                        <p @class([
                            'text-sm',
                            'text-danger-600 dark:text-danger-400' => $step['status'] === 'fail',
                            'text-gray-500 dark:text-gray-400' => $step['status'] !== 'fail',
                        ])>{{ $step['message'] }}</p>
                    @endif

                    @if ($key === 'abonnement' && $needsRegister)
                        <x-filament::button size="sm" icon="heroicon-o-arrow-path" wire:click="register" wire:loading.attr="disabled" wire:target="register" class="mt-2">
                            {{ __('municipality/resources/zgw_connection.actions.verify.abonnement.register') }}
                        </x-filament::button>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    @if ($finished)
        <div @class([
            'rounded-lg p-3 text-sm',
            'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $success,
            'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => ! $success,
        ])>
            {{ $success
                ? __('municipality/resources/zgw_connection.actions.verify.result.success')
                : __('municipality/resources/zgw_connection.actions.verify.result.fail') }}
        </div>
    @endif
</div>

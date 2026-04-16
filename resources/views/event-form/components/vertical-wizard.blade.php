@php
    $isContained = $isContained();
    $key = $getKey();
    $previousAction = $getAction('previous');
    $nextAction = $getAction('next');
    $steps = $getChildSchema()->getComponents();
    $isHeaderHidden = $isHeaderHidden();
    $applicabilityResolver = function (\Filament\Schemas\Components\Wizard\Step $step) {
        if (method_exists($this, 'isStepApplicable')) {
            return $this->isStepApplicable($step->getKey());
        }

        return true;
    };
@endphp

<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('wizard', 'filament/schemas') }}"
    x-data="wizardSchemaComponent({
                isSkippable: @js($isSkippable()),
                isStepPersistedInQueryString: @js($isStepPersistedInQueryString()),
                key: @js($key),
                startStep: @js($getStartStep()),
                stepQueryStringKey: @js($getStepQueryStringKey()),
            })"
    x-on:next-wizard-step.window="if ($event.detail.key === @js($key)) goToNextStep()"
    x-on:go-to-wizard-step.window="$event.detail.key === @js($key) && goToStep($event.detail.step)"
    wire:ignore.self
    {{
        $attributes
            ->merge(['id' => $getId()], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->merge($getExtraAlpineAttributes(), escape: false)
            ->class([
                'fi-sc-wizard',
                'fi-sc-vertical-wizard',
                'fi-contained' => $isContained,
                'fi-sc-wizard-header-hidden' => $isHeaderHidden,
                'fi-vertical-wizard-layout',
            ])
    }}
>
    <input
        type="hidden"
        value="{{
            collect($steps)
                ->filter(static fn (\Filament\Schemas\Components\Wizard\Step $step): bool => $step->isVisible())
                ->map(static fn (\Filament\Schemas\Components\Wizard\Step $step): ?string => $step->getKey())
                ->values()
                ->toJson()
        }}"
        x-ref="stepsData"
    />

    {{-- Hoofd-kolom: form + footer --}}
    <div class="fi-vertical-wizard-main">
        @foreach ($steps as $step)
            {{ $step }}
        @endforeach

        <div x-cloak class="fi-sc-wizard-footer">
            <div
                x-cloak
                @if (! $previousAction->isDisabled())
                    x-on:click="goToPreviousStep"
                @endif
                x-show="! isFirstStep()"
            >
                {{ $previousAction }}
            </div>

            <div x-show="isFirstStep()">
                {{ $getCancelAction() }}
            </div>

            <div
                x-cloak
                @if (! $nextAction->isDisabled())
                    x-on:click="requestNextStep()"
                @endif
                x-bind:class="{ 'fi-hidden': isLastStep() }"
                wire:loading.class="fi-disabled"
            >
                {{ $nextAction }}
            </div>

            <div x-bind:class="{ 'fi-hidden': ! isLastStep() }">
                {{ $getSubmitAction() }}
            </div>
        </div>
    </div>

    {{-- Verticale sidebar --}}
    @if (! $isHeaderHidden)
        <aside class="fi-vertical-wizard-sidebar" x-cloak>
            <ol
                @if (filled($label = $getLabel()))
                    aria-label="{{ $label }}"
                @endif
                role="list"
                x-ref="header"
                class="fi-vertical-wizard-list"
            >
                @foreach ($steps as $step)
                    @php $isApplicable = $applicabilityResolver($step); @endphp
                    <li
                        x-bind:data-status="(getStepIndex(step) === {{ $loop->index }}) ? 'active' : ((getStepIndex(step) > {{ $loop->index }}) ? 'completed' : 'pending')"
                        @class([
                            'fi-vertical-wizard-step',
                            'fi-vertical-wizard-step-not-applicable' => ! $isApplicable,
                        ])
                    >
                        <button
                            type="button"
                            x-bind:aria-current="getStepIndex(step) === {{ $loop->index }} ? 'step' : null"
                            x-on:click="step = @js($step->getKey())"
                            class="fi-vertical-wizard-step-btn"
                        >
                            <span class="fi-vertical-wizard-step-marker" aria-hidden="true">
                                @if (! $isApplicable)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="fi-vertical-wizard-step-icon">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                @else
                                    <svg x-cloak x-show="getStepIndex(step) > {{ $loop->index }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="fi-vertical-wizard-step-icon">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                    </svg>
                                    <span x-show="getStepIndex(step) <= {{ $loop->index }}" class="fi-vertical-wizard-step-number">
                                        {{ $loop->index + 1 }}
                                    </span>
                                @endif
                            </span>

                            <span class="fi-vertical-wizard-step-text">
                                @if (! $step->isLabelHidden())
                                    <span class="fi-vertical-wizard-step-label">
                                        {{ $step->getLabel() }}
                                    </span>
                                @endif

                                @if (! $isApplicable)
                                    <span class="fi-vertical-wizard-step-description">
                                        niet van toepassing
                                    </span>
                                @elseif (filled($description = $step->getDescription()))
                                    <span class="fi-vertical-wizard-step-description">
                                        {{ $description }}
                                    </span>
                                @endif
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </aside>
    @endif
</div>

@once
    @push('styles')
        <style>
            .fi-vertical-wizard-layout {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 1.5rem;
            }

            @media (min-width: 1024px) {
                .fi-vertical-wizard-layout {
                    grid-template-columns: minmax(0, 1fr) 280px;
                }
            }

            .fi-vertical-wizard-main {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                min-width: 0;
            }

            .fi-vertical-wizard-sidebar {
                align-self: start;
                position: sticky;
                top: 1.5rem;
                padding: 1rem;
                background: rgb(var(--color-gray-50, 249 250 251) / 1);
                border: 1px solid rgb(var(--color-gray-200, 229 231 235) / 1);
                border-radius: 0.75rem;
            }

            :is(.dark .fi-vertical-wizard-sidebar) {
                background: rgb(var(--color-gray-900, 17 24 39) / 0.5);
                border-color: rgb(var(--color-gray-800, 31 41 55) / 1);
            }

            .fi-vertical-wizard-list {
                list-style: none;
                padding: 0;
                margin: 0;
                display: flex;
                flex-direction: column;
                gap: 0.125rem;
            }

            .fi-vertical-wizard-step-btn {
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
                background: transparent;
                border: 0;
                padding: 0.5rem 0.5rem;
                border-radius: 0.5rem;
                text-align: start;
                width: 100%;
                cursor: pointer;
                transition: background-color 0.15s;
            }

            .fi-vertical-wizard-step-btn:hover {
                background: rgb(var(--color-gray-100, 243 244 246) / 1);
            }

            :is(.dark .fi-vertical-wizard-step-btn:hover) {
                background: rgb(var(--color-gray-800, 31 41 55) / 1);
            }

            .fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-btn {
                background: rgb(var(--primary-50, 239 246 255) / 1);
            }

            :is(.dark .fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-btn) {
                background: rgb(var(--primary-950, 23 37 84) / 0.4);
            }

            .fi-vertical-wizard-step-marker {
                flex: 0 0 auto;
                width: 1.75rem;
                height: 1.75rem;
                border-radius: 9999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 2px solid rgb(var(--color-gray-300, 209 213 219) / 1);
                background: rgb(var(--color-white, 255 255 255) / 1);
                font-size: 0.75rem;
                font-weight: 600;
                color: rgb(var(--color-gray-600, 75 85 99) / 1);
            }

            :is(.dark .fi-vertical-wizard-step-marker) {
                border-color: rgb(var(--color-gray-700, 55 65 81) / 1);
                background: rgb(var(--color-gray-900, 17 24 39) / 1);
                color: rgb(var(--color-gray-400, 156 163 175) / 1);
            }

            .fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-marker {
                border-color: rgb(var(--primary-600, 37 99 235) / 1);
                background: rgb(var(--primary-600, 37 99 235) / 1);
                color: rgb(var(--color-white, 255 255 255) / 1);
            }

            .fi-vertical-wizard-step[data-status="completed"] .fi-vertical-wizard-step-marker {
                border-color: rgb(var(--color-success-600, 22 163 74) / 1);
                background: rgb(var(--color-success-600, 22 163 74) / 1);
                color: rgb(var(--color-white, 255 255 255) / 1);
            }

            .fi-vertical-wizard-step-not-applicable .fi-vertical-wizard-step-marker {
                border-color: rgb(var(--color-gray-200, 229 231 235) / 1);
                background: rgb(var(--color-gray-100, 243 244 246) / 1);
                color: rgb(var(--color-gray-400, 156 163 175) / 1);
            }

            :is(.dark .fi-vertical-wizard-step-not-applicable .fi-vertical-wizard-step-marker) {
                border-color: rgb(var(--color-gray-800, 31 41 55) / 1);
                background: rgb(var(--color-gray-800, 31 41 55) / 1);
                color: rgb(var(--color-gray-500, 107 114 128) / 1);
            }

            .fi-vertical-wizard-step-icon {
                width: 1rem;
                height: 1rem;
            }

            .fi-vertical-wizard-step-text {
                display: inline-flex;
                flex-direction: column;
                min-width: 0;
                padding-top: 0.125rem;
            }

            .fi-vertical-wizard-step-label {
                font-weight: 500;
                font-size: 0.875rem;
                line-height: 1.25rem;
                color: rgb(var(--color-gray-900, 17 24 39) / 1);
            }

            :is(.dark .fi-vertical-wizard-step-label) {
                color: rgb(var(--color-gray-100, 243 244 246) / 1);
            }

            .fi-vertical-wizard-step-not-applicable .fi-vertical-wizard-step-label {
                color: rgb(var(--color-gray-500, 107 114 128) / 1);
                text-decoration: line-through;
                text-decoration-color: rgb(var(--color-gray-400, 156 163 175) / 1);
            }

            .fi-vertical-wizard-step-description {
                font-size: 0.75rem;
                color: rgb(var(--color-gray-500, 107 114 128) / 1);
            }
        </style>
    @endpush
@endonce

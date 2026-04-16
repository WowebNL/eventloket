@php
    $isContained = $isContained();
    $key = $getKey();
    $previousAction = $getAction('previous');
    $nextAction = $getAction('next');
    $steps = $getChildSchema()->getComponents();
    $isHeaderHidden = $isHeaderHidden();
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
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->merge($getExtraAlpineAttributes(), escape: false)
            ->class([
                'fi-sc-wizard',
                'fi-sc-vertical-wizard',
                'fi-contained' => $isContained,
                'fi-sc-wizard-header-hidden' => $isHeaderHidden,
            ])
    }}
    style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;"
    x-init="$el.style.gridTemplateColumns = window.innerWidth >= 1024 ? '1fr 280px' : '1fr'"
    x-on:resize.window="$el.style.gridTemplateColumns = window.innerWidth >= 1024 ? '1fr 280px' : '1fr'"
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

    {{-- MIDDEN KOLOM: formulier + footer --}}
    <div style="display: flex; flex-direction: column; gap: 1rem; min-width: 0;">
        @foreach ($steps as $step)
            {{ $step }}
        @endforeach

        <div x-cloak class="fi-sc-wizard-footer" style="display: flex; gap: 0.75rem; justify-content: flex-end;">
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

    {{-- RECHTER KOLOM: verticale step-lijst --}}
    @if (! $isHeaderHidden)
        <aside
            class="fi-sc-vertical-wizard-sidebar"
            style="align-self: start; position: sticky; top: 1rem;"
        >
            <ol
                @if (filled($label = $getLabel()))
                    aria-label="{{ $label }}"
                @endif
                role="list"
                x-cloak
                x-ref="header"
                class="fi-sc-wizard-header"
                style="display: flex; flex-direction: column; gap: 0; list-style: none; padding: 0; margin: 0; border-inline-start: 2px solid var(--gray-200, #e5e7eb);"
            >
                @foreach ($steps as $step)
                    @php
                        $isApplicable = method_exists($this, 'isStepApplicable')
                            ? $this->isStepApplicable($step->getKey())
                            : true;
                    @endphp
                    <li
                        class="fi-sc-wizard-header-step"
                        @class([
                            'fi-sc-wizard-header-step-not-applicable' => ! $isApplicable,
                        ])
                        x-bind:class="{
                            'fi-active': getStepIndex(step) === {{ $loop->index }},
                            'fi-completed': getStepIndex(step) > {{ $loop->index }},
                        }"
                        style="padding: 0.5rem 0 0.5rem 1rem; position: relative; margin-inline-start: -1px;"
                    >
                        <button
                            type="button"
                            x-bind:aria-current="getStepIndex(step) === {{ $loop->index }} ? 'step' : null"
                            x-on:click="step = @js($step->getKey())"
                            @if (! $isApplicable)
                                aria-disabled="false"
                                data-not-applicable="true"
                                title="Deze stap is niet van toepassing voor uw aanvraag"
                            @endif
                            role="step"
                            class="fi-sc-wizard-header-step-btn"
                            style="display: flex; align-items: center; gap: 0.75rem; background: transparent; border: 0; padding: 0; text-align: left; width: 100%; cursor: pointer;{{ $isApplicable ? '' : ' opacity: 0.5;' }}"
                        >
                            <span
                                class="fi-sc-wizard-header-step-marker"
                                aria-hidden="true"
                                style="flex: 0 0 auto; width: 1.75rem; height: 1.75rem; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; border: 2px solid var(--gray-300, #d1d5db); background: var(--white, #ffffff); font-size: 0.75rem; font-weight: 600;"
                            >
                                @if (! $isApplicable)
                                    <span aria-hidden="true">⊘</span>
                                @else
                                    <span x-show="getStepIndex(step) > {{ $loop->index }}" x-cloak aria-hidden="true">✓</span>
                                    <span x-show="getStepIndex(step) <= {{ $loop->index }}" aria-hidden="true">{{ $loop->index + 1 }}</span>
                                @endif
                            </span>

                            <span class="fi-sc-wizard-header-step-text" style="display: inline-flex; flex-direction: column; min-width: 0;">
                                @if (! $step->isLabelHidden())
                                    <span
                                        class="fi-sc-wizard-header-step-label"
                                        style="font-weight: 500; font-size: 0.875rem; line-height: 1.25rem;"
                                    >
                                        {{ $step->getLabel() }}
                                    </span>
                                @endif

                                @if (! $isApplicable)
                                    <span
                                        class="fi-sc-wizard-header-step-description"
                                        style="font-size: 0.75rem; color: var(--gray-500, #6b7280);"
                                    >
                                        niet van toepassing
                                    </span>
                                @elseif (filled($description = $step->getDescription()))
                                    <span
                                        class="fi-sc-wizard-header-step-description"
                                        style="font-size: 0.75rem; color: var(--gray-500, #6b7280);"
                                    >
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

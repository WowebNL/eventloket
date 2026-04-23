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
    {{-- verticalWizardComponent is inline gedefinieerd in @push('scripts') onderaan deze view, geen x-load nodig. --}}
    {{--
        De `verticalWizardComponent`-factory (zie `<script>` onderaan deze
        view) wrapt Filament's stock wizardSchemaComponent en voegt
        `currentStepValid` + `checkValidity()` toe. Functie-aanroep in
        x-data werkt betrouwbaarder dan spread-syntax binnen een Alpine-
        expressie, waar de parser soms over { } struikelt.
    --}}
    x-data="verticalWizardComponent({
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
                        {{--
                            Forward-skipping via sidebar is geblokkeerd: je kunt alleen
                            terug naar eerder voltooide stappen of op de huidige stap
                            blijven staan. Vooruit gaan moet via de "Volgende"-knop,
                            zodat de validatie van de huidige stap eerst afgaat.
                            Niet-toepasselijke stappen zijn volledig niet klikbaar.
                        --}}
                        <button
                            type="button"
                            x-bind:aria-current="getStepIndex(step) === {{ $loop->index }} ? 'step' : null"
                            x-bind:disabled="{{ $isApplicable ? 'false' : 'true' }} || getStepIndex(step) < {{ $loop->index }}"
                            x-on:click="goToStep(@js($step->getKey()))"
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
    @push('scripts')
        <script>
            /*
             * Alpine-factory voor onze verticale wizard. Dupliceert bewust de
             * logica van Filament's `wizardSchemaComponent` (vendor/filament/
             * schemas/resources/js/components/wizard.js) zodat we niet via
             * x-load hoeven te wachten op een externe dependency — dat gaf
             * parser-issues met spread-syntax in Alpine's expression-evaluator.
             *
             * Afwijkingen van stock: geen — functioneel identiek aan
             * Filament's wizardSchemaComponent. We vervangen de factory
             * alleen om het nesting/laadpad te vereenvoudigen.
             */
            window.verticalWizardComponent = function (config) {
                const { isSkippable, isStepPersistedInQueryString, key, startStep, stepQueryStringKey } = config;
                return {
                    step: null,

                    init() {
                        this.step = this.getSteps().at(startStep - 1);
                        this.$watch('step', () => {
                            this.updateQueryString();
                            this.autofocusFields();
                        });
                        this.autofocusFields(true);
                    },

                    async requestNextStep() {
                        await this.$wire.callSchemaComponentMethod(key, 'nextStep', {
                            currentStepIndex: this.getStepIndex(this.step),
                        });
                    },

                    goToNextStep() {
                        const next = this.getStepIndex(this.step) + 1;
                        if (next >= this.getSteps().length) return;
                        this.step = this.getSteps()[next];
                        this.scroll();
                    },

                    goToPreviousStep() {
                        const prev = this.getStepIndex(this.step) - 1;
                        if (prev < 0) return;
                        this.step = this.getSteps()[prev];
                        this.scroll();
                    },

                    goToStep(stepKey) {
                        const idx = this.getStepIndex(stepKey);
                        if (idx <= -1) return;
                        if (!isSkippable && idx > this.getStepIndex(this.step)) return;
                        this.step = stepKey;
                        this.scroll();
                    },

                    scroll() {
                        this.$nextTick(() => {
                            // Scroll de body naar boven zodat de nieuwe stap vanaf
                            // het begin leesbaar is, niet halverwege vanaf waar
                            // de vorige stap eindigde.
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                            this.$refs.header?.children[this.getStepIndex(this.step)]
                                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        });
                    },

                    autofocusFields(respectCurrentFocus = false) {
                        this.$nextTick(() => {
                            if (
                                respectCurrentFocus &&
                                document.activeElement &&
                                document.activeElement !== document.body &&
                                this.$el.compareDocumentPosition(document.activeElement) & Node.DOCUMENT_POSITION_PRECEDING
                            ) return;
                            const fields = this.$refs[`step-${this.step}`]?.querySelectorAll('[autofocus]') ?? [];
                            for (const field of fields) {
                                field.focus();
                                if (document.activeElement === field) break;
                            }
                        });
                    },

                    getStepIndex(step) {
                        const idx = this.getSteps().findIndex((s) => s === step);
                        return idx === -1 ? 0 : idx;
                    },

                    getSteps() {
                        return JSON.parse(this.$refs.stepsData.value);
                    },

                    isFirstStep() { return this.getStepIndex(this.step) <= 0; },
                    isLastStep()  { return this.getStepIndex(this.step) + 1 >= this.getSteps().length; },

                    isStepAccessible(stepKey) {
                        return isSkippable || this.getStepIndex(this.step) > this.getStepIndex(stepKey);
                    },

                    updateQueryString() {
                        if (!isStepPersistedInQueryString) return;
                        const url = new URL(window.location.href);
                        url.searchParams.set(stepQueryStringKey, this.step);
                        history.replaceState(null, document.title, url.toString());
                    },

                };
            };
        </script>
    @endpush

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

            /*
             * Filament 4 levert kleuren als oklch(...) waarden in `--primary-*`
             * en `--gray-*` (zie support/Assets/AssetManager::renderStyles). We
             * gebruiken die variabelen direct als kleurwaarde — wrappen in
             * `rgb(...)` werkt hier niet.
             */
            .fi-vertical-wizard-sidebar {
                align-self: start;
                position: sticky;
                top: 1.5rem;
                padding: 1rem;
                background: color-mix(in oklab, var(--gray-50) 100%, transparent);
                border: 1px solid var(--gray-200);
                border-radius: 0.75rem;
            }

            :is(.dark .fi-vertical-wizard-sidebar) {
                background: color-mix(in oklab, var(--gray-900) 60%, transparent);
                border-color: var(--gray-800);
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
                position: relative;
            }

            .fi-vertical-wizard-step-btn:hover:not(:disabled) {
                background: var(--gray-100);
            }

            :is(.dark .fi-vertical-wizard-step-btn:hover:not(:disabled)) {
                background: var(--gray-800);
            }

            /*
             * Disabled = future step of niet-toepasselijke stap: gebruiker mag
             * hier niet naartoe klikken. We dimmen de label + maken hover-cursor
             * niet-klikbaar zodat het ook interactief duidelijk is.
             */
            .fi-vertical-wizard-step-btn:disabled {
                cursor: not-allowed;
                opacity: 0.7;
            }

            .fi-vertical-wizard-step-btn:disabled .fi-vertical-wizard-step-label {
                color: var(--gray-500);
            }

            :is(.dark .fi-vertical-wizard-step-btn:disabled .fi-vertical-wizard-step-label) {
                color: var(--gray-500);
            }

            /*
             * Actieve stap markeren we met een duidelijke ring rond het
             * step-marker-cirkeltje — simpel en direct herkenbaar zonder
             * op theme-specifieke achtergronden te leunen.
             */

            .fi-vertical-wizard-step-marker {
                flex: 0 0 auto;
                width: 1.75rem;
                height: 1.75rem;
                border-radius: 9999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: 2px solid var(--gray-300);
                background: #ffffff;
                font-size: 0.75rem;
                font-weight: 600;
                color: var(--gray-600);
            }

            :is(.dark .fi-vertical-wizard-step-marker) {
                border-color: var(--gray-700);
                background: var(--gray-900);
                color: var(--gray-400);
            }

            .fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-marker {
                border-color: var(--primary-600);
                background: var(--primary-600);
                color: #ffffff;
                box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 25%, transparent);
            }

            .fi-vertical-wizard-step[data-status="completed"] .fi-vertical-wizard-step-marker {
                border-color: var(--primary-500);
                background: var(--primary-500);
                color: #ffffff;
            }

            .fi-vertical-wizard-step-not-applicable .fi-vertical-wizard-step-marker {
                border-color: var(--gray-200);
                background: var(--gray-100);
                color: var(--gray-400);
            }

            :is(.dark .fi-vertical-wizard-step-not-applicable .fi-vertical-wizard-step-marker) {
                border-color: var(--gray-800);
                background: var(--gray-800);
                color: var(--gray-500);
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
                color: var(--gray-900);
            }

            :is(.dark .fi-vertical-wizard-step-label) {
                color: var(--gray-100);
            }

            .fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-label {
                font-weight: 600;
                color: var(--primary-700);
            }

            :is(.dark .fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-label) {
                color: var(--primary-300);
            }

            .fi-vertical-wizard-step-not-applicable .fi-vertical-wizard-step-label {
                color: var(--gray-500);
                text-decoration: line-through;
                text-decoration-color: var(--gray-400);
            }

            .fi-vertical-wizard-step-description {
                font-size: 0.75rem;
                color: var(--gray-500);
            }

        </style>
    @endpush
@endonce

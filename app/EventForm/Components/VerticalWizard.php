<?php

declare(strict_types=1);

namespace App\EventForm\Components;

use Filament\Schemas\Components\Wizard;

/**
 * Wizard-variant die rechts van het formulier een verticale step-lijst
 * toont i.p.v. een horizontale balk bovenaan. Vereist voor formulieren
 * met veel stappen (zoals het 17-staps evenementformulier).
 *
 * De step-lijst ondersteunt naast active/completed ook een `not-applicable`
 * markering — gevoed door `FormState::isStepApplicable()` via een callable
 * die op `notApplicableResolver()` kan worden gezet. Niet-applicable stappen
 * blijven klikbaar maar worden visueel gedempt.
 */
class VerticalWizard extends Wizard
{
    /**
     * @var \Closure(string): bool|null Krijgt de step-key, returnt true als applicable.
     */
    protected ?\Closure $notApplicableResolver = null;

    protected string $view = 'event-form.components.vertical-wizard';

    /**
     * @param  \Closure(string): bool  $resolver
     */
    public function stepApplicability(\Closure $resolver): static
    {
        $this->notApplicableResolver = $resolver;

        return $this;
    }

    public function isStepApplicable(string $stepKey): bool
    {
        if ($this->notApplicableResolver === null) {
            return true;
        }

        return (bool) ($this->notApplicableResolver)($stepKey);
    }
}

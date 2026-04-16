<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid f1202010-b8b7-45c0-8f31-756190313451
 *
 * @openforms-rule-description Als ({{risicoClassificatie}} is gelijk aan 'B')of ({{risicoClassificatie}} is gelijk aan 'C')
 */
final class AlsIsGelijkAanBOfIsGelijkAanC implements Rule
{
    public function identifier(): string
    {
        return 'f1202010-b8b7-45c0-8f31-756190313451';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('veiligheidsplan', false);
        $s->setFieldHidden('infoTekstVeiligheidsplan', false);
        $s->setFieldHidden('ContentOverigeBijlage', false);
    }
}

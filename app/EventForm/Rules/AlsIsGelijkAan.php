<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a
 *
 * @openforms-rule-description Als {{eventloketSession.kvk}} is gelijk aan ''
 */
final class AlsIsGelijkAan implements Rule
{
    public function identifier(): string
    {
        return '1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('eventloketSession.kvk') === ''));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('organisatieInformatie', true);
        $s->setFieldHidden('adresgegevens', false);
        $s->setFieldHidden('waarschuwingGeenKvk', false);
    }
}

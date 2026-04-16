<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 974b5945-c4cf-4d1a-a5f8-34985255406d
 *
 * @openforms-rule-description Als {{adresVanDeGebouwEn}} is niet gelijk aan None
 */
final class AlsIsNietGelijkAanNone implements Rule
{
    public function identifier(): string
    {
        return '974b5945-c4cf-4d1a-a5f8-34985255406d';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((((bool) $s->get('adresVanDeGebouwEn')) && ($s->get('adresVanDeGebouwEn') !== 'None')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('addressesToCheck', $s->get('adresVanDeGebouwEn'));
    }
}

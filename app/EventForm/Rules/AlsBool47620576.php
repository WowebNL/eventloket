<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 47620576-e866-4f7e-98fb-cad476f4ac3b
 *
 * @openforms-rule-description Als bool({{evenementInGemeente.brk_identification}})
 */
final class AlsBool47620576 implements Rule
{
    public function identifier(): string
    {
        return '47620576-e866-4f7e-98fb-cad476f4ac3b';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (((bool) $s->get('evenementInGemeente.brk_identification')));
    }

    public function apply(FormState $s): void
    {
        $s->setFieldHidden('algemeneVragen', false);
    }
}

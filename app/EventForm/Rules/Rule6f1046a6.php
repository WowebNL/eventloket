<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\MapContext;

/**
 * @openforms-rule-uuid 6f1046a6-7866-491b-b87d-65bd67aade6f
 *
 * @openforms-rule-description
 */
final class Rule6f1046a6 implements Rule
{
    public function identifier(): string
    {
        return '6f1046a6-7866-491b-b87d-65bd67aade6f';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('inGemeentenResponse') !== '{}'));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('evenementInGemeentenNamen', ((function () use ($s) {
            $__items = $s->get('inGemeentenResponse.all.items');
            if (! is_array($__items)) {
                return [];
            } $__result = [];
            foreach ($__items as $__item) {
                $__result[] = (function ($s) {
                    return $s->get('name');
                })(MapContext::from($s, $__item));
            }

            return $__result;
        })()));
        $s->setVariable('evenementInGemeentenLijst', ((function () use ($s) {
            $__items = $s->get('inGemeentenResponse.all.items');
            if (! is_array($__items)) {
                return [];
            } $__result = [];
            foreach ($__items as $__item) {
                $__result[] = (function ($s) {
                    return array_merge(($s->get('brk_identification') ?? []), ($s->get('name') ?? []));
                })(MapContext::from($s, $__item));
            }

            return $__result;
        })()));
        $s->setVariable('binnenVeiligheidsregio', $s->get('inGemeentenResponse.all.within'));
        $s->setVariable('gemeenten', $s->get('inGemeentenResponse.all.object'));
        $s->setVariable('routeDoorGemeentenNamen', ((function () use ($s) {
            $__items = $s->get('inGemeentenResponse.line.items');
            if (! is_array($__items)) {
                return [];
            } $__result = [];
            foreach ($__items as $__item) {
                $__result[] = (function ($s) {
                    return $s->get('name');
                })(MapContext::from($s, $__item));
            }

            return $__result;
        })()));
    }
}

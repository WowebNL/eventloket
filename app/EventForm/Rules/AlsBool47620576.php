<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Transpiler\JsTruthy;

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

    public function triggerStepUuids(): array
    {
        return [];
    }

    public function effectStepUuids(): array
    {
        return ['d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'];
    }

    public function applies(FormState $s): bool
    {
        return JsTruthy::of($s->get('evenementInGemeente.brk_identification'));
    }

    public function apply(FormState $s): void
    {
        app(ServiceFetcher::class)->fetch('gemeenteVariabelen', $s);
        $s->setFieldHidden('algemeneVragen', false);
    }
}

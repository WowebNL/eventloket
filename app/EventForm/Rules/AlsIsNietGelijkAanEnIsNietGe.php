<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid bb866a33-aa14-437f-a7bf-3303ad75a5d9
 *
 * @openforms-rule-description Als ({{adresSenVanHetEvenement}} is niet gelijk aan '{}')en ({{adresSenVanHetEvenement}} is niet ge…
 */
final class AlsIsNietGelijkAanEnIsNietGe implements Rule
{
    public function identifier(): string
    {
        return 'bb866a33-aa14-437f-a7bf-3303ad75a5d9';
    }

    public function applies(FormState $s): bool
    {
        return (bool) ((($s->get('adresSenVanHetEvenement') !== '{}') && ($s->get('adresSenVanHetEvenement') !== '[]') && ($s->get('adresSenVanHetEvenement') !== 'None')));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('addressesToCheck', $s->get('adresSenVanHetEvenement'));
    }
}

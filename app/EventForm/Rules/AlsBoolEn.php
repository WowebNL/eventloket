<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

use App\EventForm\State\FormState;

/**
 * @openforms-rule-uuid 2f7b0e09-2730-4aab-89e5-8b0182ee68bb
 *
 * @openforms-rule-description Als bool({{eventloketSession.organisation_address}})en ({{eventloketSession.organisation_address}} …
 */
final class AlsBoolEn implements Rule
{
    public function identifier(): string
    {
        return '2f7b0e09-2730-4aab-89e5-8b0182ee68bb';
    }

    public function applies(FormState $s): bool
    {
        return (bool) (($s->get('eventloketSession.organisation_address') !== ''));
    }

    public function apply(FormState $s): void
    {
        $s->setVariable('postcode1', $s->get('eventloketSession.organisation_address.postcode'));
        $s->setVariable('huisnummer1', $s->get('eventloketSession.organisation_address.houseNumber'));
        $s->setVariable('huisletter1', $s->get('eventloketSession.organisation_address.houseLetter'));
        $s->setVariable('huisnummertoevoeging1', $s->get('eventloketSession.organisation_address.houseNumberAddition'));
        $s->setVariable('straatnaam1', $s->get('eventloketSession.organisation_address.streetName'));
        $s->setVariable('plaatsnaam1', $s->get('eventloketSession.organisation_address.city'));
    }
}

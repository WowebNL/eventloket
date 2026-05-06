<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Components\InfoText;
use App\EventForm\Reporting\TypeAanvraagOnderdelen;
use App\EventForm\State\FormState;
use Filament\Schemas\Components\Wizard\Step;

/**
 * @openforms-step-uuid 119481f2-02f1-4882-974a-6578d3f80d59
 *
 * @openforms-step-index 16
 */
final class TypeAanvraagStep
{
    public const UUID = '119481f2-02f1-4882-974a-6578d3f80d59';

    public static function make(): Step
    {
        return Step::make('Type aanvraag')
            ->key(self::UUID)
            ->schema([
                InfoText::info('content35', fn (FormState $state) => self::renderOnderdelen($state)),
            ]);
    }

    /**
     * Bouw de lijst onderdelen op die voor déze aanvraag van toepassing
     * zijn. Voorheen stond dezelfde logica als Jinja-template in een
     * lange InfoText-string; in PHP is 't direct leesbaar en kunnen we
     * dezelfde lijst hergebruiken in de Samenvatting + PDF (zie
     * `TypeAanvraagOnderdelen`).
     */
    private static function renderOnderdelen(FormState $state): string
    {
        $gemeente = $state->get('evenementInGemeente');
        $gemeenteNaam = is_array($gemeente) ? (string) ($gemeente['name'] ?? '') : '';
        $evenementNaam = (string) ($state->get('watIsDeNaamVanHetEvenementVergunning') ?? '');

        $items = TypeAanvraagOnderdelen::buildList($state);

        $intro = '<p>Eventloket regelt de aanvraag voor uw evenement '.e($evenementNaam)
            .' bij de Gemeente <strong>'.e($gemeenteNaam).'</strong> voor wat betreft:</p>';

        $lijst = '';
        if ($items !== []) {
            $lijst = '<ul>';
            foreach ($items as $item) {
                $lijst .= '<li><strong>'.e($item).'</strong></li>';
            }
            $lijst .= '</ul>';
        }

        $outro = '<p>Alle onderdelen worden door de behandelaar opgepakt, wanneer van toepassing.</p>';

        return $intro.$lijst.$outro;
    }
}

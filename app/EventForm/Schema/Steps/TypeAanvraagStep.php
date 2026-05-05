<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

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
                TextEntry::make('content35')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Eventloket regelt de aanvraag voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} bij de Gemeente <strong>{% get_value evenementInGemeente \'name\' %}</strong> voor wat betreft:</p><p>{% if waarvoorWiltUEventloketGebruiken == \'vooraankondiging\' %}</p><ul><li><strong>Vooraankondiging</strong></li></ul><p>{% elif wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer == \'Nee\' %}</p><ul><li><strong>Melding</strong></li></ul><p>{% else %}</p><ul><li><strong>Evenementenvergunning</strong></li></ul><p>{% endif %}</p><p>{% if alcoholvergunning %}</p><ul><li><strong>Ontheffing Alcoholwet</strong></li></ul><p>{% endif %}</p><p>{% if kruisAanWatVanToepassingIsVoorUwEvenementX.A3 %}</p><ul><li><strong>Gebruiksmelding brandveilig gebruik en basishulpverlening overige plaatsen</strong></li></ul><p>{% endif %}</p><p>{% if kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48 or kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49 %}</p><ul><li><strong>Ontheffing plaatsen object of parkeren grote voertuigen op de openbare weg.</strong></li></ul><p>{% endif %}</p><p>{% if kruisAanWatVanToepassingIsVoorUwEvenementX.A4 %}</p><ul><li><strong>Kansspelen</strong></li></ul><p>{% endif %}</p><p>{% if kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51 %}</p><ul><li><strong>Aanstellingsbesluit verkeersregelaars</strong></li></ul><p>{% endif %}</p><p>&nbsp;</p><p>Alle onderdelen worden door de behandelaar opgepakt, wanneer van toepassing.</p>', $livewire->state()))),
            ]);
    }
}

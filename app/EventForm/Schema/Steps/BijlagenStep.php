<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 7982e106-bce0-49cf-bdaa-ada9eac8b6ba
 *
 * @openforms-step-index 15
 */
final class BijlagenStep
{
    public const UUID = '7982e106-bce0-49cf-bdaa-ada9eac8b6ba';

    public static function make(): Step
    {
        return Step::make('Bijlagen')
            ->key(self::UUID)
            ->schema([
                TextEntry::make('infoTekstVeiligheidsplan')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p><strong>U moet voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} een veiligheidsplan indienen, waarin de volgende onderdelen zijn opgenomen:</strong></p><ul><li><strong>Huisregelement</strong>: beschrijft de regels, waaraan een bezoeker of deelnemer zich dient te houden tijdens het evenement</li><li><strong>Zorgplan</strong>/EHBO inzetplan/medische planvorming: beschrijft hoe de organisatie van het evenement er voor zorgt, dat de bezoekers/deelnemers medisch ondersteund worden tijdens het evenement, zowel preventief als reactief.</li><li><strong>Beveiligingsplan</strong>: beschrijft op welke wijze de organisatie tijdens het evenement de veiligheid van alle deelnemers waarborgt, waarbij zowel de preventieve maatregelen ter voorkoming van incidenten, als de correctieve maatregelen ingeval van incidenten en/of calamiteiten beschreven worden. Hierbij wordt ook verwacht, dat er nagedacht is over mogelijke calamiteiten en dat beschreven wordt hoe daarop gereageerd wordt door de organisatie en de hulpdiensten.</li><li><strong>Verkeersplan</strong> (mobiliteitsplan): beschrijft de wijze waarop het evenement impact heeft op de normale verkeersstromen op- en rondom het evenemententerrein. Speciale aandacht dient er de zijn voor de toegankelijkheid van het evenemententerrein voor de hulpdiensten Brandweer/Politie/Ambulance.</li></ul>'))
                    ->hidden(),
                FileUpload::make('veiligheidsplan')
                    ->label('Veiligheidsplan')
                    ->hidden(),
                FileUpload::make('bebordingsEnBewegwijzeringsplan')
                    ->label('U heeft aangegeven, dat u gebruik gaat maken van bewegwijzering. Hiervoor dient u een bebordings- en bewegwijzeringsplan toe te voegen, als onderdeel van het verkeersplan, dat als bijlage toegevoegd wordt.')
                    ->hidden(),
                TextEntry::make('ContentOverigeBijlage')
                    ->hiddenLabel()
                    ->state(new HtmlString('<p>Naast het veiligheidsplan moet u bij uw aanvraag ook de hier aangegeven bijlage(n) indienen.</p><ul><li>Draaiboek van alle dagen, inclusief opbouwen en afbouwen.</li><li>Muziek- of activiteiten programma (van alle dagen), indien van toepassing</li><li>Situatietekening(en) (plattegrond)&nbsp; van uw evenement locatie(s) met alle activiteiten.</li><li>Mobiliteitsplan voor omleidingen of beperking van de toegangswegen voor hulpdiensten (ingeval van een geringe impact volstaat het intekenen van de verkeersimpact op de plattegrond bij de vraag over verkeersbelemmeringen)</li></ul>'))
                    ->hidden(),
                FileUpload::make('bijlagen1')
                    ->label('Overige bijlagen'),
            ]);
    }
}

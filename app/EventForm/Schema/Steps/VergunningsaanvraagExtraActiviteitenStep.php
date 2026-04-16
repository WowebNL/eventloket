<?php

declare(strict_types=1);

namespace App\EventForm\Schema\Steps;

use App\EventForm\Template\LabelRenderer;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\HtmlString;

/**
 * @openforms-step-uuid 6e285ace-f891-4324-b54e-639c1cfff9fa
 *
 * @openforms-step-index 13
 */
final class VergunningsaanvraagExtraActiviteitenStep
{
    public const UUID = '6e285ace-f891-4324-b54e-639c1cfff9fa';

    public static function make(): Step
    {
        return Step::make('Vergunningsaanvraag: extra activiteiten')
            ->key(self::UUID)
            ->schema([
                TextEntry::make('contentBalon')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Het oplaten van ballonnen kan van invloed zijn op het luchtverkeer binnen een straal van 8 km van een commerciele luchthaven. Zie voor de richtlijnen op <a href="www.lvnl.nl" target="_blank" rel="noopener noreferrer">www.lvnl.nl - een actviteit in het luchtruim</a> - evenementen.</p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentBalon') !== false),
                TextEntry::make('contentLasershow')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Het uitvoeren van een lasershow kan van invloed zijn op het luchtverkeer binnen een straal van 8 km van een commerciele luchthaven. Zie voor de richtlijnen op <a href="www.lvnl.nl" target="_blank" rel="noopener noreferrer">www.lvnl.nl - een actviteit in het luchtruim</a> - evenementen.</p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentLasershow') !== false),
                TextEntry::make('contentZeppelin')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Het oplaten van een zeppelin kan van invloed zijn op het luchtverkeer binnen een straal van 8 km van een commerciele luchthaven. Zie voor de richtlijnen op<a href="www.lvnl.nl " target="_blank" rel="noopener noreferrer"> www.lvnl.nl - een actviteit in het luchtruim</a> - evenementen.</p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentZeppelin') !== false),
                TextEntry::make('contentDieren')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor activiteiten met dieren verwijzen wij u naar de website van <a href="https://www.nvwa.nl/onderwerpen/evenementen-met-levende-dieren" target="_blank" rel="noopener noreferrer">de Nederlandse Voedsel- en Warenautoriteit</a></p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentDieren') !== false),
                TextEntry::make('contentVuurwerk')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Het afsteken van vuurwerk, buiten de oud/nieuw periode is voorbehouden aan professionele bedrijven, die hiervoor een toepassingsvergunning nodig hebben en per evenement hiervoor een ontbrandingstoestemming moeten aanvragen. De regels hiervoor zijn te vinden op <a href="https://ondernemersplein.overheid.nl/professioneel-vuurwerk-opslaan-en-afsteken/provincie/limburg/" target="_blank" rel="noopener noreferrer">de website van het ondernemersplein</a>.</p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentVuurwerk') !== false),
                TextEntry::make('contentTattoo')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor het plaatsen van tatoeages of piercings tijdens evenementen is een vergunning van de Gemeenschappelijke Gezondheidsdienst (GGD) noodzakelijk. De regels hiervoor vindt u op <a href="https://ondernemersplein.overheid.nl/vergunning-aanvragen-voor-tatoeeren-of-piercen/" target="_blank" rel="noopener noreferrer">de website van het ondernemersplein.</a></p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentTattoo') !== false),
                TextEntry::make('contentVuurkorf')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Voor het plaatsen van vuurkorven of het aansteken van open vuur verwijzen we naar <a href="https://www.brandweer.nl/onderwerpen/evenement-organiseren/" target="_blank" rel="noopener noreferrer">de website van de brandweer</a>.</p><p>Controleer ook bij uw betreffende gemeente of een aparte ontheffing hiervoor nodig is voor het gebruik van open vuur of vuurkorven.</p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentVuurkorf') !== false),
                TextEntry::make('contentWapen')
                    ->hiddenLabel()
                    ->state(fn ($livewire) => new HtmlString(app(LabelRenderer::class)->render('<p>Controleer of u een ontheffing van het wapenverbod nodig heeft voor uw evenement op <a href="https://www.rijksoverheid.nl/wetten-en-regelingen/productbeschrijvingen/ontheffing-wapenverbod-aanvragen" target="_blank" rel="noopener noreferrer">de website van Rijksoverheid</a>.</p>', $livewire->state())))
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('contentWapen') !== false),
                Textarea::make('welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement')
                    ->label('Welke showeffecten bent u van plan te organiseren voor uw evenement?\'')
                    ->required()
                    ->maxLength(10000)
                    ->hidden(fn ($livewire): bool => $livewire->state()->isFieldHidden('welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement') !== false),
            ]);
    }
}

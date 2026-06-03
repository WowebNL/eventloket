import { test, expect } from '@playwright/test';
import { skipAlsOpenZaakOffline } from './helpers/openzaak-check.mjs';
import { leesPdfContent } from './helpers/pdf-content.mjs';
import { kiesRadioOptioneel, vulTekst } from './helpers/form-invullen.mjs';
import {
    verseStart,
    stap1Contactgegevens,
    stap2HetEvenement,
    stap3LocatieGebouw,
    stap4Tijden,
    stap5Vooraankondiging,
    stap6ScanVergunning,
    stap8Risicoscan,
    stap9VergunningSoort,
    klikDoorTotIndienen,
    indienen,
} from './helpers/wizard-flow.mjs';

/**
 * Scenario: vergunning-aanvraag met "Aanstellingsbesluit verkeers-
 * regelaars" als ontheffing. Vink kenmerk A51 op
 * `kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX`
 * aan; dat triggert op stap "Overig" een Verkeersregelaars-fieldset
 * en zorgt dat `TypeAanvraagOnderdelen::buildList()` zowel
 * "Evenementenvergunning" als "Aanstellingsbesluit verkeersregelaars"
 * in de PDF-onderdelen-lijst zet.
 *
 * Hoeveel ontheffingen tegelijk testen levert exponentieel veel
 * verplichte invul-velden op stap 10/15; voor regression-coverage van
 * de TypeAanvraagOnderdelen-logic is één extra-onderdeel genoeg. De
 * Pest-test `Type-aanvraag-stap: vergunning + ontheffingen` dekt alle
 * vijf de takken al in de PHP-laag.
 */
test('Vergunning + verkeersregelaars-ontheffing → "Onderdelen aanvraag" lijst toont beide', async ({ page }) => {
    test.setTimeout(240_000);
    skipAlsOpenZaakOffline(test);

    await verseStart(page);

    await stap1Contactgegevens(page);
    await stap2HetEvenement(page, {
        naam: 'Stadsfestival met Ontheffing',
        omschrijving: 'Festival met verkeersregelaars op de openbare weg.',
        soort: 'Festival',
    });
    await stap3LocatieGebouw(page);
    await stap4Tijden(page);
    await stap5Vooraankondiging(page, 'evenement');
    await stap6ScanVergunning(page);
    await stap8Risicoscan(page);
    await stap9VergunningSoort(page, {
        extraOverigeKenmerken: ['A51'],
    });

    // Custom handler voor de Overig-stap: het verkeersregelaars-fieldset
    // heeft eigen verplichte velden die niet in de standaard 'overig'-
    // handler staan. Werkt aanvullend op standaardHandlers.
    const overigMetVerkeersregelaars = async () => {
        const standaardVelden = [
            'wiltUPromotieMakenVoorUwEvenement',
            'geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX',
            'organiseertUUwEvenementXVoorDeEersteKeer',
            'hanteertUHuisregelsVoorUwEvenementX',
            'organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024',
            'heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement',
        ];
        for (const v of standaardVelden) {
            await kiesRadioOptioneel(page, v, 'Nee');
            await page.waitForTimeout(250);
        }
        // Extra: Verkeersregelaars-fieldset (A51-trigger). 'Nee' kiezen
        // omdat dat geen extra textarea zichtbaar maakt. Het aantal
        // is als TextInput verplicht — vul met '5'.
        await kiesRadioOptioneel(page, 'huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie', 'Nee');
        await page.waitForTimeout(400);
        await vulTekst(page, 'hoeveelVerkeersregelaarsWiltUInzetten', '5').catch(() => {});
        await page.waitForTimeout(300);
    };

    await klikDoorTotIndienen(page, { 'overig': overigMetVerkeersregelaars });
    const zaakIdentifier = await indienen(page);

    const pdf = leesPdfContent(zaakIdentifier);

    const typeAanvraag = pdf.sections.find((s) => /type aanvraag/i.test(s.title));
    expect(typeAanvraag, 'sectie Type aanvraag gevonden').not.toBeUndefined();

    const onderdelenEntry = (typeAanvraag?.entries ?? []).find((e) =>
        /onderdelen van uw aanvraag/i.test(e.label ?? ''),
    );
    expect(onderdelenEntry, '"Onderdelen van uw aanvraag"-entry aanwezig').not.toBeUndefined();

    const value = onderdelenEntry?.value ?? '';
    expect(value).toContain('Evenementenvergunning');
    expect(value).toContain('Aanstellingsbesluit verkeersregelaars');
});

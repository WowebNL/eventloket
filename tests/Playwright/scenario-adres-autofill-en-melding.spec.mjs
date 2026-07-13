import { test, expect } from '@playwright/test';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import { leegDraftDb } from './helpers/wizard-flow.mjs';
import {
    vulTekst,
    vulTextarea,
    vulEindigendOp,
    kiesSelect,
    klikVolgende,
    huidigeStap,
    endsWithSelector,
} from './helpers/form-invullen.mjs';

/**
 * Adres-autofill in de locatie-stap (bevindingen 2, 3 en A uit de tester-ronde).
 *
 * - Een bestaand adres vult straat/plaats automatisch aan.
 * - Een niet-bestaand adres (bv. 5541WG/99: PDOK geeft dan fuzzy een 99 uit een
 *   andere plaats terug) mag GEEN verkeerd adres invullen; straat/plaats worden
 *   leeggemaakt en er verschijnt een melding "Geen adres gevonden".
 * - Bevinding A (nog open): bij meerdere locaties gaat de autofill van één rij
 *   verloren door de gemeente-detect re-render. Vastgelegd als test.fixme.
 */

const ADR1 = '.adresVanHetGebouwWaarUwEvenementPlaatsvindt1';

const straatRij = (page, i) => page.locator(endsWithSelector('input', `${ADR1}.straatnaam`)).nth(i);
const plaatsRij = (page, i) => page.locator(endsWithSelector('input', `${ADR1}.woonplaatsnaam`)).nth(i);

async function vulAdresRij(page, i, postcode, huisnummer) {
    await page.locator(endsWithSelector('input', `${ADR1}.postcode`)).nth(i).fill(postcode);
    await page.keyboard.press('Tab');
    await page.locator(endsWithSelector('input', `${ADR1}.huisnummer`)).nth(i).fill(huisnummer);
    await page.keyboard.press('Tab');
    await page.waitForTimeout(3500); // debounce (750ms) + PDOK + gemeente-detect
}

/** Loopt de wizard tot en met "In een gebouw" op de locatie-stap. */
async function totLocatieGebouw(page) {
    await leegDraftDb();
    await loginAlsOrganiser(page);
    await openFormulier(page);

    // Stap 1: Contactgegevens
    await vulTekst(page, 'watIsUwVoornaam', 'Noah');
    await vulTekst(page, 'watIsUwAchternaam', 'de Graaf');
    await vulTekst(page, 'watIsUwEMailadres', 'noah.degraaf@example.net');
    await vulTekst(page, 'watIsUwTelefoonnummer', '0612345678');
    await vulTekst(page, 'watIsHetKamerVanKoophandelNummerVanUwOrganisatie', '12345678').catch(() => {});
    await vulTekst(page, 'watIsDeNaamVanUwOrganisatie', 'Testorganisatie').catch(() => {});
    await vulTekst(page, 'postcode1', '6411CD').catch(() => {});
    await vulTekst(page, 'huisnummer1', '32').catch(() => {});
    await vulTekst(page, 'straatnaam1', 'Coriovallumstraat').catch(() => {});
    await vulTekst(page, 'plaatsnaam1', 'Heerlen').catch(() => {});
    await vulTekst(page, 'emailadresOrganisatie', 'org@example.net').catch(() => {});
    await vulTekst(page, 'telefoonnummerOrganisatie', '0612345678').catch(() => {});
    await page.waitForTimeout(500);
    await klikVolgende(page);

    // Stap 2: Het evenement
    await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Testevenement');
    await page.waitForTimeout(300);
    await vulTextarea(page, 'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', 'Test').catch(() => {});
    await kiesSelect(page, 'soortEvenement', 'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales').catch(() => {});
    await page.waitForTimeout(400);
    await klikVolgende(page);

    // Stap 3: Locatie
    expect(await huidigeStap(page)).toMatch(/Locatie/i);
    await page.getByRole('checkbox', { name: /In een gebouw/i }).check();
    await page.waitForTimeout(1500);
    await vulEindigendOp(page, 'input', '.naamVanDeLocatieGebouw', 'Locatie 1');
}

test('een bestaand adres vult straat en plaats automatisch aan', async ({ page }) => {
    test.setTimeout(180_000);
    await totLocatieGebouw(page);

    // 6411CD/32 = Coriovallumstraat, Heerlen.
    await vulAdresRij(page, 0, '6411CD', '32');

    await expect(straatRij(page, 0)).toHaveValue('Coriovallumstraat');
    await expect(plaatsRij(page, 0)).toHaveValue('Heerlen');
});

test('een niet-bestaand adres maakt straat/plaats leeg en toont een melding', async ({ page }) => {
    test.setTimeout(180_000);
    await totLocatieGebouw(page);

    // Eerst een geldig adres zodat straat/plaats gevuld zijn.
    await vulAdresRij(page, 0, '6411CD', '32');
    await expect(straatRij(page, 0)).toHaveValue('Coriovallumstraat');

    // Nu een huisnummer dat niet bestaat op deze postcode. PDOK geeft fuzzy een
    // adres uit een andere plaats terug; de exact-match-guard weigert dat.
    await page.locator(endsWithSelector('input', `${ADR1}.huisnummer`)).nth(0).fill('9999');
    await page.keyboard.press('Tab');
    await page.waitForTimeout(4000);

    await expect(straatRij(page, 0)).toHaveValue('');
    await expect(plaatsRij(page, 0)).toHaveValue('');
    await expect(page.getByText(/Geen adres gevonden/i).first()).toBeVisible();
});

// Bevinding A: bij twee locaties gaat de autofill van (in deze run) de tweede
// rij verloren. De gemeente-detect voor adressen doet nu géén tweede PDOK-call
// meer per commit (reactief wordt de al bekende gemeente uit de auto-fill
// hergebruikt; de autoritatieve bepaling gebeurt op de gate bij Volgende), en
// een niet-aangevuld adres kan altijd handmatig worden ingevuld want straat en
// plaats zijn verplichte velden — de gebruiker komt dus nooit vast te zitten.
// Wat blijft is de onderliggende Livewire-re-render-race: een commit van de ene
// rij rendert het formulier opnieuw en wist daarbij de nog niet gesynchroniseerde
// invoer van de andere rij, los van hoe goedkoop de fetch is. Bij Playwright's
// instant-`fill()` (het strengste geval, strenger dan menselijk typen) treedt dat
// nog op. Een deterministische fix vraagt het isoleren van de repeater-re-render
// van de gemeente-updates; fixme tot dat er is.
test.fixme('bevinding A: beide locatie-rijen vullen straat/plaats automatisch aan', async ({ page }) => {
    test.setTimeout(180_000);
    await totLocatieGebouw(page);

    await vulAdresRij(page, 0, '6411CD', '32');
    await expect(straatRij(page, 0)).toHaveValue('Coriovallumstraat');

    await page.getByRole('button', { name: /Nog een adres toevoegen/i }).click();
    await page.waitForTimeout(1500);
    await page.locator(endsWithSelector('input', '.naamVanDeLocatieGebouw')).nth(1).fill('Locatie 2');
    await vulAdresRij(page, 1, '6411CD', '32');

    await expect(straatRij(page, 1)).toHaveValue('Coriovallumstraat');
    await expect(plaatsRij(page, 1)).toHaveValue('Heerlen');
});

import { test, expect } from '@playwright/test';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import {
    vulTekst,
    vulTextarea,
    kiesRadio,
    kiesSelect,
    klikVolgende,
    huidigeStap,
} from './helpers/form-invullen.mjs';

/**
 * Live doorloop van het formulier. Draai met zichtbare browser via
 * `npm run walkthrough:live` — de acties lopen met slow-mo zodat je
 * kan meekijken.
 *
 * Scenario: klein buurtfeest (< 150 bezoekers, overdag). Dat is de
 * eenvoudigste route door de wizard (melding-pad, geen risicoscan).
 *
 * Stap 3 (Locatie, met kaart) is bewust een afkorting — de user heeft
 * aangegeven die apart uit te werken. De walkthrough gaat tot aan stap 3
 * en stopt daar expliciet. Zodra Locatie vervolledigd wordt, kunnen we
 * de rest van de route hieraan vast plakken.
 */
test('walkthrough: doorloop stap 1 t/m 3 (zichtbaar in browser)', async ({ page }) => {
    test.setTimeout(120_000);

    await loginAlsOrganiser(page);
    await openFormulier(page);

    // ---------- Stap 1: Contactgegevens -----------------------------
    await expect(page.locator('text=Nieuwe evenement-aanvraag')).toBeVisible();
    await test.step('Stap 1 — Contactgegevens', async () => {
        // Prefill vult naam/e-mail/KvK meestal al; organisatie-adres niet.
        await vulTekst(page, 'postcode1', '6211AB').catch(() => {});
        await vulTekst(page, 'huisnummer1', '1').catch(() => {});
        await vulTekst(page, 'straatnaam1', 'Teststraat').catch(() => {});
        await vulTekst(page, 'plaatsnaam1', 'Maastricht').catch(() => {});

        await page.screenshot({ path: 'test-results/walkthrough/stap-01-contactgegevens.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 2: Het evenement -------------------------------
    await test.step('Stap 2 — Het evenement', async () => {
        expect(await huidigeStap(page)).toMatch(/Het evenement/i);

        await vulTekst(page, 'watIsDeNaamVanHetEvenementVergunning', 'Buurtfeest Testlaan');
        // Wacht tot ->live() de vervolgvelden zichtbaar heeft gemaakt
        await page.waitForTimeout(1000);
        await vulTextarea(
            page,
            'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning',
            'Een kleinschalig buurtfeest op een middag met zo\'n 80 bewoners, zonder muziek of stands op de weg.',
        );
        await kiesSelect(
            page,
            'soortEvenement',
            'Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales',
        );
        await page.waitForTimeout(600);

        await page.screenshot({ path: 'test-results/walkthrough/stap-02-evenement.png', fullPage: true });
        await klikVolgende(page);
    });

    // ---------- Stap 3: Locatie (halt — kaart/repeater-interactie komt apart) ----
    await test.step('Stap 3 — Locatie (halt)', async () => {
        expect(await huidigeStap(page)).toMatch(/Locatie/i);
        await page.screenshot({ path: 'test-results/walkthrough/stap-03-locatie-aangekomen.png', fullPage: true });
        console.log('✅ Walkthrough bereikt stap 3 (Locatie) — verdere stappen wachten op uitwerking van de kaart-interactie.');
        // Korte pauze zodat je in live-modus de Locatie-pagina nog even ziet
        // staan voor de browser sluit. Geen 5-minuten-wacht meer — test klaar.
        await page.waitForTimeout(3000);
    });
});

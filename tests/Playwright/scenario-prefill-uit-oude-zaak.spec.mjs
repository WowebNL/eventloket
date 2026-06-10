import { test, expect, request as playwrightRequest } from '@playwright/test';
import { loginAlsOrganiser } from './helpers/login.mjs';
import { endsWithSelector } from './helpers/form-invullen.mjs';

/**
 * Browser-bewijs van het bestaansdoel van de Objects-backfill:
 * "herhaal aanvraag" — een nieuw formulier dat gevuld start met de
 * gegevens van een oude (gebackfillde) aanvraag.
 *
 * De data-keten (oude Objects-data → command → snapshot → PrefillLoader)
 * is op de PHP-laag bewezen. Deze test dekt de laatste laag: rendert het
 * formulier de prefill daadwerkelijk in de browser.
 *
 * Een test-only endpoint (/_test/seed-prefill-zaak, alleen local/testing)
 * maakt een Zaak met een snapshot zoals de backfill die produceert, en
 * geeft het zaak-id terug. We openen vervolgens het formulier met
 * ?prefill_from_zaak=<id> — exact wat de "herhaal aanvraag"-actie doet.
 */
test('herhaal aanvraag: formulier start gevuld met gegevens uit een oude zaak', async ({ page }) => {
    test.setTimeout(60_000);

    await loginAlsOrganiser(page);
    const tenant = new URL(page.url()).pathname.split('/')[2];

    // Seed een gebackfillde zaak en pak het id.
    const baseUrl = process.env.EF_BASE_URL || 'http://localhost';
    const ctx = await playwrightRequest.newContext({ baseURL: baseUrl });
    const resp = await ctx.post('/_test/seed-prefill-zaak', {
        form: { email: 'noah.degraaf@example.net' },
        timeout: 10_000,
    });
    expect(resp.ok(), 'seed-endpoint moet een zaak aanmaken').toBeTruthy();
    const { zaak_id: zaakId } = await resp.json();
    await ctx.dispose();
    expect(zaakId, 'seed moet een zaak-id teruggeven').toBeTruthy();

    // Open het formulier als "herhaal aanvraag" op basis van die zaak.
    await page.goto(`/organiser/${tenant}/aanvraag?prefill_from_zaak=${zaakId}`);

    // Op de eerste stap (Contactgegevens) moet de voornaam uit de oude
    // zaak al ingevuld staan — bewijs dat de prefill in de browser rendert.
    const voornaam = page.locator(endsWithSelector('input', '.watIsUwVoornaam')).first();
    await expect(voornaam).toHaveValue('PrefillEva', { timeout: 15_000 });

    const achternaam = page.locator(endsWithSelector('input', '.watIsUwAchternaam')).first();
    await expect(achternaam).toHaveValue('PrefillTest');
});

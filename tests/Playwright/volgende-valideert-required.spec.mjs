import { test, expect } from '@playwright/test';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';
import { leegDraftDb } from './helpers/wizard-flow.mjs';

/**
 * Klikken op "Volgende" zonder verplichte velden ingevuld te hebben moet
 * de stap-wissel blokkeren en rood-gemarkeerde fouten tonen.
 */
test('volgende-knop blijft op dezelfde stap bij ontbrekende verplichte velden', async ({ page }) => {
    // Schoon vertrekpunt zodat een oude draft de Contactgegevens-stap
    // niet pre-fillt — anders zou Volgende juist wél door mogen.
    await leegDraftDb();
    await loginAlsOrganiser(page);
    await openFormulier(page);

    await page.getByRole('button', { name: /^volgende$/i }).first().click();
    await page.waitForTimeout(1500);

    const errors = page.locator('.fi-fo-field-wrp-error-message');
    expect(await errors.count(), 'er moeten minimaal enkele "is verplicht"-meldingen verschijnen').toBeGreaterThan(0);

    const actief = page.locator('.fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-label');
    await expect(actief).toHaveText(/Contactgegevens/i);
});

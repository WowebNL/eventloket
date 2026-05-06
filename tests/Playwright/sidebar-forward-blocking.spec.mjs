import { test, expect } from '@playwright/test';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';

/**
 * Verifieert de UX-beveiliging: in de sidebar kun je niet vooruit-skippen
 * naar een stap die je nog niet hebt bereikt. Alleen de huidige + reeds
 * voltooide stappen zijn klikbaar.
 */
test('sidebar blokkeert navigatie naar nog-niet-bereikte stappen', async ({ page }) => {
    await loginAlsOrganiser(page);
    await openFormulier(page);

    const stepButtons = page.locator('.fi-vertical-wizard-step-btn');
    const count = await stepButtons.count();
    expect(count, 'het formulier heeft 17 stappen in de sidebar').toBe(17);

    await expect(stepButtons.nth(0)).toBeEnabled();

    for (let i = 1; i < count; i++) {
        await expect(stepButtons.nth(i), `stap ${i + 1} moet disabled zijn totdat stap ${i} is voltooid`).toBeDisabled();
    }
});

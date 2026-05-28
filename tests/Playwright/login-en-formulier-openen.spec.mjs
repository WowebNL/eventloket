import { test, expect } from '@playwright/test';
import { loginAlsOrganiser, openFormulier } from './helpers/login.mjs';

/**
 * Basis-walkthrough: kan een organiser inloggen en het evenementformulier
 * openen? Dit is de absolute minimumcheck die bij elke regressie direct
 * faalt. Geen OF-vergelijking — puur bewijs dat het formulier überhaupt
 * start zonder fouten.
 */
test('organiser kan inloggen en het aanvraag-formulier openen', async ({ page }) => {
    await loginAlsOrganiser(page);
    await openFormulier(page);

    await expect(page.getByRole('heading', { name: /nieuwe evenement-aanvraag/i })).toBeVisible();

    // Sidebar aanwezig + actieve stap is "Contactgegevens"
    const actieveStap = page.locator('.fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-label');
    await expect(actieveStap).toHaveText(/Contactgegevens/i);
});

import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import {
  fillContactgegevens,
  fillHetEvenement,
  fillLocatie,
  fillTijden,
  fillVooraankondiging,
  fillVergunningsplichtigScan,
  fillMelding,
} from './helpers/form-filler';

test.describe('Full Form Submission', () => {
  test('fill in and submit the evenement form via the melding path', async ({ page, context }) => {
    await context.clearCookies({ name: 'openforms_sessionid' });
    await loginAsOrganiser(page);

    // Navigate to "Nieuwe aanvraag"
    await page.click('text=Nieuwe aanvraag');
    await page.waitForURL('**/new-request/**', { timeout: 15000 });

    // Wait for "Formulier starten"
    const startButton = page.locator('#openforms-root button[type="submit"]');
    await expect(startButton).toBeVisible({ timeout: 30000 });
    await startButton.click();

    // Wait for first step
    await page.waitForURL('**/stap/**', { timeout: 30000 });

    // Step 1: Contactgegevens
    await fillContactgegevens(page);

    // Step 2: Het evenement
    await fillHetEvenement(page);

    // Step 3: Locatie
    await fillLocatie(page);

    // Step 4: Tijden
    await fillTijden(page);

    // Step 5: Vooraankondiging
    await fillVooraankondiging(page);

    // Step 6: Vergunningsplichtig scan
    await fillVergunningsplichtigScan(page);

    // Step 7: Melding
    await fillMelding(page);

    // Take screenshot to see where we are
    await page.screenshot({ path: 'test-results/form-progress.png' });
  });
});

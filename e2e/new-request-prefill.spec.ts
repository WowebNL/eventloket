import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';

test.describe('New Request — Contact Data Prefill', () => {
  test.beforeEach(async ({ page, context }) => {
    // Clear Open Forms session cookie to ensure fresh auth
    await context.clearCookies({ name: 'openforms_sessionid' });
    await loginAsOrganiser(page);
  });

  test('contact data is prefilled after starting the form', async ({ page }) => {
    // Navigate to "Nieuwe aanvraag"
    await page.click('text=Nieuwe aanvraag');

    // Wait for redirect (token generation) and Open Forms SDK to load
    await page.waitForURL('**/new-request/**', { timeout: 15000 });

    // Wait for the form to render — look for "Formulier starten"
    const startButton = page.locator('#openforms-root button[type="submit"]');
    await expect(startButton).toBeVisible({ timeout: 30000 });

    // Click "Formulier starten"
    await startButton.click();

    // Wait for the contact form step to load
    await page.waitForURL('**/stap/contactgegevens**', { timeout: 30000 });

    // Check that contact data is prefilled
    const voornaamField = page.locator('input[name="data[watIsUwVoornaam]"]');
    await expect(voornaamField).toBeVisible({ timeout: 10000 });

    // The value should not be empty — it should be prefilled from the token
    const voornaamValue = await voornaamField.inputValue();
    expect(voornaamValue).not.toBe('');
  });

  test('"Inloggen met Eventloket" button is hidden', async ({ page }) => {
    await page.click('text=Nieuwe aanvraag');
    await page.waitForURL('**/new-request/**', { timeout: 15000 });

    // Wait for SDK to render
    await page.locator('#openforms-root').waitFor({ timeout: 30000 });

    // The login button should be hidden via CSS
    const loginLink = page.locator('a:has-text("Inloggen met Eventloket")');
    await expect(loginLink).toBeHidden({ timeout: 10000 });
  });
});

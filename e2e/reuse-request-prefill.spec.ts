import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { exec } from 'child_process';
import { promisify } from 'util';

const execAsync = promisify(exec);

async function runArtisan(command: string): Promise<string> {
  const { stdout } = await execAsync(
    `docker compose -f docker-compose-full-architecture.yml exec -T laravel.test php artisan ${command}`,
    { timeout: 30000 }
  );
  return stdout;
}

test.describe('Reuse Request — Form Data Prefill', () => {
  test.beforeEach(async ({ page, context }) => {
    await context.clearCookies({ name: 'openforms_sessionid' });
    await loginAsOrganiser(page);
  });

  test('reuse flow opens form and prefills event name from previous submission', async ({ page }) => {
    // Navigate to zaak list
    await page.click('text=Aanvragen');

    // Wait for table
    const zaakRow = page.locator('table tbody tr').first();
    await expect(zaakRow).toBeVisible({ timeout: 15000 });

    // Click the zaak
    await zaakRow.click();

    // Wait for "Nieuwe aanvraag" button on detail page
    const reuseButton = page.getByRole('button', { name: 'Nieuwe aanvraag' });
    await expect(reuseButton).toBeVisible({ timeout: 15000 });

    // Click reuse
    await reuseButton.click();

    // Should redirect to reuse-request
    await page.waitForURL('**/reuse-request/**', { timeout: 15000 });

    // Wait for form to auto-start and navigate to first step (contactgegevens)
    await page.waitForURL('**/stap/**', { timeout: 45000 });

    // Check that contact data is prefilled on step 1
    const voornaamField = page.locator('input[name="data[watIsUwVoornaam]"]');
    await expect(voornaamField).toBeVisible({ timeout: 15000 });

    const voornaamValue = await voornaamField.inputValue();
    expect(voornaamValue).not.toBe('');

    // Check organisation name is prefilled
    const orgField = page.locator('input[name="data[watIsDeNaamVanUwOrganisatie]"]');
    const orgValue = await orgField.inputValue();
    expect(orgValue).toBe('Media Tuin');
  });

  test('"Inloggen met Eventloket" button is hidden on reuse', async ({ page }) => {
    await page.click('text=Aanvragen');

    const zaakRow = page.locator('table tbody tr').first();
    await expect(zaakRow).toBeVisible({ timeout: 15000 });
    await zaakRow.click();

    const reuseButton = page.getByRole('button', { name: 'Nieuwe aanvraag' });
    await expect(reuseButton).toBeVisible({ timeout: 15000 });
    await reuseButton.click();

    await page.waitForURL('**/reuse-request/**', { timeout: 15000 });

    // Wait for SDK to render
    await page.locator('#openforms-root').waitFor({ timeout: 30000 });

    // Login options should be hidden
    const loginLink = page.locator('a:has-text("Inloggen met Eventloket")');
    await expect(loginLink).toBeHidden({ timeout: 10000 });
  });
});

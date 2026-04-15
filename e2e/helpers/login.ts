import { Page } from '@playwright/test';

export async function loginAsOrganiser(
  page: Page,
  email = 'noah.degraaf@example.net',
  password = 'password',
  tenantName = 'Media Tuin'
) {
  await page.goto('/organiser/login');

  // Filament uses wire:model attributes, find by id
  await page.fill('#form\\.email', email);
  await page.fill('#form\\.password', password);

  // Click the submit button
  await page.click('button[type="submit"]');

  // Select tenant if presented
  const tenantLink = page.getByText(tenantName);
  if (await tenantLink.isVisible({ timeout: 5000 }).catch(() => false)) {
    await tenantLink.click();
  }

  // Wait for dashboard
  await page.waitForURL('**/organiser/**', { timeout: 10000 });
}

import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { fillContactgegevens, fillHetEvenement } from './helpers/form-filler';

test('debug step 3 after gebouw saved', async ({ page, context }) => {
  await context.clearCookies({ name: 'openforms_sessionid' });
  await loginAsOrganiser(page);
  await page.click('text=Nieuwe aanvraag');
  await page.waitForURL('**/new-request/**', { timeout: 15000 });
  const startButton = page.locator('#openforms-root button[type="submit"]');
  await expect(startButton).toBeVisible({ timeout: 30000 });
  await startButton.click();
  await page.waitForURL('**/stap/**', { timeout: 30000 });
  
  await fillContactgegevens(page);
  await fillHetEvenement(page);
  await page.waitForTimeout(2000);

  // Check gebouw
  await page.locator('input[value="gebouw"]').check();
  await page.waitForTimeout(2000);
  
  // Add gebouw row
  await page.locator('button:has-text("Adres van gebouw toevoegen")').click();
  await page.waitForTimeout(2000);
  await page.locator('input[name*="naamVanDeLocatieGebouw"]').first().fill('Testlocatie');
  await page.locator('input[name="postcode"]').first().fill('6211 AB');
  await page.locator('input[name="houseNumber"]').first().fill('1');
  await page.keyboard.press('Tab');
  await page.waitForTimeout(3000);
  
  // Save row
  await page.locator('button:has-text("bewaren")').first().click();
  await page.waitForTimeout(3000);
  
  // Now click Volgende and see what happens
  await page.locator('button:has-text("Volgende")').scrollIntoViewIfNeeded();
  await page.locator('button:has-text("Volgende")').click();
  await page.waitForTimeout(3000);
  
  // Check for errors and current URL
  console.log('URL after click:', page.url());
  
  const errors = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('[class*="error"], .formio-errors'))
      .map(el => el.textContent?.trim().substring(0, 80))
      .filter(t => t && t.length > 5);
  });
  console.log('Errors:', errors);

  // Check for radio buttons that appeared
  const radios = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('#openforms-root input[type="radio"]'))
      .filter(el => (el as HTMLElement).offsetParent !== null)
      .map(el => `name="${el.getAttribute('name')}" value="${el.getAttribute('value')}"`);
  });
  console.log('Visible radios:', radios);
  
  await page.screenshot({ path: 'test-results/step3b.png', fullPage: true });
});

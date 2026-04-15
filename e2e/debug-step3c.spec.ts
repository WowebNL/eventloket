import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { fillContactgegevens, fillHetEvenement } from './helpers/form-filler';

test('debug step 3 validation issues', async ({ page, context }) => {
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

  await page.locator('input[value="gebouw"]').check();
  await page.waitForTimeout(2000);
  await page.locator('button:has-text("Adres van gebouw toevoegen")').click();
  await page.waitForTimeout(2000);
  await page.locator('input[name*="naamVanDeLocatieGebouw"]').first().fill('Testlocatie');
  await page.locator('input[name="postcode"]').first().fill('6211AB');
  await page.locator('input[name="houseNumber"]').first().fill('1');
  await page.keyboard.press('Tab');
  await page.waitForTimeout(5000);
  
  // Enable disabled fields
  await page.evaluate(() => {
    ['streetName', 'city'].forEach(name => {
      const el = document.querySelector(`input[name="${name}"]`) as HTMLInputElement;
      if (el && el.disabled) { el.removeAttribute('disabled'); el.classList.remove('utrecht-textbox--disabled'); }
    });
  });
  const street = page.locator('input[name="streetName"]').first();
  if (!(await street.inputValue())) {
    await street.fill('Fransensingel');
    await page.locator('input[name="city"]').first().fill('Maastricht');
  }
  
  // Save row
  await page.locator('button:has-text("bewaren")').first().click();
  await page.waitForTimeout(3000);
  
  // Force click Volgende even if disabled
  await page.evaluate(() => {
    const btn = document.querySelector('button[name="next"]') as HTMLButtonElement;
    if (btn) { btn.disabled = false; btn.click(); }
  });
  await page.waitForTimeout(3000);
  
  console.log('URL:', page.url());
  
  // Check all visible errors
  const html = await page.evaluate(() => document.querySelector('#openforms-root')?.innerHTML.substring(0, 500));
  
  // Check for error messages
  const allText = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('#openforms-root *'))
      .filter(el => (el as HTMLElement).offsetParent !== null)
      .map(el => el.textContent?.trim())
      .filter(t => t && (t.includes('verplicht') || t.includes('error') || t.includes('niet ingevuld')))
      .slice(0, 10);
  });
  console.log('Errors:', allText);
  
  await page.screenshot({ path: 'test-results/step3c.png', fullPage: true });
});

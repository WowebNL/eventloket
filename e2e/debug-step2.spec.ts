import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { fillContactgegevens } from './helpers/form-filler';

test('find soort evenement field', async ({ page, context }) => {
  await context.clearCookies({ name: 'openforms_sessionid' });
  await loginAsOrganiser(page);
  await page.click('text=Nieuwe aanvraag');
  await page.waitForURL('**/new-request/**', { timeout: 15000 });
  const startButton = page.locator('#openforms-root button[type="submit"]');
  await expect(startButton).toBeVisible({ timeout: 30000 });
  await startButton.click();
  await page.waitForURL('**/stap/**', { timeout: 30000 });
  await fillContactgegevens(page);
  await page.waitForTimeout(2000);
  
  await page.fill('[name="data[watIsDeNaamVanHetEvenementVergunning]"]', 'Debug Test');
  await page.keyboard.press('Tab');
  await page.waitForTimeout(3000);

  // Find everything related to "soort"
  const soortElements = await page.evaluate(() => {
    const all = document.querySelectorAll('#openforms-root *');
    const results: string[] = [];
    all.forEach(el => {
      const name = el.getAttribute('name') || '';
      const id = el.id || '';
      const cls = el.className || '';
      const role = el.getAttribute('role') || '';
      if ((name + id + cls).toLowerCase().includes('soort') || 
          (name + id).includes('soortEvenement')) {
        results.push(`${el.tagName} name="${name}" id="${id}" role="${role}" class="${String(cls).substring(0, 60)}" visible=${(el as HTMLElement).offsetParent !== null}`);
      }
    });
    return results;
  });
  
  console.log('=== soortEvenement elements ===');
  soortElements.forEach(e => console.log(`  ${e}`));

  // Also check for react-select or similar dropdown components
  const selects = await page.evaluate(() => {
    const all = document.querySelectorAll('#openforms-root [role="listbox"], #openforms-root [role="combobox"], #openforms-root .choices, #openforms-root .formio-choices');
    return Array.from(all).map(el => `${el.tagName} class="${el.className.substring(0, 80)}" role="${el.getAttribute('role')}"`);
  });
  console.log('=== Custom selects ===');
  selects.forEach(e => console.log(`  ${e}`));
});

import { test, expect } from '@playwright/test';
import { loginAsOrganiser } from './helpers/login';
import { fillContactgegevens, fillHetEvenement } from './helpers/form-filler';

test('debug locatie step - watch what happens', async ({ page, context }) => {
  test.setTimeout(300000);
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
  
  // Add row
  await page.locator('button:has-text("Adres van gebouw toevoegen")').click();
  await page.waitForTimeout(2000);
  
  // Fill name
  await page.locator('input[name*="naamVanDeLocatieGebouw"]').first().fill('Testlocatie');
  
  // Fill postcode + huisnummer
  await page.locator('input[name="postcode"]').first().fill('6211AB');
  await page.locator('input[name="houseNumber"]').first().fill('1');
  await page.keyboard.press('Tab');
  
  // Wait long for API lookup
  console.log('Waiting for address lookup...');
  await page.waitForTimeout(10000);
  
  // Check street value
  const streetVal = await page.locator('input[name="streetName"]').first().inputValue();
  console.log('streetName value:', streetVal);
  const cityVal = await page.locator('input[name="city"]').first().inputValue();
  console.log('city value:', cityVal);
  
  // If empty, force fill
  if (!streetVal) {
    console.log('API lookup failed, force-filling...');
    await page.evaluate(() => {
      ['streetName', 'city'].forEach(name => {
        const el = document.querySelector(`input[name="${name}"]`) as HTMLInputElement;
        if (el) { 
          el.removeAttribute('disabled');
          el.classList.remove('utrecht-textbox--disabled');
          const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')!.set!;
          nativeInputValueSetter.call(el, name === 'streetName' ? 'Fransensingel' : 'Maastricht');
          el.dispatchEvent(new Event('input', { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        }
      });
    });
    await page.waitForTimeout(2000);
  }
  
  // Save row
  console.log('Saving row...');
  await page.locator('button:has-text("bewaren")').first().click();
  await page.waitForTimeout(5000);
  
  // Check if userSelectGemeente appeared
  const radios = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('#openforms-root input[type="radio"]'))
      .filter(el => (el as HTMLElement).offsetParent !== null)
      .map(el => `name="${el.getAttribute('name')}" value="${el.getAttribute('value')}"`);
  });
  console.log('Visible radios after save:', radios);
  
  // Check if Volgende is enabled
  const nextBtn = await page.locator('button[name="next"]');
  const isDisabled = await nextBtn.isDisabled();
  console.log('Volgende disabled:', isDisabled);
  
  // Try force-clicking volgende
  console.log('Force clicking Volgende...');
  await page.evaluate(() => {
    const btn = document.querySelector('button[name="next"]') as HTMLButtonElement;
    if (btn) { btn.disabled = false; btn.click(); }
  });
  await page.waitForTimeout(3000);
  
  console.log('URL after force click:', page.url());
  
  // Keep clicking until we get past locatie
  for (let i = 0; i < 5; i++) {
    const url = page.url();
    if (url.includes('tijden')) {
      console.log('Reached Tijden!');
      break;
    }
    console.log(`Attempt ${i+1}, URL: ${url}`);
    await page.evaluate(() => {
      const btn = document.querySelector('button[name="next"]') as HTMLButtonElement;
      if (btn) { btn.disabled = false; btn.click(); }
    });
    await page.waitForTimeout(3000);
  }
  
  console.log('Final URL:', page.url());
  await page.screenshot({ path: 'test-results/debug-locatie-final.png', fullPage: true });
});

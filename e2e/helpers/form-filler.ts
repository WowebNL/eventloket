import { Page, expect, Locator } from '@playwright/test';

/**
 * Helper to fill in the Open Forms Evenementformulier step by step.
 * Takes the "melding" (notification) path — the shortest route.
 */

export async function fillContactgegevens(page: Page) {
  await waitForStep(page, 'Contactgegevens');

  // Organisatie vestigingsadres (always visible, not prefilled)
  await fillField(page, 'postcode1', '6211AA');
  await fillField(page, 'straatnaam1', 'Markt');
  await fillField(page, 'huisnummer1', '1');
  await fillField(page, 'plaatsnaam1', 'Maastricht');

  // Check the "extra contactpersonen" checkboxes to reveal those fields
  await checkCheckbox(page, 'extraContactpersonenToevoegen', 'vooraf');
  await checkCheckbox(page, 'extraContactpersonenToevoegen', 'tijdens');
  await checkCheckbox(page, 'extraContactpersonenToevoegen', 'achteraf');
  await page.waitForTimeout(1000);

  // Contactpersoon vooraf
  await fillField(page, 'naam', 'Contact Vooraf');
  await fillField(page, 'telefoonnummer', '+31612345678');
  await fillField(page, 'eMailadres', 'vooraf@test.nl');

  // Contactpersoon tijdens
  await fillField(page, 'naam1', 'Contact Tijdens');
  await fillField(page, 'telefoonnummer1', '+31612345679');
  await fillField(page, 'eMailadres1', 'tijdens@test.nl');

  // Contactpersoon na
  await fillField(page, 'naam2', 'Contact Na');
  await fillField(page, 'telefoonnummer2', '+31612345680');
  await fillField(page, 'eMailadres2', 'na@test.nl');

  // Scroll down to find correspondentie adres if visible
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(500);

  // Correspondentie adres (may be same as vestigingsadres)
  await fillFieldIfVisible(page, 'postcode', '6211AA');
  await fillFieldIfVisible(page, 'straatnaam', 'Markt');
  await fillFieldIfVisible(page, 'huisnummer', '1');
  await fillFieldIfVisible(page, 'plaatsnaam', 'Maastricht');

  await clickVolgende(page);
}

export async function fillHetEvenement(page: Page) {
  await waitForStep(page, 'Het evenement');

  await fillField(page, 'watIsDeNaamVanHetEvenementVergunning', 'E2E Test Evenement');
  await page.waitForTimeout(1500); // Wait for conditional fields

  await fillFieldIfVisible(page, 'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', 'Een test evenement voor e2e');

  // Soort evenement — Choices.js dropdown (native select is hidden)
  await selectChoicesOption(page, 'soortEvenement');
  await page.keyboard.press('Escape'); // Close dropdown
  await page.waitForTimeout(2000); // Wait for conditional fields to appear

  await fillFieldIfVisible(page, 'omschrijfHetSoortEvenement', 'Een feestelijk evenement');

  await clickVolgende(page);
}

export async function fillLocatie(page: Page) {
  await waitForStep(page, 'Locatie');

  // Select "Buiten op één of meerdere plaatsen" — simpler than gebouw
  await checkCheckbox(page, 'waarVindtHetEvenementPlaats', 'buiten');
  await page.waitForTimeout(2000);

  // Open editgrid row
  const addLocatieButton = page.locator('button:has-text("locatie in te tekenen toevoegen")');
  await addLocatieButton.click();
  await page.waitForTimeout(2000);

  // Fill location name
  await page.locator('input[name*="naamVanDeLocatieKaart"]').first().fill('Testlocatie Buiten');

  // Draw on the Leaflet map — click the draw tool then click on the map
  const mapCanvas = page.locator('.leaflet-container').first();
  await mapCanvas.waitFor({ timeout: 10000 });

  // Click the polygon/marker draw tool (first toolbar button)
  const drawTool = page.locator('.leaflet-draw-toolbar a').first();
  if (await drawTool.isVisible({ timeout: 5000 }).catch(() => false)) {
    await drawTool.click();
    await page.waitForTimeout(500);

    // Click on the map to place points for a polygon
    const mapBox = await mapCanvas.boundingBox();
    if (mapBox) {
      const centerX = mapBox.x + mapBox.width / 2;
      const centerY = mapBox.y + mapBox.height / 2;
      await page.mouse.click(centerX - 30, centerY - 30);
      await page.waitForTimeout(300);
      await page.mouse.click(centerX + 30, centerY - 30);
      await page.waitForTimeout(300);
      await page.mouse.click(centerX + 30, centerY + 30);
      await page.waitForTimeout(300);
      await page.mouse.click(centerX - 30, centerY + 30);
      await page.waitForTimeout(300);
      // Double-click or click first point to close polygon
      await page.mouse.click(centerX - 30, centerY - 30);
      await page.waitForTimeout(1000);
    }
  }

  // Save the editgrid row (contains both name and map)
  const saveButton = page.locator('button:has-text("bewaren")').first();
  if (await saveButton.isVisible({ timeout: 5000 }).catch(() => false)) {
    await saveButton.click();
    await page.waitForTimeout(3000);
  }

  // Municipality selection (may appear after location is saved)
  await selectFirstRadioIfVisible(page, 'userSelectGemeente');
  await page.waitForTimeout(1000);

  // Navigate through potential sub-steps until we reach Tijden
  await clickVolgendeUntilStep(page, 'Tijden');
}

export async function fillTijden(page: Page) {
  await waitForStep(page, 'Tijden');

  // Datetime fields use flatpickr — set values via JavaScript to avoid picker UI
  await page.evaluate(() => {
    const inputs = document.querySelectorAll('#openforms-root input[placeholder*="dd-mm"]');
    const values = ['15-07-2026 10:00', '15-07-2026 22:00'];
    inputs.forEach((input, i) => {
      if (i < values.length) {
        const el = input as HTMLInputElement;
        const nativeSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!;
        nativeSetter.call(el, values[i]);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });
  await page.waitForTimeout(1000);

  await selectRadioByLabel(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee');
  await selectRadioByLabel(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee');
  await selectRadioByLabel(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee');
  await selectRadioByLabel(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee');

  await clickVolgende(page);
}

export async function fillVooraankondiging(page: Page) {
  await waitForStep(page, 'Vooraankondiging');

  await selectRadioByLabel(page, 'waarvoorWiltUEventloketGebruiken', 'evenement');
  await page.waitForTimeout(500);

  await fillField(page, 'aantalVerwachteAanwezigen', '100');

  await clickVolgende(page);
}

export async function fillVergunningsplichtigScan(page: Page) {
  await waitForStep(page, 'Vergunningsplichtig');

  // Answer Ja to all to get melding classification
  const radioQuestions = [
    'isHetAantalAanwezigenBijUwEvenementMinderDanSdf',
    'vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen',
    'WordtErAlleenMuziekGeluidGeproduceerdTussen',
    'IsdeGeluidsproductieLagerDan',
    'erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten',
    'wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst',
    'indienErObjectenGeplaatstWordenZijnDezeDanKleiner',
    'meldingvraag1',
    'meldingvraag2',
    'meldingvraag3',
    'meldingvraag4',
    'meldingvraag5',
  ];

  for (const q of radioQuestions) {
    await selectRadioByLabelIfVisible(page, q, 'Ja');
    await page.waitForTimeout(300);
  }

  await selectRadioByLabelIfVisible(page, 'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', 'Nee');

  await clickVolgende(page);
}

export async function fillMelding(page: Page) {
  await waitForStep(page, 'Melding');

  await selectRadioByLabel(page, 'wordtErAlcoholGeschonkenTijdensUwEvenement', 'Nee');
  await selectRadioByLabel(page, 'wordenErFilmopnamesMetBehulpVanDronesGemaakt', 'Nee');

  await clickVolgende(page);
}

// ============ Utility functions ============

async function waitForStep(page: Page, stepName: string) {
  // Wait for the step content to appear
  await page.waitForTimeout(1000);
  const heading = page.locator(`h2:has-text("${stepName}"), h3:has-text("${stepName}")`).first();
  if (await heading.isVisible({ timeout: 5000 }).catch(() => false)) {
    return;
  }
  // Fallback: just wait a bit
  await page.waitForTimeout(2000);
}

async function fillField(page: Page, name: string, value: string) {
  const field = page.locator(`[name="data[${name}]"]`);
  await field.waitFor({ timeout: 5000 });
  await field.fill(value);
}

async function fillFieldIfVisible(page: Page, name: string, value: string) {
  const field = page.locator(`[name="data[${name}]"]`);
  if (await field.isVisible({ timeout: 2000 }).catch(() => false)) {
    await field.fill(value);
  }
}

async function checkCheckbox(page: Page, name: string, value: string) {
  const checkbox = page.locator(`input[name="data[${name}][]"][value="${value}"]`);
  if (await checkbox.isVisible({ timeout: 3000 }).catch(() => false)) {
    if (!(await checkbox.isChecked())) {
      await checkbox.check();
    }
  }
}

async function selectRadioByLabel(page: Page, name: string, labelText: string) {
  // Open Forms radio names contain a dynamic suffix: data[fieldName][randomId]
  // The actual radio input is hidden — click the label instead
  const radio = page.locator(`input[type="radio"][name*="${name}"][value="${labelText}"]`).first();
  await radio.waitFor({ timeout: 5000 });

  // Click the label associated with this radio
  const label = radio.locator('xpath=ancestor::label');
  if (await label.isVisible({ timeout: 2000 }).catch(() => false)) {
    await label.click();
  } else {
    // Fallback: force-click the radio via JS
    await radio.evaluate((el) => (el as HTMLInputElement).click());
  }
  await page.waitForTimeout(500);
}

async function selectRadioByLabelIfVisible(page: Page, name: string, labelText: string) {
  const radio = page.locator(`input[type="radio"][name*="${name}"]`).first();
  if (await radio.isVisible({ timeout: 2000 }).catch(() => false)) {
    await selectRadioByLabel(page, name, labelText);
  }
}

async function selectFirstRadioIfVisible(page: Page, name: string) {
  const radio = page.locator(`input[name="data[${name}]"]`).first();
  if (await radio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await radio.click();
  }
}

async function fillDatetimeField(page: Page, name: string, date: string, time: string) {
  // Open Forms datetime uses flatpickr — find the input and type into it
  const container = page.locator(`[ref="component"][name="data[${name}]"]`).first();

  // Try direct input first
  const input = page.locator(`input[name="data[${name}]"]`).first();
  if (await input.isVisible({ timeout: 5000 }).catch(() => false)) {
    await input.click();
    await input.fill(`${date} ${time}`);
    await page.keyboard.press('Escape'); // Close any datepicker
    await page.waitForTimeout(300);
    return;
  }

  // Fallback: find by ID pattern
  const dateInput = page.locator(`[id*="${name}"]`).first();
  if (await dateInput.isVisible({ timeout: 3000 }).catch(() => false)) {
    await dateInput.fill(`${date} ${time}`);
    await page.keyboard.press('Escape');
  }
}

async function selectChoicesOption(page: Page, name: string, optionText?: string) {
  // Choices.js hides the real <select> and renders a custom dropdown.
  const choicesContainer = page.locator(`.choices:has(select[name="data[${name}]"])`).first();

  if (!(await choicesContainer.isVisible({ timeout: 5000 }).catch(() => false))) {
    return;
  }

  // Click to open dropdown
  await choicesContainer.click();
  await page.waitForTimeout(500);

  // Click the first non-placeholder option, or one matching optionText
  if (optionText) {
    const option = page.locator(`.choices__list--dropdown .choices__item--choice:has-text("${optionText}")`).first();
    await option.click();
  } else {
    // Click first selectable option
    const option = page.locator('.choices__list--dropdown .choices__item--selectable').first();
    await option.click();
  }
  await page.waitForTimeout(500);
}

async function clickVolgendeUntilStep(page: Page, stepName: string, maxAttempts = 5) {
  for (let i = 0; i < maxAttempts; i++) {
    // Check if we've reached the target step
    const heading = page.locator(`h2:has-text("${stepName}"), h3:has-text("${stepName}")`).first();
    if (await heading.isVisible({ timeout: 1000 }).catch(() => false)) {
      return;
    }

    // Also check sidebar for bold step name
    const sidebarActive = page.locator(`.openforms-progress-indicator li strong:has-text("${stepName}")`).first();
    if (await sidebarActive.isVisible({ timeout: 500 }).catch(() => false)) {
      return;
    }

    await clickVolgende(page);
  }
}

async function clickVolgende(page: Page) {
  const button = page.locator('button:has-text("Volgende")');
  await expect(button).toBeVisible({ timeout: 10000 });
  await button.scrollIntoViewIfNeeded();

  // Try normal click first, force-click if disabled
  try {
    await button.click({ timeout: 3000 });
  } catch {
    // Button might be disabled — force-enable and click via JS
    await page.evaluate(() => {
      const btn = document.querySelector('button[name="next"]') as HTMLButtonElement;
      if (btn) { btn.disabled = false; btn.click(); }
    });
  }

  await page.waitForTimeout(2000);
}

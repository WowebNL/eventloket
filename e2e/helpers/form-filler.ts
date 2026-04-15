import { Page, expect } from '@playwright/test';

/**
 * Helper to fill in the Open Forms Evenementformulier step by step.
 * Takes the "melding" (notification) path — the shortest route.
 */

export async function fillContactgegevens(page: Page) {
  await waitForStep(page, 'Contactgegevens');

  // Organisatie vestigingsadres (always visible, not prefilled)
  await fillFieldIfEmpty(page, 'postcode1', '6211AA');
  await fillFieldIfEmpty(page, 'straatnaam1', 'Markt');
  await fillFieldIfEmpty(page, 'huisnummer1', '1');
  await fillFieldIfEmpty(page, 'plaatsnaam1', 'Maastricht');

  // Check the "extra contactpersonen" checkboxes to reveal those fields
  await checkCheckbox(page, 'extraContactpersonenToevoegen', 'vooraf');
  await checkCheckbox(page, 'extraContactpersonenToevoegen', 'tijdens');
  await checkCheckbox(page, 'extraContactpersonenToevoegen', 'achteraf');
  await page.waitForTimeout(1000);

  // Contactpersoon vooraf
  await fillFieldIfEmpty(page, 'naam', 'Contact Vooraf');
  await fillFieldIfEmpty(page, 'telefoonnummer', '+31612345678');
  await fillFieldIfEmpty(page, 'eMailadres', 'vooraf@test.nl');

  // Contactpersoon tijdens
  await fillFieldIfEmpty(page, 'naam1', 'Contact Tijdens');
  await fillFieldIfEmpty(page, 'telefoonnummer1', '+31612345679');
  await fillFieldIfEmpty(page, 'eMailadres1', 'tijdens@test.nl');

  // Contactpersoon na
  await fillFieldIfEmpty(page, 'naam2', 'Contact Na');
  await fillFieldIfEmpty(page, 'telefoonnummer2', '+31612345680');
  await fillFieldIfEmpty(page, 'eMailadres2', 'na@test.nl');

  // Scroll down for correspondentie adres
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(500);

  await fillFieldIfVisible(page, 'postcode', '6211AA');
  await fillFieldIfVisible(page, 'straatnaam', 'Markt');
  await fillFieldIfVisible(page, 'huisnummer', '1');
  await fillFieldIfVisible(page, 'plaatsnaam', 'Maastricht');

  await clickVolgende(page);
}

export async function fillHetEvenement(page: Page) {
  await waitForStep(page, 'Het evenement');

  await fillField(page, 'watIsDeNaamVanHetEvenementVergunning', 'E2E Test Evenement');
  await page.waitForTimeout(1500);

  await fillFieldIfVisible(page, 'geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', 'Een test evenement voor e2e');

  // Soort evenement — Choices.js dropdown
  await selectChoicesOption(page, 'soortEvenement');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(2000);

  await fillFieldIfVisible(page, 'omschrijfHetSoortEvenement', 'Een feestelijk evenement');

  await clickVolgende(page);
}

export async function fillLocatie(page: Page) {
  await waitForStep(page, 'Locatie');

  // Select "Buiten op één of meerdere plaatsen"
  await checkCheckbox(page, 'waarVindtHetEvenementPlaats', 'buiten');
  await page.waitForTimeout(2000);

  // Open editgrid row
  const addLocatieButton = page.locator('button:has-text("locatie in te tekenen toevoegen")');
  await addLocatieButton.click();
  await page.waitForTimeout(2000);

  // Fill location name
  await page.locator('input[name*="naamVanDeLocatieKaart"]').first().fill('Testlocatie Buiten');

  // Draw on the Leaflet map
  const mapCanvas = page.locator('.leaflet-container').first();
  await mapCanvas.waitFor({ timeout: 10000 });

  const drawTool = page.locator('.leaflet-draw-toolbar a').first();
  if (await drawTool.isVisible({ timeout: 5000 }).catch(() => false)) {
    await drawTool.click();
    await page.waitForTimeout(500);

    const mapBox = await mapCanvas.boundingBox();
    if (mapBox) {
      const cx = mapBox.x + mapBox.width / 2;
      const cy = mapBox.y + mapBox.height / 2;
      await page.mouse.click(cx - 30, cy - 30);
      await page.waitForTimeout(300);
      await page.mouse.click(cx + 30, cy - 30);
      await page.waitForTimeout(300);
      await page.mouse.click(cx + 30, cy + 30);
      await page.waitForTimeout(300);
      await page.mouse.click(cx - 30, cy + 30);
      await page.waitForTimeout(300);
      await page.mouse.click(cx - 30, cy - 30);
      await page.waitForTimeout(1000);
    }
  }

  // Save the editgrid row
  const saveButton = page.locator('button:has-text("bewaren")').first();
  if (await saveButton.isVisible({ timeout: 5000 }).catch(() => false)) {
    await saveButton.click();
    await page.waitForTimeout(3000);
  }

  // Municipality selection
  await selectFirstRadioIfVisible(page, 'userSelectGemeente');
  await page.waitForTimeout(1000);

  await clickVolgendeUntilStep(page, 'Tijden');
}

export async function fillTijden(page: Page) {
  await waitForStep(page, 'Tijden');

  // Datetime fields — fill visible flatpickr inputs
  const visibleDateInputs = page.locator('#openforms-root input[placeholder*="dd-mm"]:visible');
  const dateValues = ['15-07-2026 10:00', '15-07-2026 22:00'];
  const visibleCount = await visibleDateInputs.count();
  for (let i = 0; i < Math.min(visibleCount, dateValues.length); i++) {
    const input = visibleDateInputs.nth(i);
    await input.click();
    await page.waitForTimeout(300);
    await page.keyboard.type(dateValues[i], { delay: 30 });
    await page.keyboard.press('Tab');
    await page.waitForTimeout(500);
  }
  await page.waitForTimeout(1000);

  await selectRadioByLabel(page, 'zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten', 'Nee');
  await selectRadioByLabel(page, 'zijnErTijdensHetEvenementXOpbouwactiviteiten', 'Nee');
  await selectRadioByLabel(page, 'zijnErAansluitendAanHetEvenementAfbouwactiviteiten', 'Nee');
  await selectRadioByLabel(page, 'zijnErTijdensHetEvenementXAfbouwactiviteiten3', 'Nee');

  await clickVolgende(page);
}

export async function fillVooraankondiging(page: Page) {
  await waitForStep(page, 'Vooraankondiging');

  await selectRadioByLabelIfVisible(page, 'waarvoorWiltUEventloketGebruiken', 'evenement');
  await page.waitForTimeout(1000);

  await fillFieldIfVisible(page, 'aantalVerwachteAanwezigen', '100');

  await clickVolgende(page);
}

export async function fillVergunningsplichtigScan(page: Page) {
  await waitForStep(page, 'Vergunningsplichtig');

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

/**
 * Navigate through remaining steps (Bijlagen, Type aanvraag) to the Overzicht,
 * then submit the form.
 */
export async function navigateToOverzichtAndSubmit(page: Page) {
  // After Melding, the form skips n.v.t. steps and goes to Bijlagen or further.
  // Keep clicking Volgende until we reach Overzicht or a submit button.
  for (let i = 0; i < 15; i++) {
    await page.waitForTimeout(3000);
    const url = page.url();

    // Check if we're on the summary/overzicht page
    if (url.includes('overzicht') || url.includes('bevestiging') || url.includes('confirmation')) {
      break;
    }

    // Check for a "Bevestigen" / "Verzenden" submit button
    const confirmButton = page.locator('button:has-text("Bevestigen"), button:has-text("Verzenden"), button:has-text("Indienen")');
    if (await confirmButton.isVisible({ timeout: 1000 }).catch(() => false)) {
      await confirmButton.click();
      await page.waitForTimeout(5000);
      return;
    }

    // Fill any required fields on the current step
    // Bijlagen: no required fields
    // Type aanvraag: no required fields

    // Click Volgende
    await clickVolgende(page);
  }

  // We should be on the Overzicht page — wait for it to fully load
  await page.waitForTimeout(10000);

  // Scroll to bottom to find the privacy checkbox and submit button
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await page.waitForTimeout(2000);

  // Check the privacy/consent checkbox (required before submit)
  const privacyCheckbox = page.locator('#openforms-root input[type="checkbox"]').last();
  if (await privacyCheckbox.isVisible({ timeout: 5000 }).catch(() => false)) {
    if (!(await privacyCheckbox.isChecked())) {
      const label = privacyCheckbox.locator('xpath=ancestor::label');
      if (await label.isVisible({ timeout: 2000 }).catch(() => false)) {
        await label.click();
      } else {
        await privacyCheckbox.check();
      }
      await page.waitForTimeout(1000);
    }
  }

  // Click Verzenden/Bevestigen
  const submitButton = page.locator('button:has-text("Verzenden"), button:has-text("Bevestigen")').first();
  await submitButton.scrollIntoViewIfNeeded();
  await submitButton.click({ timeout: 10000 });
  await page.waitForTimeout(15000);
}

// ============ Utility functions ============

async function waitForStep(page: Page, stepName: string) {
  await page.waitForTimeout(1000);
  const heading = page.locator(`h2:has-text("${stepName}"), h3:has-text("${stepName}")`).first();
  if (await heading.isVisible({ timeout: 5000 }).catch(() => false)) {
    return;
  }
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

async function fillFieldIfEmpty(page: Page, name: string, value: string) {
  const field = page.locator(`[name="data[${name}]"]`);
  if (await field.isVisible({ timeout: 3000 }).catch(() => false)) {
    const currentValue = await field.inputValue();
    if (!currentValue) {
      await field.fill(value);
    }
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
  const radio = page.locator(`input[type="radio"][name*="${name}"][value="${labelText}"]`).first();
  await radio.waitFor({ timeout: 5000 });
  const label = radio.locator('xpath=ancestor::label');
  if (await label.isVisible({ timeout: 2000 }).catch(() => false)) {
    await label.click();
  } else {
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
  const radio = page.locator(`input[type="radio"][name*="${name}"]`).first();
  if (await radio.isVisible({ timeout: 3000 }).catch(() => false)) {
    const label = radio.locator('xpath=ancestor::label');
    if (await label.isVisible({ timeout: 2000 }).catch(() => false)) {
      await label.click();
    } else {
      await radio.evaluate((el) => (el as HTMLInputElement).click());
    }
  }
}

async function selectChoicesOption(page: Page, name: string, optionText?: string) {
  const choicesContainer = page.locator(`.choices:has(select[name="data[${name}]"])`).first();
  if (!(await choicesContainer.isVisible({ timeout: 5000 }).catch(() => false))) {
    return;
  }
  await choicesContainer.click();
  await page.waitForTimeout(500);
  if (optionText) {
    const option = page.locator(`.choices__list--dropdown .choices__item--choice:has-text("${optionText}")`).first();
    await option.click();
  } else {
    const option = page.locator('.choices__list--dropdown .choices__item--selectable').first();
    await option.click();
  }
  await page.waitForTimeout(500);
}

async function clickVolgendeUntilStep(page: Page, stepName: string, maxAttempts = 5) {
  for (let i = 0; i < maxAttempts; i++) {
    const heading = page.locator(`h2:has-text("${stepName}"), h3:has-text("${stepName}")`).first();
    if (await heading.isVisible({ timeout: 1000 }).catch(() => false)) {
      return;
    }
    await clickVolgende(page);
  }
}

async function clickVolgende(page: Page) {
  const button = page.locator('button:has-text("Volgende")');
  await expect(button).toBeVisible({ timeout: 10000 });
  await button.scrollIntoViewIfNeeded();
  try {
    await button.click({ timeout: 3000 });
  } catch {
    await page.evaluate(() => {
      const btn = document.querySelector('button[name="next"]') as HTMLButtonElement;
      if (btn) { btn.disabled = false; btn.click(); }
    });
  }
  await page.waitForTimeout(2000);
}

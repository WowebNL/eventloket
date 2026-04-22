import { expect } from '@playwright/test';

/**
 * Helpers om velden in het Filament-formulier in te vullen. Filament's
 * inputs hebben `wire:model="data.<key>"` als binding, dus we selecteren
 * op dat attribuut i.p.v. op fragiele labels.
 */

export async function vulTekst(page, key, waarde) {
    const input = page.locator(
        `input[wire\\:model="data.${key}"], input[wire\\:model\\.live="data.${key}"], input[wire\\:model\\.defer="data.${key}"]`
    ).first();
    await input.fill(waarde);
}

export async function vulTextarea(page, key, waarde) {
    const input = page.locator(
        `textarea[wire\\:model="data.${key}"], textarea[wire\\:model\\.live="data.${key}"], textarea[wire\\:model\\.defer="data.${key}"]`
    ).first();
    await input.fill(waarde);
}

export async function kiesRadio(page, key, value) {
    // Radios hebben wire:model op een wrapper; de inputs zelf hebben value=X
    const radio = page.locator(`input[type=radio][name*="${key}"][value="${value}"]`).first();
    await radio.check();
}

export async function kiesSelect(page, key, value) {
    const select = page.locator(
        `select[wire\\:model="data.${key}"], select[wire\\:model\\.live="data.${key}"]`
    ).first();
    await select.selectOption(value);
}

export async function vinkCheckboxAan(page, key, optie) {
    // Selectboxes/CheckboxList in Filament: input type=checkbox met value=optie
    const cb = page.locator(`input[type=checkbox][value="${optie}"]`).first();
    await cb.check();
}

export async function klikVolgende(page) {
    const btn = page.getByRole('button', { name: /^volgende$/i }).first();
    await btn.click();
    // Wacht op livewire-update + re-render
    await page.waitForTimeout(1200);
    // Als validatie faalt, blijft de Volgende-knop zichtbaar en is er een
    // veld met rode rand / fout-tekst. Check op error-meldingen.
    const errors = await page.locator('.fi-fo-field-wrp-error-message').count();
    if (errors > 0) {
        const teksten = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
        throw new Error(`Validatie faalt: ${teksten.join(' | ')}`);
    }
}

export async function huidigeStap(page) {
    return page.locator('.fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-label').textContent();
}

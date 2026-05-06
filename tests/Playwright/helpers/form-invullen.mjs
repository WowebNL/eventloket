/**
 * Helpers om velden in het Filament-formulier in te vullen. Filament
 * gebruikt verschillende wire:model-varianten — `wire:model` (standaard),
 * `wire:model.live` (reactief), `wire:model.blur` (AddressNL postcode
 * e.d.), `wire:model.defer` (oude form-state-modus). Onze selectoren
 * proberen ze allemaal in volgorde totdat er één lokaliseert.
 */

const MODIFIERS = ['', '.live', '.defer', '.blur', '.debounce.500ms'];

function selectorVoor(tag, attr) {
    // Bouw een CSS-selector die één van de wire:model-varianten matcht.
    return MODIFIERS.map((m) => `${tag}[wire\\:model${m.replace(/\./g, '\\.')}="${attr}"]`).join(', ');
}

function endsWithSelector(tag, suffix) {
    // Voor een eind-match (bv. een pad dat eindigt op ".postcode")
    return MODIFIERS.map((m) => `${tag}[wire\\:model${m.replace(/\./g, '\\.')}$="${suffix}"]`).join(', ');
}

export async function vulTekst(page, key, waarde) {
    await page.locator(selectorVoor('input', `data.${key}`)).first().fill(waarde);
}

export async function vulTextarea(page, key, waarde) {
    await page.locator(selectorVoor('textarea', `data.${key}`)).first().fill(waarde);
}

export async function vulEindigendOp(page, tag, suffix, waarde) {
    await page.locator(endsWithSelector(tag, suffix)).first().fill(waarde);
}

export async function kiesRadio(page, key, value) {
    const radio = page.locator(`input[type=radio][name*="${key}"][value="${value}"]`).first();
    await radio.check();
}

/**
 * Vinkt een radio alleen aan als het veld bestaat en zichtbaar is. Geen
 * fout als het veld (nog) niet in de DOM zit — handig voor cascade-stappen
 * waar pas vragen verschijnen na antwoord op vorige vraag.
 */
export async function kiesRadioOptioneel(page, key, value, { timeout = 2500, wachtOpVerschijnen = 2000 } = {}) {
    const radio = page.locator(`input[type=radio][name*="${key}"][value="${value}"]`).first();
    // Wacht tot de radio in de DOM verschijnt — bij cascade-velden duurt
    // het een Livewire-roundtrip voordat ze getoond worden na een klik op
    // de vorige vraag. `count() === 0` was te vroeg; `waitFor` met een
    // korte timeout vangt die vertraging op. Niet gevonden? → stil skip.
    try {
        await radio.waitFor({ state: 'attached', timeout: wachtOpVerschijnen });
    } catch {
        return false;
    }
    try {
        await radio.check({ timeout });
        return true;
    } catch {
        return false;
    }
}

export async function kiesSelect(page, key, value) {
    await page.locator(selectorVoor('select', `data.${key}`)).first().selectOption(value);
}

export async function vinkCheckboxAan(page, key, optie) {
    const cb = page.locator(`input[type=checkbox][value="${optie}"]`).first();
    await cb.check();
}

export async function klikVolgende(page) {
    const btn = page.getByRole('button', { name: /^volgende$/i }).first();
    await btn.click();
    await page.waitForTimeout(1200);
    const errors = await page.locator('.fi-fo-field-wrp-error-message').count();
    if (errors > 0) {
        const teksten = await page.locator('.fi-fo-field-wrp-error-message').allTextContents();
        throw new Error(`Validatie faalt: ${teksten.join(' | ')}`);
    }
}

export async function huidigeStap(page) {
    return page.locator('.fi-vertical-wizard-step[data-status="active"] .fi-vertical-wizard-step-label').textContent();
}

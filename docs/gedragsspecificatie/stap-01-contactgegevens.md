# Stap 1: Contactgegevens

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 7/7 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Conditionele zichtbaarheid van velden en stappen

Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ Zichtbaarheid "contactpersoonVoorafgaandAanHetEvenement" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `extraContactpersonenToevoegen` = `vooraf` — moet veld `contactpersoonVoorafgaandAanHetEvenement` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Extra contactpersonen toevoegen" — `0` uit

**Dan verwachten we:**
- Veld "Contactpersoon voorafgaand aan het evenement" _(op Stap 1: Contactgegevens)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "contactpersoonVoorafgaandAanHetEvenement" — trigger matcht niet (auto)

Met een waarde die niet matcht — `extraContactpersonenToevoegen` is iets anders dan `vooraf` — moet veld `contactpersoonVoorafgaandAanHetEvenement` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Extra contactpersonen toevoegen" — 

**Dan verwachten we:**
- Veld "Contactpersoon voorafgaand aan het evenement" _(op Stap 1: Contactgegevens)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "contactpersoonVoorafgaandAanHetEvenement1" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `extraContactpersonenToevoegen` = `tijdens` — moet veld `contactpersoonVoorafgaandAanHetEvenement1` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Extra contactpersonen toevoegen" — `0` uit

**Dan verwachten we:**
- Veld "Contactpersoon tijdens het evenement" _(op Stap 1: Contactgegevens)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "contactpersoonVoorafgaandAanHetEvenement1" — trigger matcht niet (auto)

Met een waarde die niet matcht — `extraContactpersonenToevoegen` is iets anders dan `tijdens` — moet veld `contactpersoonVoorafgaandAanHetEvenement1` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Extra contactpersonen toevoegen" — 

**Dan verwachten we:**
- Veld "Contactpersoon tijdens het evenement" _(op Stap 1: Contactgegevens)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "contactpersoonVoorafgaandAanHetEvenement2" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `extraContactpersonenToevoegen` = `achteraf` — moet veld `contactpersoonVoorafgaandAanHetEvenement2` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Extra contactpersonen toevoegen" — `0` uit

**Dan verwachten we:**
- Veld "Contactpersoon na het evenement" _(op Stap 1: Contactgegevens)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "contactpersoonVoorafgaandAanHetEvenement2" — trigger matcht niet (auto)

Met een waarde die niet matcht — `extraContactpersonenToevoegen` is iets anders dan `achteraf` — moet veld `contactpersoonVoorafgaandAanHetEvenement2` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Extra contactpersonen toevoegen" — 

**Dan verwachten we:**
- Veld "Contactpersoon na het evenement" _(op Stap 1: Contactgegevens)_ is **niet zichtbaar** in de rendered pagina

### ✅ KvK-gebruiker — adresgegevens verborgen

Gebruiker ingelogd via eHerkenning/KvK heeft de organisatie-gegevens al uit de KvK-koppeling. "Organisatie-informatie" wordt zichtbaar om de opgehaalde gegevens te tonen; "Adresgegevens" wordt verborgen omdat het adres al bekend is.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `eventloketSession.kvk` = "12345678"

**Dan verwachten we:**
- Veld "Organisatie informatie" _(op Stap 1: Contactgegevens)_ wordt **zichtbaar**
- Veld "Adresgegevens" _(op Stap 1: Contactgegevens)_ wordt **verborgen**

# Stap 12: Vergunningsaanvraag: voorwerpen

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 1/1 gedekt.

## Conditionele zichtbaarheid van velden en stappen

Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ Speeltoestellen — voorwerpen-stap van toepassing na A25

Als de organisator aangeeft speeltoestellen te plaatsen (optie A25 in "welke voorwerpen gaat u plaatsen"), moeten "Speeltoestellen" en "voorwerpen" zichtbaar zijn én wordt de stap "Vergunningsaanvraag: voorwerpen" actief.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorwerpen gaat u plaatsen bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Speeltoestellen Attractietoestellen" aangevinkt

**Dan verwachten we:**
- Veld "Speeltoestellen" _(op Stap 12: Vergunningsaanvraag: voorwerpen)_ wordt **zichtbaar**
- Veld "Voorwerpen" _(op Stap 12: Vergunningsaanvraag: voorwerpen)_ wordt **zichtbaar**
- Stap 12: Vergunningsaanvraag: voorwerpen wordt **van toepassing** (getoond in sidebar)

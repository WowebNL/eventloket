# Stap 11: Vergunningsaanvraag: voorzieningen

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 4/4 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Detail-velden per aangevinkte voorziening

Voor elke voorziening die de organisator aankruist (wc's, douches, etc.) moet een detail-veld verschijnen waarin de organisator bijvoorbeeld het aantal kan aangeven. Deze pagina wordt ook automatisch als van toepassing gemarkeerd zodra minstens één voorziening is aangevinkt.

### ✅ Zichtbaarheid "hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar` = `0` — moet veld `hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar` **verborgen** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Hoeveel tijdelijke chemische toiletten / Dixies zijn er beschikbaar?" = `0`

**Dan verwachten we:**
- Veld "Hoeveel tijdelijke gespoelde toiletten zijn er beschikbaar?" _(op Stap 11: Vergunningsaanvraag: voorzieningen)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar" — trigger matcht niet (auto)

Met een waarde die niet matcht — `hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar` is iets anders dan `0` — moet veld `hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar` **zichtbaar** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Hoeveel tijdelijke chemische toiletten / Dixies zijn er beschikbaar?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Hoeveel tijdelijke gespoelde toiletten zijn er beschikbaar?" _(op Stap 11: Vergunningsaanvraag: voorzieningen)_ is **zichtbaar** in de rendered pagina

### ✅ WCs aangevinkt → detailveld voor aantallen verschijnt

Als de organisator bij de voorzieningen-checkboxen optie A12 (wc's) aanvinkt, wordt het detail-veld zichtbaar waarin de aantallen wc's kunnen worden ingevuld. De pagina voorzieningen zelf wordt als van toepassing gemarkeerd in de sidebar.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "WC's plaatsen (of bestaande gebruiken) " aangevinkt

**Dan verwachten we:**
- Veld "WC's" _(op Stap 11: Vergunningsaanvraag: voorzieningen)_ wordt **zichtbaar**
- Stap 11: Vergunningsaanvraag: voorzieningen wordt **van toepassing** (getoond in sidebar)

### ✅ Douches aangevinkt → detailveld voor douches verschijnt

Net als bij WCs: als de organisator douches (optie A13) aanvinkt in de voorzieningen-lijst, wordt het douches-detailveld zichtbaar zodat de organisator aantallen/locaties kan doorgeven.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Douches plaatsen (of bestaande gebruiken) " aangevinkt

**Dan verwachten we:**
- Veld "Douche's" _(op Stap 11: Vergunningsaanvraag: voorzieningen)_ wordt **zichtbaar**
- Stap 11: Vergunningsaanvraag: voorzieningen wordt **van toepassing** (getoond in sidebar)

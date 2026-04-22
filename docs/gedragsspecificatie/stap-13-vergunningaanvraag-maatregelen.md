# Stap 13: Vergunningaanvraag: maatregelen

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 2/2 gedekt.

## Detail-velden voor overige kenmerken

Voor kenmerken als grote voertuigen op de openbare weg (A48 of A49) verschijnt een detail-veld waarin de organisator specifieke afspraken kan doorgeven. De pagina "overig" wordt automatisch van toepassing.

### ✅ Plaatsen object op openbare weg (A48) → detail-veld verschijnt

Als de organisator aangeeft objecten op de openbare weg te plaatsen (kenmerk A48), moet het detail-veld "groteVoertuigen" zichtbaar worden om de aanvullende gegevens te kunnen invullen.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan wat voor overige kenmerken van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}" — "Voertuigen parkeren die langer zijn dan 6 meter en/of hoger dan 2,40 meter" aangevinkt

**Dan verwachten we:**
- Veld "Voorwerpen op de weg" _(op Stap 15: Vergunningaanvraag: overig)_ wordt **zichtbaar**
- Stap 13: Vergunningaanvraag: maatregelen wordt **van toepassing** (getoond in sidebar)

### ✅ Parkeren grote voertuigen (A49) → detail-veld verschijnt

Bij de keuze om grote voertuigen te parkeren op de openbare weg (A49) verschijnt hetzelfde detail-veld voor aanvullende gegevens.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan wat voor overige kenmerken van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}" — "Voorwerpen op de weg plaatsen" aangevinkt

**Dan verwachten we:**
- Veld "Voorwerpen op de weg" _(op Stap 15: Vergunningaanvraag: overig)_ wordt **zichtbaar**
- Stap 13: Vergunningaanvraag: maatregelen wordt **van toepassing** (getoond in sidebar)

# Stap 13: Vergunningaanvraag: maatregelen

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 8/8 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Detail-velden voor overige kenmerken

Voor kenmerken als grote voertuigen op de openbare weg (A48 of A49) verschijnt een detail-veld waarin de organisator specifieke afspraken kan doorgeven. De pagina "overig" wordt automatisch van toepassing.

### ✅ Zichtbaarheid "uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `voertUDeSchoonmaakZelfUit` = `Ja` — moet veld `uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Voert u de schoonmaak zelf uit? " = "Ja"

**Dan verwachten we:**
- Veld "U kunt het afvalplan hier uploaden of later als bijlage toevoegen." _(op Stap 13: Vergunningaanvraag: maatregelen)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `voertUDeSchoonmaakZelfUit` is iets anders dan `Ja` — moet veld `uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Voert u de schoonmaak zelf uit? " = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "U kunt het afvalplan hier uploaden of later als bijlage toevoegen." _(op Stap 13: Vergunningaanvraag: maatregelen)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "veldengroep2" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `wilUGebruikMakenVanGemeentelijkeHulpmiddelen` = `Ja` — moet veld `veldengroep2` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wil U gebruik maken van gemeentelijke hulpmiddelen?" = "Ja"

**Dan verwachten we:**
- Veld "Veldengroep" _(op Stap 13: Vergunningaanvraag: maatregelen)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "veldengroep2" — trigger matcht niet (auto)

Met een waarde die niet matcht — `wilUGebruikMakenVanGemeentelijkeHulpmiddelen` is iets anders dan `Ja` — moet veld `veldengroep2` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wil U gebruik maken van gemeentelijke hulpmiddelen?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Veldengroep" _(op Stap 13: Vergunningaanvraag: maatregelen)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "geefAanOpWelkeLocatieUStroomWilt1" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `wenstUTegenBetalingStroomAfTeNemenVanDeGemeente` = `Ja` — moet veld `geefAanOpWelkeLocatieUStroomWilt1` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `wenstUTegenBetalingStroomAfTeNemenVanDeGemeente` = "Ja"

**Dan verwachten we:**
- Veld "Geef aan op welke locatie u stroom wilt afnemen" _(op Stap 13: Vergunningaanvraag: maatregelen)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "geefAanOpWelkeLocatieUStroomWilt1" — trigger matcht niet (auto)

Met een waarde die niet matcht — `wenstUTegenBetalingStroomAfTeNemenVanDeGemeente` is iets anders dan `Ja` — moet veld `geefAanOpWelkeLocatieUStroomWilt1` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `wenstUTegenBetalingStroomAfTeNemenVanDeGemeente` = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Geef aan op welke locatie u stroom wilt afnemen" _(op Stap 13: Vergunningaanvraag: maatregelen)_ is **niet zichtbaar** in de rendered pagina

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

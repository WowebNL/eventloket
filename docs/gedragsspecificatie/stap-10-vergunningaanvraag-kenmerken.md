# Stap 10: Vergunningaanvraag: kenmerken

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 21/21 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Conditionele zichtbaarheid van velden en stappen

Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ Zichtbaarheid "opWelkeAndereManierWordtErMuziekGemaakt" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning` = `anders` — moet veld `opWelkeAndereManierWordtErMuziekGemaakt` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wie maakt de muziek op locatie bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Op welke andere manier wordt er muziek gemaakt?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "opWelkeAndereManierWordtErMuziekGemaakt" — trigger matcht niet (auto)

Met een waarde die niet matcht — `wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning` is iets anders dan `anders` — moet veld `opWelkeAndereManierWordtErMuziekGemaakt` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wie maakt de muziek op locatie bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Op welke andere manier wordt er muziek gemaakt?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` = `A71` — moet veld `welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Welke soorten Dance muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` is iets anders dan `A71` — moet veld `welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Welke soorten Dance muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` = `A72` — moet veld `welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` is iets anders dan `A72` — moet veld `welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement` = `anders` — moet veld `welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Welke ander soort popmuziek is er te horen op evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement` is iets anders dan `anders` — moet veld `welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Welke ander soort popmuziek is er te horen op evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "geefEenOmschrijvingVanSoortOmheining" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `watVoorBouwselsPlaatsUOpDeLocaties` = `A57` — moet veld `geefEenOmschrijvingVanSoortOmheining` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat voor bouwsels plaats u op de locaties?" — `0` uit

**Dan verwachten we:**
- Veld "Geef een omschrijving van soort omheining" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "geefEenOmschrijvingVanSoortOmheining" — trigger matcht niet (auto)

Met een waarde die niet matcht — `watVoorBouwselsPlaatsUOpDeLocaties` is iets anders dan `A57` — moet veld `geefEenOmschrijvingVanSoortOmheining` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat voor bouwsels plaats u op de locaties?" — 

**Dan verwachten we:**
- Veld "Geef een omschrijving van soort omheining" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `isDeOrganisatieVanHetKansspelInHandenVanEenVereniging` = `Ja` — moet veld `bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is de organisatie van het kansspel in handen van een vereniging?" = "Ja"

**Dan verwachten we:**
- Veld "Bestaat de vereninging, die het kansspel organiseert langer dan 3 jaar?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar" — trigger matcht niet (auto)

Met een waarde die niet matcht — `isDeOrganisatieVanHetKansspelInHandenVanEenVereniging` is iets anders dan `Ja` — moet veld `bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is de organisatie van het kansspel in handen van een vereniging?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Bestaat de vereninging, die het kansspel organiseert langer dan 3 jaar?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "persoongroep" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop` = `persoon` — moet veld `persoongroep` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is een persoon of organisatie verantwoordelijk voor de alcoholverkoop?" = "persoon" (_Persoon_)

**Dan verwachten we:**
- Veld "Persoongroep" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "persoongroep" — trigger matcht niet (auto)

Met een waarde die niet matcht — `isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop` is iets anders dan `persoon` — moet veld `persoongroep` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is een persoon of organisatie verantwoordelijk voor de alcoholverkoop?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Persoongroep" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "organisatiegroep" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop` = `organisatie` — moet veld `organisatiegroep` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is een persoon of organisatie verantwoordelijk voor de alcoholverkoop?" = "organisatie" (_Organisatie_)

**Dan verwachten we:**
- Veld "Organisatiegroep" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "organisatiegroep" — trigger matcht niet (auto)

Met een waarde die niet matcht — `isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop` is iets anders dan `organisatie` — moet veld `organisatiegroep` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is een persoon of organisatie verantwoordelijk voor de alcoholverkoop?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Organisatiegroep" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "waarvanMetAlcohol" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken.uitgiftepuntenDrank` = `0` — moet veld `waarvanMetAlcohol` **verborgen** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Op hoeveel punten en op welke locaties gaat u dranken en voedsel verstrekken?" = `0`

**Dan verwachten we:**
- Veld "Waarvan met alcohol" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "waarvanMetAlcohol" — trigger matcht niet (auto)

Met een waarde die niet matcht — `watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken.uitgiftepuntenDrank` is iets anders dan `0` — moet veld `waarvanMetAlcohol` **zichtbaar** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Op hoeveel punten en op welke locaties gaat u dranken en voedsel verstrekken?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Waarvan met alcohol" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAndereWarmtebronWordtGebruikt" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX` = `anders` — moet veld `welkeAndereWarmtebronWordtGebruikt` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Met welke warmtebron wordt het eten ter plaatse klaargemaakt  op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Welke andere warmtebron wordt gebruikt?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAndereWarmtebronWordtGebruikt" — trigger matcht niet (auto)

Met een waarde die niet matcht — `metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX` is iets anders dan `anders` — moet veld `welkeAndereWarmtebronWordtGebruikt` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Met welke warmtebron wordt het eten ter plaatse klaargemaakt  op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Welke andere warmtebron wordt gebruikt?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ is **niet zichtbaar** in de rendered pagina

### ✅ Bouwsels >10 m² — velden en stap zichtbaar na aanvinken

Als de organisator bij "wat is van toepassing voor uw evenement" de optie A3 (bouwsels groter dan 10 m²) aanvinkt, moeten de vervolg-velden zichtbaar worden en wordt de stap "Vergunningsaanvraag: extra activiteiten" in de sidebar actief.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan wat van toepassing is voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Bouwsels plaatsen groter dan 10m2, zoals tenten of podia" aangevinkt

**Dan verwachten we:**
- Veld "Bouwsels > 10m<sup>2</sup> " _(op Stap 10: Vergunningaanvraag: kenmerken)_ wordt **zichtbaar**
- Veld "Wat voor bouwsels plaats u op de locaties?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ wordt **zichtbaar**
- Stap 10: Vergunningaanvraag: kenmerken wordt **van toepassing** (getoond in sidebar)

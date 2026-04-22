# Stap 2: Het evenement

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 11/11 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Conditionele zichtbaarheid op stap "Het evenement"

Op deze stap bepalen een paar velden of vervolgvragen te zien zijn. Zodra de evenementnaam is ingevuld, verschijnen omschrijving- en soort-velden. Bij "Anders" als soort komt er een extra tekstveld vrij. Bij "Markt of braderie" komt er een periodieke-markt-vraag vrij.

### ✅ Zichtbaarheid "geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `watIsDeNaamVanHetEvenementVergunning` = `` — moet veld `geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning` **verborgen** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = `null`

**Dan verwachten we:**
- Veld "Geef een korte omschrijving van het evenement {{ watIsDeNaamVanHetEvenementVergunning }}" _(op Stap 2: Het evenement)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning" — trigger matcht niet (auto)

Met een waarde die niet matcht — `watIsDeNaamVanHetEvenementVergunning` is iets anders dan `` — moet veld `geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning` **zichtbaar** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "niet leeg"

**Dan verwachten we:**
- Veld "Geef een korte omschrijving van het evenement {{ watIsDeNaamVanHetEvenementVergunning }}" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "soortEvenement" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `watIsDeNaamVanHetEvenementVergunning` = `` — moet veld `soortEvenement` **verborgen** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = `null`

**Dan verwachten we:**
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 2: Het evenement)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "soortEvenement" — trigger matcht niet (auto)

Met een waarde die niet matcht — `watIsDeNaamVanHetEvenementVergunning` is iets anders dan `` — moet veld `soortEvenement` **zichtbaar** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "niet leeg"

**Dan verwachten we:**
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "omschrijfHetSoortEvenement" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `soortEvenement` = `Anders` — moet veld `omschrijfHetSoortEvenement` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Anders"

**Dan verwachten we:**
- Veld "Omschrijf het soort evenement" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "omschrijfHetSoortEvenement" — trigger matcht niet (auto)

Met een waarde die niet matcht — `soortEvenement` is iets anders dan `Anders` — moet veld `omschrijfHetSoortEvenement` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Omschrijf het soort evenement" _(op Stap 2: Het evenement)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `soortEvenement` = `Markt of braderie` — moet veld `gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Markt of braderie"

**Dan verwachten we:**
- Veld "Gaat het hier om een periodiek terugkerende markt (jaarmarkt of weekmarkt), waarvoor de gemeente een besluit heeft genomen met betrekking tot de marktdagen?" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `soortEvenement` is iets anders dan `Markt of braderie` — moet veld `gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Gaat het hier om een periodiek terugkerende markt (jaarmarkt of weekmarkt), waarvoor de gemeente een besluit heeft genomen met betrekking tot de marktdagen?" _(op Stap 2: Het evenement)_ is **niet zichtbaar** in de rendered pagina

### ✅ Evenementnaam ingevuld → omschrijving + soort-veld verschijnen

Zolang "Wat is de naam van het evenement?" leeg is, hoeven de vervolgvelden niet in beeld. Zodra de gebruiker een naam heeft ingevuld, komen "Geef een korte omschrijving" en "Wat voor soort evenement is het?" tevoorschijn.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 2 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/2 checks daadwerkelijk gemeten via rendered HTML; 2 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "Zomerfestival 2026"

**Dan verwachten we:**
- Veld "Geef een korte omschrijving van het evenement {{ watIsDeNaamVanHetEvenementVergunning }}" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Soort evenement "Anders" → omschrijf-veld verschijnt

Als de gebruiker bij "Wat voor soort evenement?" kiest voor "Anders", komt een extra tekstveld "Omschrijf het soort evenement" tevoorschijn waar een eigen omschrijving gevraagd wordt.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "Wandeltocht"
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Anders"

**Dan verwachten we:**
- Veld "Omschrijf het soort evenement" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Soort evenement "Markt of braderie" → periodiciteit-vraag verschijnt

Bij een markt of braderie moet de organisator aangeven of het gaat om een periodiek terugkerende markt (jaar/week-markt) waarvoor de gemeente al een regulier besluit heeft.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "Weekmarkt Maastricht"
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Markt of braderie"

**Dan verwachten we:**
- Veld "Gaat het hier om een periodiek terugkerende markt (jaarmarkt of weekmarkt), waarvoor de gemeente een besluit heeft genomen met betrekking tot de marktdagen?" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

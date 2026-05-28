# Stap 5: Vooraankondiging

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 2/2 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

### ✅ Zichtbaarheid "vooraankondiginggroep" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `waarvoorWiltUEventloketGebruiken` = `vooraankondiging` — moet veld `vooraankondiginggroep` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Veld "Vooraankondiging" _(op Stap 5: Vooraankondiging)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "vooraankondiginggroep" — trigger matcht niet (auto)

Met een waarde die niet matcht — `waarvoorWiltUEventloketGebruiken` is iets anders dan `vooraankondiging` — moet veld `vooraankondiginggroep` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Vooraankondiging" _(op Stap 5: Vooraankondiging)_ is **niet zichtbaar** in de rendered pagina

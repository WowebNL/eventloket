# Stap 4: Tijden

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 9/9 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Waarschuwing voor gelijktijdig geplande evenementen

Als de gemeente heeft gemeld dat er andere evenementen op dezelfde datum staan, toont het formulier een waarschuwings-blok op de Tijden-pagina. Zo kan de organisator zien of 8n planning-wijziging overwogen moet worden.

### ✅ Zichtbaarheid "OpbouwStart" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten` = `Ja` — moet veld `OpbouwStart` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er voorafgaand aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?" = "Ja"

**Dan verwachten we:**
- Veld "Wat is de start datum en tijd van de opbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "OpbouwStart" — trigger matcht niet (auto)

Met een waarde die niet matcht — `zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten` is iets anders dan `Ja` — moet veld `OpbouwStart` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er voorafgaand aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Wat is de start datum en tijd van de opbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "OpbouwEind" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten` = `Ja` — moet veld `OpbouwEind` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er voorafgaand aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?" = "Ja"

**Dan verwachten we:**
- Veld "Wat is de eind datum en tijd van de opbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "OpbouwEind" — trigger matcht niet (auto)

Met een waarde die niet matcht — `zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten` is iets anders dan `Ja` — moet veld `OpbouwEind` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er voorafgaand aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Wat is de eind datum en tijd van de opbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "AfbouwStart" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `zijnErAansluitendAanHetEvenementAfbouwactiviteiten` = `Ja` — moet veld `AfbouwStart` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er aansluitend aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?" = "Ja"

**Dan verwachten we:**
- Veld "Wat is de start datum en tijdstip van de afbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "AfbouwStart" — trigger matcht niet (auto)

Met een waarde die niet matcht — `zijnErAansluitendAanHetEvenementAfbouwactiviteiten` is iets anders dan `Ja` — moet veld `AfbouwStart` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er aansluitend aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Wat is de start datum en tijdstip van de afbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "AfbouwEind" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `zijnErAansluitendAanHetEvenementAfbouwactiviteiten` = `Ja` — moet veld `AfbouwEind` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er aansluitend aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?" = "Ja"

**Dan verwachten we:**
- Veld "Wat is de eind datum en tijdstip van de afbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "AfbouwEind" — trigger matcht niet (auto)

Met een waarde die niet matcht — `zijnErAansluitendAanHetEvenementAfbouwactiviteiten` is iets anders dan `Ja` — moet veld `AfbouwEind` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Zijn er aansluitend aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Wat is de eind datum en tijdstip van de afbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 4: Tijden)_ is **niet zichtbaar** in de rendered pagina

### ✅ Waarschuwing over gelijktijdige evenementen verschijnt als er andere evenementen bekend zijn

Zodra evenementenInDeGemeente een (niet-lege) waarde heeft — dat wil zeggen: de service EventsCheckService heeft evenementen teruggekregen voor de gekozen datum — toont de Tijden-pagina een inhoud-blok dat de organisator waarschuwt dat er al andere evenementen gepland staan.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementenInDeGemeente` = "Koningsdag-markt, Kermis Centrum"

**Dan verwachten we:**
- Veld "Content" _(op Stap 4: Tijden)_ wordt **zichtbaar**

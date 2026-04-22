# Stap 2: Het evenement

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 3/3 gedekt.

## Conditionele zichtbaarheid op stap "Het evenement"

Op deze stap bepalen een paar velden of vervolgvragen te zien zijn. Zodra de evenementnaam is ingevuld, verschijnen omschrijving- en soort-velden. Bij "Anders" als soort komt er een extra tekstveld vrij. Bij "Markt of braderie" komt er een periodieke-markt-vraag vrij.

### ✅ Evenementnaam ingevuld → omschrijving + soort-veld verschijnen

Zolang "Wat is de naam van het evenement?" leeg is, hoeven de vervolgvelden niet in beeld. Zodra de gebruiker een naam heeft ingevuld, komen "Geef een korte omschrijving" en "Wat voor soort evenement is het?" tevoorschijn.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "Zomerfestival 2026"

**Dan verwachten we:**
- Veld "Geef een korte omschrijving van het evenement {{ watIsDeNaamVanHetEvenementVergunning }}" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Soort evenement "Anders" → omschrijf-veld verschijnt

Als de gebruiker bij "Wat voor soort evenement?" kiest voor "Anders", komt een extra tekstveld "Omschrijf het soort evenement" tevoorschijn waar een eigen omschrijving gevraagd wordt.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "Wandeltocht"
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Anders"

**Dan verwachten we:**
- Veld "Omschrijf het soort evenement" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

### ✅ Soort evenement "Markt of braderie" → periodiciteit-vraag verschijnt

Bij een markt of braderie moet de organisator aangeven of het gaat om een periodiek terugkerende markt (jaar/week-markt) waarvoor de gemeente al een regulier besluit heeft.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de naam van het evenement?" = "Weekmarkt Maastricht"
- Veld "Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Markt of braderie"

**Dan verwachten we:**
- Veld "Gaat het hier om een periodiek terugkerende markt (jaarmarkt of weekmarkt), waarvoor de gemeente een besluit heeft genomen met betrekking tot de marktdagen?" _(op Stap 2: Het evenement)_ is **zichtbaar** in de rendered pagina

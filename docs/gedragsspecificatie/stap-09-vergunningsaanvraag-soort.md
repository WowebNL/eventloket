# Stap 9: Vergunningsaanvraag: soort

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 14/14 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Vergunningsaanvraag-details alleen bij de vergunningsroute

Deze pagina vraagt naar soort-specifieke details over de vergunningaanvraag. Als de aanvraag een vooraankondiging of een melding is (geen gebiedsafsluiting), is deze pagina niet nodig en wordt 8n doorgestreept in de sidebar.

### ✅ Zichtbaarheid "welkeOverigeBouwwerkenGaatUPlaatsen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeVoorzieningenZijnAanwezigBijUwEvenement` = `A22` — moet veld `welkeOverigeBouwwerkenGaatUPlaatsen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Welke overige bouwwerken gaat u plaatsen?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeOverigeBouwwerkenGaatUPlaatsen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeVoorzieningenZijnAanwezigBijUwEvenement` is iets anders dan `A22` — moet veld `welkeOverigeBouwwerkenGaatUPlaatsen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Welke overige bouwwerken gaat u plaatsen?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAnderVoorwerpenGaatUPlaatsenBijEvenementX" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeVoorwerpenGaatUPlaatsenBijUwEvenementX` = `A30` — moet veld `welkeAnderVoorwerpenGaatUPlaatsenBijEvenementX` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorwerpen gaat u plaatsen bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "welke ander voorwerpen gaat u plaatsen bij evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAnderVoorwerpenGaatUPlaatsenBijEvenementX" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeVoorwerpenGaatUPlaatsenBijUwEvenementX` is iets anders dan `A30` — moet veld `welkeAnderVoorwerpenGaatUPlaatsenBijEvenementX` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorwerpen gaat u plaatsen bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "welke ander voorwerpen gaat u plaatsen bij evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkActiviteitBetreftUwEvenementX" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX` = `A46` — moet veld `welkActiviteitBetreftUwEvenementX` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke van de onderstaande activiteiten vinden verder nog plaats tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Welk activiteit betreft uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkActiviteitBetreftUwEvenementX" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX` is iets anders dan `A46` — moet veld `welkActiviteitBetreftUwEvenementX` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke van de onderstaande activiteiten vinden verder nog plaats tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Welk activiteit betreft uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "isUwEvenementToegankelijkVoorMensenMetEenBeperking" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeVoorzieningenZijnAanwezigBijUwEvenement` = `A16` — moet veld `isUwEvenementToegankelijkVoorMensenMetEenBeperking` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} toegankelijk voor mensen met een beperking?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "isUwEvenementToegankelijkVoorMensenMetEenBeperking" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeVoorzieningenZijnAanwezigBijUwEvenement` is iets anders dan `A16` — moet veld `isUwEvenementToegankelijkVoorMensenMetEenBeperking` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} toegankelijk voor mensen met een beperking?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "voorHoeveelMensenMetEenLichamelijkeOfGeestelijkeBeperkingVerzorgtUOpvangTijdensUwEvenementX" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `welkeVoorzieningenZijnAanwezigBijUwEvenement` = `A16` — moet veld `voorHoeveelMensenMetEenLichamelijkeOfGeestelijkeBeperkingVerzorgtUOpvangTijdensUwEvenementX` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Voor hoeveel mensen met een lichamelijke of geestelijke beperking verzorgt u opvang tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "voorHoeveelMensenMetEenLichamelijkeOfGeestelijkeBeperkingVerzorgtUOpvangTijdensUwEvenementX" — trigger matcht niet (auto)

Met een waarde die niet matcht — `welkeVoorzieningenZijnAanwezigBijUwEvenement` is iets anders dan `A16` — moet veld `voorHoeveelMensenMetEenLichamelijkeOfGeestelijkeBeperkingVerzorgtUOpvangTijdensUwEvenementX` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Voor hoeveel mensen met een lichamelijke of geestelijke beperking verzorgt u opvang tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeMaatregelenHeeftUGenomenOmMensenMetEenBeperkingOngehinderdDeelTeLatenNemenAanUwEvenement" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `isUwEvenementToegankelijkVoorMensenMetEenBeperking` = `Ja` — moet veld `welkeMaatregelenHeeftUGenomenOmMensenMetEenBeperkingOngehinderdDeelTeLatenNemenAanUwEvenement` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} toegankelijk voor mensen met een beperking?" = "Ja"

**Dan verwachten we:**
- Veld "Welke maatregelen heeft u genomen om mensen met een beperking ongehinderd deel te laten nemen aan uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeMaatregelenHeeftUGenomenOmMensenMetEenBeperkingOngehinderdDeelTeLatenNemenAanUwEvenement" — trigger matcht niet (auto)

Met een waarde die niet matcht — `isUwEvenementToegankelijkVoorMensenMetEenBeperking` is iets anders dan `Ja` — moet veld `welkeMaatregelenHeeftUGenomenOmMensenMetEenBeperkingOngehinderdDeelTeLatenNemenAanUwEvenement` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} toegankelijk voor mensen met een beperking?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Welke maatregelen heeft u genomen om mensen met een beperking ongehinderd deel te laten nemen aan uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 9: Vergunningsaanvraag: soort)_ is **niet zichtbaar** in de rendered pagina

### ✅ Vooraankondiging → "Vergunningsaanvraag: soort" wordt doorgestreept

Een vooraankondiging vraagt minder details dan een volledige vergunning. Zodra de organisator "vooraankondiging" kiest, hoeft de soort-stap niet ingevuld te worden en wordt 8n als niet-van-toepassing gemarkeerd.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Stap 9: Vergunningsaanvraag: soort wordt **niet van toepassing** (doorgestreept in sidebar)

### ✅ Geen wegafsluiting (Nee) → "Vergunningsaanvraag: soort" wordt doorgestreept

Als het evenement geen wegen of gebiedsontsluiting afsluit, valt het in het melding-regime. De soort-stap is dan niet relevant en wordt in de sidebar doorgestreept.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Stap 9: Vergunningsaanvraag: soort wordt **niet van toepassing** (doorgestreept in sidebar)

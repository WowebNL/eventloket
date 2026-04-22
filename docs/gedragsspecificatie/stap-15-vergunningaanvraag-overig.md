# Stap 15: Vergunningaanvraag: overig

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 2/2 gedekt.

## Detail-velden per aangevinkte maatregel

Per aangevinkte overige maatregel (bijvoorbeeld "extra afval" of "aanpassen straatmeubilair") verschijnt een detail-veld waarin de organisator kan aangeven hoe dat wordt georganiseerd. Ook markeert het systeem de maatregelen-pagina als van toepassing zodat ze in de wizard-sidebar actief is.

### ✅ Extra afval aangevinkt → detail-veld + maatregelen-stap actief

Als de organisator bij "kruis aan welke overige maatregelen" optie A33 (extra afvalvoorzieningen) aanvinkt, verschijnt het detail-veld waarin de aanpak beschreven kan worden, en wordt de maatregelen-pagina in de sidebar actief.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan welke overige maatregelen/gevolgen van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Er ontstaat extra afval" aangevinkt

**Dan verwachten we:**
- Veld "Extra afval" _(op Stap 13: Vergunningaanvraag: maatregelen)_ wordt **zichtbaar**
- Stap 15: Vergunningaanvraag: overig wordt **van toepassing** (getoond in sidebar)

### ✅ Straatmeubilair aangevinkt → detail-veld + maatregelen-stap actief

Als de organisator kiest om straatmeubilair aan te passen of te verwijderen (optie A32), verschijnt het detail-veld waarin kan worden beschreven welke objecten verplaatst worden.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan welke overige maatregelen/gevolgen van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "(Laten) aanpassen locatie en/of verwijderen straatmeubilair" aangevinkt

**Dan verwachten we:**
- Veld "Aanpassen locatie en/of verwijderen straatmeubilair" _(op Stap 13: Vergunningaanvraag: maatregelen)_ wordt **zichtbaar**
- Stap 15: Vergunningaanvraag: overig wordt **van toepassing** (getoond in sidebar)

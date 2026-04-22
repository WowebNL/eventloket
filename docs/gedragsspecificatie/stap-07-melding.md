# Stap 7: Melding

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 2/2 gedekt.

## Melding-stap verdwijnt bij vooraankondiging of vergunningsroute

De Melding-stap in de sidebar moet wegvallen zodra duidelijk wordt dat het geen melding-procedure is: óf de organisator heeft gekozen voor vooraankondiging, óf de vragenboom concludeert dat het een volledige vergunningsaanvraag wordt.

### ✅ Vooraankondiging → Melding-stap wordt doorgestreept

Zodra de organisator bij "waarvoor wilt u Eventloket gebruiken?" kiest voor "vooraankondiging", is de Melding-stap niet relevant. Het systeem markeert de stap als niet-van-toepassing; in de sidebar verschijnt hij doorgestreept.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Stap 7: Melding wordt **niet van toepassing** (doorgestreept in sidebar)

### ✅ Groot evenement (> drempel aanwezigen) → Melding-stap wordt doorgestreept

Als de organisator al op stap 6 aangeeft dat het aantal aanwezigen boven de drempel ligt, start de vergunningsroute. De Melding-stap is dan niet van toepassing en wordt in de sidebar doorgestreept.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen 'aanwezigen' %} personen?" = "Nee"

**Dan verwachten we:**
- Stap 7: Melding wordt **niet van toepassing** (doorgestreept in sidebar)

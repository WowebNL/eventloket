# Stap 1: Contactgegevens

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 1/1 gedekt.

## Conditionele zichtbaarheid van velden en stappen

Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ KvK-gebruiker — adresgegevens verborgen

Gebruiker ingelogd via eHerkenning/KvK heeft de organisatie-gegevens al uit de KvK-koppeling. "Organisatie-informatie" wordt zichtbaar om de opgehaalde gegevens te tonen; "Adresgegevens" wordt verborgen omdat het adres al bekend is.

**PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `eventloketSession.kvk` = "12345678"

**Dan verwachten we:**
- Veld "Organisatie informatie" _(op Stap 1: Contactgegevens)_ wordt **zichtbaar**
- Veld "Adresgegevens" _(op Stap 1: Contactgegevens)_ wordt **verborgen**

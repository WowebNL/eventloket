# Stap 4: Tijden

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 1/1 gedekt.

## Waarschuwing voor gelijktijdig geplande evenementen

Als de gemeente heeft gemeld dat er andere evenementen op dezelfde datum staan, toont het formulier een waarschuwings-blok op de Tijden-pagina. Zo kan de organisator zien of 8n planning-wijziging overwogen moet worden.

### ✅ Waarschuwing over gelijktijdige evenementen verschijnt als er andere evenementen bekend zijn

Zodra evenementenInDeGemeente een (niet-lege) waarde heeft — dat wil zeggen: de service EventsCheckService heeft evenementen teruggekregen voor de gekozen datum — toont de Tijden-pagina een inhoud-blok dat de organisator waarschuwt dat er al andere evenementen gepland staan.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementenInDeGemeente` = "Koningsdag-markt, Kermis Centrum"

**Dan verwachten we:**
- Veld "Content" _(op Stap 4: Tijden)_ wordt **zichtbaar**

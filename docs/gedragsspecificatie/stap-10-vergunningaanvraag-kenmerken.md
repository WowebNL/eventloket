# Stap 10: Vergunningaanvraag: kenmerken

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 1/1 gedekt.

## Conditionele zichtbaarheid van velden en stappen

Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ Bouwsels >10 m² — velden en stap zichtbaar na aanvinken

Als de organisator bij "wat is van toepassing voor uw evenement" de optie A3 (bouwsels groter dan 10 m²) aanvinkt, moeten de vervolg-velden zichtbaar worden en wordt de stap "Vergunningsaanvraag: extra activiteiten" in de sidebar actief.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan wat van toepassing is voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Bouwsels plaatsen groter dan 10m2, zoals tenten of podia" aangevinkt

**Dan verwachten we:**
- Veld "Bouwsels > 10m<sup>2</sup> " _(op Stap 10: Vergunningaanvraag: kenmerken)_ wordt **zichtbaar**
- Veld "Wat voor bouwsels plaats u op de locaties?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ wordt **zichtbaar**
- Stap 10: Vergunningaanvraag: kenmerken wordt **van toepassing** (getoond in sidebar)

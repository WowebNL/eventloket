# Stap 16: Bijlagen

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 3/3 gedekt.

## Bijlage-upload-velden op basis van risico en kenmerken

Welke bijlagen verplicht zijn hangt af van de risico-classificatie en de kenmerken van het evenement. Deze scenarios tonen dat het veiligheidsplan-veld verschijnt bij B- of C-classificatie, en dat het bebordingsplan-veld verschijnt als kenmerk A50 is aangevinkt.

### ✅ Classificatie B → upload-veld veiligheidsplan verschijnt

Bij een middelhoog risico (classificatie B) is de organisator verplicht een veiligheidsplan te uploaden. Het veiligheidsplan-veld wordt zichtbaar, samen met de bijbehorende uitleg-teksten.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `risicoClassificatie` = "B"

**Dan verwachten we:**
- Veld "Veiligheidsplan" _(op Stap 16: Bijlagen)_ wordt **zichtbaar**
- Veld "Content" _(op Stap 16: Bijlagen)_ wordt **zichtbaar**

### ✅ Classificatie C → upload-veld veiligheidsplan verschijnt

Hoog-risico evenementen (classificatie C) vragen om hetzelfde veiligheidsplan als B. Het upload-veld en de uitleg-tekst worden zichtbaar.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `risicoClassificatie` = "C"

**Dan verwachten we:**
- Veld "Veiligheidsplan" _(op Stap 16: Bijlagen)_ wordt **zichtbaar**
- Veld "Content" _(op Stap 16: Bijlagen)_ wordt **zichtbaar**

### ✅ Verkeersmaatregelen (A50) → upload-veld bebordingsplan verschijnt

Als de organisator aangeeft verkeersmaatregelen te treffen (kenmerk A50), moet er een bebordings- en bewegwijzeringsplan bijgevoegd worden. Het upload-veld daarvoor wordt zichtbaar.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan wat voor overige kenmerken van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}" — "Bewegwijzering aanbrengen" aangevinkt

**Dan verwachten we:**
- Veld "U heeft aangegeven, dat u gebruik gaat maken van bewegwijzering. Hiervoor dient u een bebordings- en bewegwijzeringsplan toe te voegen, als onderdeel van het verkeersplan, dat als bijlage toegevoegd wordt." _(op Stap 16: Bijlagen)_ wordt **zichtbaar**

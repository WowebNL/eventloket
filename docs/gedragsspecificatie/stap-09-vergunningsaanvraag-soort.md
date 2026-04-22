# Stap 9: Vergunningsaanvraag: soort

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 2/2 gedekt.

## Vergunningsaanvraag-details alleen bij de vergunningsroute

Deze pagina vraagt naar soort-specifieke details over de vergunningaanvraag. Als de aanvraag een vooraankondiging of een melding is (geen gebiedsafsluiting), is deze pagina niet nodig en wordt 8n doorgestreept in de sidebar.

### ✅ Vooraankondiging → "Vergunningsaanvraag: soort" wordt doorgestreept

Een vooraankondiging vraagt minder details dan een volledige vergunning. Zodra de organisator "vooraankondiging" kiest, hoeft de soort-stap niet ingevuld te worden en wordt 8n als niet-van-toepassing gemarkeerd.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Stap 9: Vergunningsaanvraag: soort wordt **niet van toepassing** (doorgestreept in sidebar)

### ✅ Geen wegafsluiting (Nee) → "Vergunningsaanvraag: soort" wordt doorgestreept

Als het evenement geen wegen of gebiedsontsluiting afsluit, valt het in het melding-regime. De soort-stap is dan niet relevant en wordt in de sidebar doorgestreept.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Stap 9: Vergunningsaanvraag: soort wordt **niet van toepassing** (doorgestreept in sidebar)

# Stap 6: Vergunningsplichtig scan

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 2/2 gedekt.

## Vragenboom om vergunning-plicht vs melding te bepalen

Op basis van vijf voortschrijdende Ja/Nee-vragen stelt het systeem vast of het evenement lichtvoetig gemeld kan worden of dat er een volledige vergunningaanvraag nodig is. Zodra één vraag met "Nee" beantwoord wordt, ligt de route vast op vergunning en stopt de vragenboom — de organisator krijgt dan geen verdere meldingsvragen te zien.

### ✅ Eerste meldingsvraag komt vrij als objecten klein én gemeente heeft report_question_1

Als de organisator aangeeft dat geplaatste objecten kleiner zijn dan de gemeente-grens, én de gemeente heeft de eerste aanvullende vraag geconfigureerd (`gemeenteVariabelen.report_question_1`), verschijnt meldingvraag1 als vervolgvraag in het formulier.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? " = "Ja"
- Veld `gemeenteVariabelen` — `report_question_1` uit

**Dan verwachten we:**
- Veld "{{ gemeenteVariabelen.report_question_1 }}" _(op Stap 6: Vergunningsplichtig scan)_ wordt **zichtbaar**

### ✅ Bij "aantal aanwezigen niet kleiner dan drempel" blijft vergunningsplicht

Zodra de organisator al bij de eerste vraag aangeeft dat het aantal aanwezigen NIET onder de gemeentelijke drempel ligt, stopt de melding-route direct. Het systeem markeert de aanvraag als vergunningsaanvraag en verbergt de content-block die naar melding zou leiden — de organisator wordt doorgestuurd naar de volledige vergunningsprocedure.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen 'aanwezigen' %} personen?" = "Nee"

**Dan verwachten we:**
- Veld `isVergunningaanvraag` = **ja**
- Veld "Content" _(op Stap 6: Vergunningsplichtig scan)_ wordt **verborgen**

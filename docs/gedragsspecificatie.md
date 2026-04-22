# Gedragsspecificatie evenementformulier

_Automatisch gegenereerd op 22-04-2026 14:56 via `php artisan eventform:gedrags-rapport`._

**Samenvatting:** ✅ Alle scenarios slagen — 174 geslaagd, 0 gefaald, 174 totaal.

✅ Ook **174 van 174 scenarios bevestigd door de onafhankelijke JsonLogic-spec** (via json-logic-js, de canonieke referentie die Open Forms zelf ook volgt).

**Bewijssterkte:** 🟢 69 scenarios met sterk bewijs (PHP-runtime meet én spec-referentie bevestigt) · 🟡 105 scenarios met gemiddeld bewijs (spec-referentie bevestigt, PHP-runner kan visuele check niet direct meten — typisch velden op niet-actieve wizard-stap)

Dit document is de index op de gedragsspecificatie. Elke pagina van het evenementformulier heeft een eigen bestand waarin de scenarios voor dat gedeelte beschreven staan.

Elk scenario wordt onafhankelijk gecheckt:

- **PHP (Filament)** — onze getranspileerde RulesEngine draait de rule-logica op een FormState met de gegeven input.
- **JS-spec (json-logic-js)** — de OF-rules gaan door een onafhankelijke implementatie van de JsonLogic-spec heen. Deze library wordt standaard gebruikt door web-tools die OF-rules evalueren. Als beide paden dezelfde uitkomst geven, is het gedrag byte-equivalent aan wat de spec voorschrijft.

✅ betekent: geslaagd in de betreffende check. ❌ betekent: er is een afwijking die onderzocht moet worden.

---

## Overzicht per pagina

- ✅ **[Stap 1: Contactgegevens](gedragsspecificatie/stap-01-contactgegevens.md)** — 7/7 scenarios
- ✅ **[Stap 2: Het evenement](gedragsspecificatie/stap-02-het-evenement.md)** — 11/11 scenarios
- _⚪ Stap 3: Locatie_ — nog geen scenarios gedekt
- ✅ **[Stap 4: Tijden](gedragsspecificatie/stap-04-tijden.md)** — 9/9 scenarios
- ✅ **[Stap 5: Vooraankondiging](gedragsspecificatie/stap-05-vooraankondiging.md)** — 2/2 scenarios
- ✅ **[Stap 6: Vergunningsplichtig scan](gedragsspecificatie/stap-06-vergunningsplichtig-scan.md)** — 14/14 scenarios
- ✅ **[Stap 7: Melding](gedragsspecificatie/stap-07-melding.md)** — 2/2 scenarios
- ✅ **[Stap 8: Risicoscan](gedragsspecificatie/stap-08-risicoscan.md)** — 3/3 scenarios
- ✅ **[Stap 9: Vergunningsaanvraag: soort](gedragsspecificatie/stap-09-vergunningsaanvraag-soort.md)** — 14/14 scenarios
- ✅ **[Stap 10: Vergunningaanvraag: kenmerken](gedragsspecificatie/stap-10-vergunningaanvraag-kenmerken.md)** — 21/21 scenarios
- ✅ **[Stap 11: Vergunningsaanvraag: voorzieningen](gedragsspecificatie/stap-11-vergunningsaanvraag-voorzieningen.md)** — 4/4 scenarios
- ✅ **[Stap 12: Vergunningsaanvraag: voorwerpen](gedragsspecificatie/stap-12-vergunningsaanvraag-voorwerpen.md)** — 1/1 scenario
- ✅ **[Stap 13: Vergunningaanvraag: maatregelen](gedragsspecificatie/stap-13-vergunningaanvraag-maatregelen.md)** — 8/8 scenarios
- ✅ **[Stap 14: Vergunningsaanvraag: extra activiteiten](gedragsspecificatie/stap-14-vergunningsaanvraag-extra-activiteiten.md)** — 2/2 scenarios
- ✅ **[Stap 15: Vergunningaanvraag: overig](gedragsspecificatie/stap-15-vergunningaanvraag-overig.md)** — 28/28 scenarios
- ✅ **[Stap 16: Bijlagen](gedragsspecificatie/stap-16-bijlagen.md)** — 3/3 scenarios
- 🟢 _Stap 17: Type aanvraag_ — geen dynamisch gedrag (pure input-/inhoudspagina, niks te testen)

## Pagina-overstijgend gedrag

- ✅ **[Pagina-overstijgend gedrag](gedragsspecificatie/pagina-overstijgend.md)** — 45/45 scenarios

---

Nieuwe scenarios toevoegen kan door een class toe te voegen in `tests/Feature/EventForm/Equivalence/Scenarios/` die `ScenarioProvider` implementeert. Bij de volgende run van `eventform:gedrags-rapport` verschijnt hij automatisch in het juiste paginabestand.
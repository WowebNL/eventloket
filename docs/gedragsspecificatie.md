# Gedragsspecificatie evenementformulier

_Automatisch gegenereerd op 22-04-2026 11:12 via `php artisan eventform:gedrags-rapport`._

**Samenvatting:** ✅ Alle scenarios slagen — 69 geslaagd, 0 gefaald, 69 totaal.

Dit document is de index op de gedragsspecificatie. Elke pagina van het evenementformulier heeft een eigen bestand waarin de scenarios voor dat gedeelte beschreven staan. ✅ betekent: de Filament-versie reageert exact zoals Open Forms zou doen. ❌ betekent: er is een afwijking die onderzocht moet worden.

---

## Overzicht per pagina

- ✅ **[Stap 1: Contactgegevens](gedragsspecificatie/stap-01-contactgegevens.md)** — 1/1 scenario
- 🟢 _Stap 2: Het evenement_ — geen dynamisch gedrag (pure input-/inhoudspagina, niks te testen)
- _⚪ Stap 3: Locatie_ — nog geen scenarios gedekt
- ✅ **[Stap 4: Tijden](gedragsspecificatie/stap-04-tijden.md)** — 1/1 scenario
- 🟢 _Stap 5: Vooraankondiging_ — geen dynamisch gedrag (pure input-/inhoudspagina, niks te testen)
- ✅ **[Stap 6: Vergunningsplichtig scan](gedragsspecificatie/stap-06-vergunningsplichtig-scan.md)** — 2/2 scenarios
- ✅ **[Stap 7: Melding](gedragsspecificatie/stap-07-melding.md)** — 2/2 scenarios
- ✅ **[Stap 8: Risicoscan](gedragsspecificatie/stap-08-risicoscan.md)** — 3/3 scenarios
- ✅ **[Stap 9: Vergunningsaanvraag: soort](gedragsspecificatie/stap-09-vergunningsaanvraag-soort.md)** — 2/2 scenarios
- ✅ **[Stap 10: Vergunningaanvraag: kenmerken](gedragsspecificatie/stap-10-vergunningaanvraag-kenmerken.md)** — 1/1 scenario
- ✅ **[Stap 11: Vergunningsaanvraag: voorzieningen](gedragsspecificatie/stap-11-vergunningsaanvraag-voorzieningen.md)** — 2/2 scenarios
- ✅ **[Stap 12: Vergunningsaanvraag: voorwerpen](gedragsspecificatie/stap-12-vergunningsaanvraag-voorwerpen.md)** — 1/1 scenario
- ✅ **[Stap 13: Vergunningaanvraag: maatregelen](gedragsspecificatie/stap-13-vergunningaanvraag-maatregelen.md)** — 2/2 scenarios
- ✅ **[Stap 14: Vergunningsaanvraag: extra activiteiten](gedragsspecificatie/stap-14-vergunningsaanvraag-extra-activiteiten.md)** — 2/2 scenarios
- ✅ **[Stap 15: Vergunningaanvraag: overig](gedragsspecificatie/stap-15-vergunningaanvraag-overig.md)** — 2/2 scenarios
- ✅ **[Stap 16: Bijlagen](gedragsspecificatie/stap-16-bijlagen.md)** — 3/3 scenarios
- 🟢 _Stap 17: Type aanvraag_ — geen dynamisch gedrag (pure input-/inhoudspagina, niks te testen)

## Pagina-overstijgend gedrag

- ✅ **[Pagina-overstijgend gedrag](gedragsspecificatie/pagina-overstijgend.md)** — 45/45 scenarios

---

Nieuwe scenarios toevoegen kan door een class toe te voegen in `tests/Feature/EventForm/Equivalence/Scenarios/` die `ScenarioProvider` implementeert. Bij de volgende run van `eventform:gedrags-rapport` verschijnt hij automatisch in het juiste paginabestand.
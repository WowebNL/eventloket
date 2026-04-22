# Gedragsspecificatie evenementformulier

_Automatisch gegenereerd op 22-04-2026 10:47 via `php artisan eventform:gedrags-rapport`._

**Samenvatting:** ✅ Alle scenarios slagen — 48 geslaagd, 0 gefaald, 48 totaal.

Dit document is de index op de gedragsspecificatie. Elke pagina van het evenementformulier heeft een eigen bestand waarin de scenarios voor dat gedeelte beschreven staan. ✅ betekent: de Filament-versie reageert exact zoals Open Forms zou doen. ❌ betekent: er is een afwijking die onderzocht moet worden.

---

## Overzicht per pagina

- ✅ **[Stap 1: Contactgegevens](gedragsspecificatie/stap-01-contactgegevens.md)** — 1/1 scenario
- _⚪ Stap 2: Het evenement_ — nog geen scenarios gedekt
- _⚪ Stap 3: Locatie_ — nog geen scenarios gedekt
- _⚪ Stap 4: Tijden_ — nog geen scenarios gedekt
- _⚪ Stap 5: Vooraankondiging_ — nog geen scenarios gedekt
- _⚪ Stap 6: Vergunningsplichtig scan_ — nog geen scenarios gedekt
- _⚪ Stap 7: Melding_ — nog geen scenarios gedekt
- _⚪ Stap 8: Risicoscan_ — nog geen scenarios gedekt
- _⚪ Stap 9: Vergunningsaanvraag: soort_ — nog geen scenarios gedekt
- ✅ **[Stap 10: Vergunningaanvraag: kenmerken](gedragsspecificatie/stap-10-vergunningaanvraag-kenmerken.md)** — 1/1 scenario
- _⚪ Stap 11: Vergunningsaanvraag: voorzieningen_ — nog geen scenarios gedekt
- ✅ **[Stap 12: Vergunningsaanvraag: voorwerpen](gedragsspecificatie/stap-12-vergunningsaanvraag-voorwerpen.md)** — 1/1 scenario
- _⚪ Stap 13: Vergunningaanvraag: maatregelen_ — nog geen scenarios gedekt
- _⚪ Stap 14: Vergunningsaanvraag: extra activiteiten_ — nog geen scenarios gedekt
- _⚪ Stap 15: Vergunningaanvraag: overig_ — nog geen scenarios gedekt
- _⚪ Stap 16: Bijlagen_ — nog geen scenarios gedekt
- _⚪ Stap 17: Type aanvraag_ — nog geen scenarios gedekt

## Pagina-overstijgend gedrag

- ✅ **[Pagina-overstijgend gedrag](gedragsspecificatie/pagina-overstijgend.md)** — 45/45 scenarios

---

Nieuwe scenarios toevoegen kan door een class toe te voegen in `tests/Feature/EventForm/Equivalence/Scenarios/` die `ScenarioProvider` implementeert. Bij de volgende run van `eventform:gedrags-rapport` verschijnt hij automatisch in het juiste paginabestand.
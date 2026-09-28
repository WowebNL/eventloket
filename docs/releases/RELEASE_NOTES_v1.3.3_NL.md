# Eventloket versie 1.3.3: wat is er nieuw?

**Releasedatum:** 28 september 2026

---

Deze versie herstelt de lijstweergave van de kalender. Bij het openen van die lijst, of bij het kiezen van een datum in het filter, kreeg je in sommige gevallen een foutmelding in plaats van de lijst met evenementen. Dat is opgelost. Er zijn geen nieuwe functies en er verandert niets aan de manier van werken.

---

## 🐛 Opgeloste problemen

### De lijstweergave van de kalender gaf een foutmelding

**Voor wie:** Organisatoren, gemeentemedewerkers, behandelaars en adviseurs

De kalender heeft naast de maandweergave ook een lijstweergave, met bovenaan een filter "Van" en "Tot" om de periode te kiezen. Opende je die lijst, of koos je daarin een datum, dan verscheen er in sommige gevallen een foutmelding op de plek van de lijst. De maandweergave bleef gewoon werken.

Dit kon in twee situaties gebeuren:

* **Bij het kiezen van een datum.** Een datum met een dag hoger dan twaalf, zoals 20-09-2026, werd niet goed gelezen en gaf de foutmelding.
* **Bij één afwijkende zaak.** Stond er bij een zaak een startdatum in een andere schrijfwijze dan gebruikelijk, dan kon de lijst niet worden opgebouwd. Omdat het filter "Van" in de lijstweergave standaard op vandaag staat, gold dat voor iedereen die die zaak in zijn overzicht heeft, ook zonder zelf een datum te kiezen.

De lijstweergave filtert nu op dezelfde manier als de maandweergave, die hier nooit last van had. Beide situaties leiden daardoor niet meer tot een foutmelding, en de gekozen periode wordt goed toegepast.

---

## 📱 Wat moet je doen?

### Voor gemeenten, behandelaars, adviseurs en organisatoren

**Niets.** De lijstweergave werkt weer zodra de update live staat.

Er zijn geen gegevens verloren gegaan en er is niets fout opgeslagen. Het ging alleen om het tonen van de lijst. Zie je toch nog een foutmelding in de lijstweergave, ververs dan eerst de pagina. Blijft het staan, laat het dan weten.

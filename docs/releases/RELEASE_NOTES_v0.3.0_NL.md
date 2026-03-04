# Versie 0.3.0 — Wat is er nieuw?

**Releasedatum:** 25 februari 2026

---

## ✨ Nieuwe functies

### Geïmporteerde zaken kunnen nu verwijderd worden

**Voor wie:** Beheerders (Admin)

Geïmporteerde zaken (zaken die vanuit een extern systeem zijn geïmporteerd en nog geen ZGW-zaak hebben) kunnen nu worden verwijderd. Dit is alleen beschikbaar voor beheerders en kan worden gedaan via de zaakdetailpagina.

---

### Emails als bijlage aan een zaak en betere controle op bestandsuploads

**Voor wie:** Alle gebruikers

De bestandsupload is veiliger en robuuster gemaakt. Er is betere validatie en foutafhandeling voor uploads, inclusief ondersteuning voor het uploaden van e-mailbestanden.

---

### Platform-admins kunnen nu 2FA van gebruikers resetten en alle gebruikers beheren

**Voor wie:** Platform-beheerders (Admin)

Platform-admins hebben nu toegang tot een gebruikersbeheermodule waar ze:
- Een overzicht zien van alle gebruikers in het systeem
- De 2FA (tweefactorauthenticatie) van elke gebruiker kunnen resetten
- De bewerkingen worden gelogd in de activiteitslog voor audittrail

Dit helpt bij het beheren van gebruikersaccounts en het resetten van 2FA wanneer gebruikers dit nodig hebben.

---

### Postbus ondersteuning bij bewerken organisatieprofiel

**Voor wie:** Organisaties

Organisaties kunnen nu ook een postbusadres invullen op de profielbewerkingspagina. Dit was eerder al mogelijk bij registratie, maar ontbrak nog bij het achteraf bewerken van het profiel. Het postbusadres is een volwaardig, afzonderlijk attribuut (`postbus_address`) op het `Organisation`-model, waardoor er duidelijk onderscheid is tussen een fysiek bezoekadres en een postbusadres.

---

## 🔧 Verbeteringen en oplossingen

### Verbeterde import van datums

**Voor wie:** Organisatoren die zaken importeren

De zaakimporter ondersteunt nu beter zowel datumnotaties (alleen datum) als datetime-notaties. Geïmporteerde datums zonder tijd worden nu correct afgekapt op het einde van de dag.

---

### Kalenderweergave: correcte tijdzones

**Voor wie:** Alle gebruikers met toegang tot de kalender

De kalenderweergave toont nu correct de lokale tijdzone en handelt dagdates correct af, vooral voor datumvelden zonder tijd.

---

### Postbus ondersteuning bij registreren van een organisatie

**Voor wie:** Organisaties die zich registreren

Organisaties kunnen zich nu ook registreren met een postbusnummer als adres, wat beter aansluit op de praktijk van veel organisaties.

---

### Betere normalisatie van Open Form verzoeken

De normalisatie van inkomende Open Form-verzoeken is verbeterd. Het patroon van twee apostrofs gevolgd door een woordkarakter wordt nu correct herkend en verwerkt. Dit voorkomt problemen bij speciale notaties in formulierinzendingen.

---

### Validatie e-mailadres organisator verbeterd

Het e-mailadres van een organisatie wordt nu strenger gevalideerd bij het opslaan, waardoor ongeldige e-mailadressen eerder worden afgevangen. Tevens wordt afgedwongen dat een organisatie altijd een geldig, echt e-mailadres heeft, zodat tijdelijke of gegenereerde adressen niet per ongeluk als officieel e-mailadres worden opgeslagen.

---

### Geen dubbele statusnotificaties meer

Wanneer de status van een zaak ongewijzigd blijft, worden er geen dubbele notificaties meer verstuurd. Dit voorkomt onnodige e-mails of meldingen bij herhaald opslaan.

---

### Activiteitslog configuratie verbeterd

De configuratie van de activiteitslog is aangepast naar `logFillable` en `logOnlyDirty`. Hierdoor worden alleen daadwerkelijk gewijzigde velden gelogd, wat de activiteitslog overzichtelijker maakt en ruis vermindert.

---

## 🐛 Bugfixes

### Import/Export notificaties

Verschillende verbeteringen aan het import/export systeem, waaronder:
- Betere foutafhandeling voor gebruikersrelaties
- Verbeterde caching van gebruikersgegevens in achtergrondwerkers

---

### Typo-fixes in zaakvertalingen

Kleine tekstcorrecties in de Nederlandstalige vertalingen rondom zaken.

---

### OpenForms normalizer: correcte verwerking van lijnen en polygonen

De OpenForms-normalizer verwerkte geometrievormen van het type lijn en polygoon onjuist. Dit is gecorrigeerd zodat kaartgegevens met lijn- of polygoonvormen nu correct worden genormaliseerd en doorgegeven.

---

### Fix: geometrie wordt correct opgeslagen bij ZGW-zaak

Bij het normaliseren van ZGW-verzoeken werd geometrie soms niet correct opgeslagen op de zaak. Dit is opgelost zodat kaartgegevens betrouwbaar worden bewaard.

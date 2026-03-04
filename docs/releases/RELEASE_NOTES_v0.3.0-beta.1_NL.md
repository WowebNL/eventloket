# Versie 0.3.0-beta.1 - Wat is er nieuw?

**Releasedatum:** 5 februari 2026

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
- De bewerkingen worden gelogd in de activiteitenlog voor audittrail

Dit helpt bij het beheren van gebruikersaccounts en het resetten van 2FA wanneer gebruikers dit nodig hebben.

---

## 🔧 Verbeteringen en oplossingen

### Verbeterde import van datums

**Voor wie:** Organisatoren die zaken importeren

De zaakimporter ondersteunt nu beter zowel datumnotaties (alleen datum) als datetime notaties. Geïmporteerde datums zonder tijd worden nu correct afgekapt op het einde van de dag.

---

### Kalenderwerkgeet: correcte tijdzones

**Voor wie:** Alle gebruikers met toegang tot de kalender

De kalenderweergave toont nu correct de lokale tijdzone en handelt dagdates correct af, vooral voor datumvelden zonder tijd.

---

### Postbus ondersteuning bij registeren van een organisatie

**Voor wie:** Organisaties die zich registreren

Organisaties kunnen zich nu ook registreren met een postbusnummer als adres, wat beter aansluit op de praktijk van veel organisaties.

---

## 🐛 Bugfixes (Backports uit v0.2.x)

### Import/Export notificaties

Verschillende verbeteringen aan het import/export systeem, waaronder:
- Betere foutafhandeling voor gebruikersrelaties
- Verbeterde caching van gebruikersgegevens in achtergrondwerknemers

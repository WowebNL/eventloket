# Versie 0.6.0 — Wat is er nieuw?

**Releasedatum:** 22 april 2026

---

## ✨ Nieuwe functionaliteit

### Meldingvragen per gemeente zelfstandig beheren

**Voor wie:** Gemeentebeheerders, Platformbeheerders

Gemeenten kunnen vanaf nu hun eigen meldingvragen rechtstreeks beheren via het instellingenpaneel, zonder tussenkomst van Woweb. Meldingvragen zijn de ja/nee-vragen die aan organisatoren worden gesteld om te bepalen of een melding volstaat of dat een vergunning nodig is.

Elke gemeente heeft een vaste set van 10 vragen die volledig instelbaar zijn:

- **Vraagtekst aanpassen** — Pas de inhoud van een vraag aan op de lokale regels en drempelwaarden. Vul bijvoorbeeld de standaard "XX"-placeholders in de standaardvragen in met de voor jouw gemeente geldende aantallen en afstanden.
- **Activeren en deactiveren** — Zet vragen die niet van toepassing zijn voor jouw gemeente uit. Inactieve vragen worden niet getoond in het aanvraagformulier en tellen niet mee in de beoordeling.
- **Volgorde aanpassen** — Versleep vragen naar de gewenste volgorde via drag-and-drop. Klik op "Volgorde aanpassen", sleep de vragen naar de juiste positie en klik op "Volgorde opslaan".

De meldingvragen zijn te vinden via **Instellingen > Meldingvragen** in het gemeenteportaal.

#### Overstappen op het nieuwe systeem

Het nieuwe systeem vervangt de aanpak waarbij meldingvragen werden beheerd als gemeente-variabelen. De overgang werkt via een toggle **"Gebruik nieuwe meldingvragen"** die per gemeente kan worden ingeschakeld.

Standaard staat de toggle uitgeschakeld en gebruikt de gemeente het bestaande systeem. Zodra de toggle wordt ingeschakeld, worden de nieuwe meldingvragen actief en worden de variabelen van het type ReportQuestion automatisch genegeerd om dubbeling te voorkomen.

Het is veilig om de toggle tijdelijk weer uit te schakelen als er iets niet klopt. De gemeente valt dan terug op het oude systeem.

---

## 🐛 Opgeloste problemen

### Meerdere organisaties mogen hetzelfde KvK-nummer gebruiken

**Voor wie:** Organisatoren

Het was voorheen niet mogelijk om een organisatie te registreren met een KvK-nummer dat al door een andere organisatie in gebruik was. Dit kon problemen geven in situaties waarbij meerdere organisaties onder hetzelfde KvK-nummer opereren. De beperking is opgeheven: hetzelfde KvK-nummer mag nu door meerdere organisaties worden gebruikt.

---

### Maximale bestandsgrootte voor uploads verhoogd naar 30 MB

**Voor wie:** Organisatoren, Gemeentemedewerkers, Behandelaars

Het uploaden van documenten was beperkt tot bestanden van maximaal 20 MB. Deze limiet is verhoogd naar 30 MB, zodat grotere bestanden zoals plattegronden, technische tekeningen of uitgebreide bijlagen zonder problemen kunnen worden geüpload.

---

## 📱 Wat moet je doen?

### Voor gemeentebeheerders

Om over te stappen op het nieuwe meldingvragensysteem, doorloop je de volgende stappen:

1. Ga naar **Instellingen > Meldingvragen** in het gemeenteportaal.
2. Controleer de standaardvragen en pas de vraagteksten aan waar nodig. Vul met name de "XX"-placeholders in met de voor jouw gemeente geldende waarden.
3. Deactiveer vragen die niet van toepassing zijn voor jouw gemeente.
4. Stel de gewenste volgorde in.
5. Schakel de toggle **"Gebruik nieuwe meldingvragen"** in om het nieuwe systeem te activeren.


### Voor eindgebruikers (organisatoren)
**Niets!** Alle wijzigingen werken direct na de update. De vragen in het aanvraagformulier kunnen er iets anders uitzien zodra jouw gemeente is overgestapt op het nieuwe systeem, maar de werking blijft hetzelfde.

# Eventloket versie 1.3.4: wat is er nieuw?

**Releasedatum:** 1 oktober 2026

---

Deze versie lost twee problemen op. Het accepteren van een uitnodiging lukte niet altijd als je al een account had, en bij het invullen van een adres in het aanvraagformulier werden straat en plaats soms niet automatisch aangevuld. Er zijn geen nieuwe functies en er verandert niets aan de manier van werken.

---

## 🐛 Opgeloste problemen

### Een uitnodiging accepteren gaf een foutmelding als je al een account had

**Voor wie:** Iedereen die collega's uitnodigt of een uitnodiging ontvangt (gemeentemedewerkers, behandelaars, adviseurs, organisatoren en beheerders)

Kreeg je een uitnodiging voor Eventloket terwijl je al een account had, dan kon het accepteren mislukken. Je zag dan een foutmelding en de uitnodiging bleef openstaan. Was je al ingelogd, dan kreeg je in plaats daarvan de melding dat je geen toegang had.

Dit gebeurde alleen als het e-mailadres in de uitnodiging met hoofdletters was ingetypt, bijvoorbeeld een voornaam met een hoofdletter. Eventloket bewaart het e-mailadres van een account altijd in kleine letters, maar bij een uitnodiging werd het adres bewaard zoals het was ingetypt. Daardoor herkende Eventloket het bestaande account niet.

Uitnodigingen bewaren het e-mailadres nu op dezelfde manier als een account, en de controle op dubbele uitnodigingen kijkt niet meer naar hoofdletters.

### Straat en plaats werden soms niet aangevuld bij een adres

**Voor wie:** Organisatoren

Kies je in het aanvraagformulier bij de locatie voor een evenement in een gebouw, dan vul je een postcode en huisnummer in en worden straat en plaats automatisch aangevuld. Bij het eerste adres gebeurde dat soms niet. Straat en plaats bleven leeg, en er verscheen ook geen melding dat het adres niet gevonden werd. Pas als je daarna nog een teken typte, werd het adres alsnog opgezocht.

Het adres wordt nu direct opgezocht zodra postcode en huisnummer zijn ingevuld, ook bij het eerste adres. Een straat of plaats die je zelf hebt aangepast, blijft staan.

---

## 📱 Wat moet je doen?

### Voor gemeenten, behandelaars, adviseurs en beheerders

**In de meeste gevallen niets.** Nieuwe uitnodigingen werken zodra de update live staat.

Is een uitnodiging vóór deze update verstuurd en lukt het accepteren nog steeds niet, stuur dan een nieuwe uitnodiging.

### Voor organisatoren

**Niets.** Het aanvullen van het adres werkt weer zodra de update live staat. Er zijn geen gegevens verloren gegaan.

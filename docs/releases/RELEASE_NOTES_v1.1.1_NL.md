# Eventloket Versie 1.1.1 — Wat is er nieuw?

**Releasedatum:** 20 juli 2026

---

Deze versie is een onderhoudsrelease die vooral de rollen en rechten binnen de gemeente rechttrekt en een paar zichtbare oneffenheden oplost. Behandelaars kunnen voortaan zelf een zaak oppakken of weer vrijgeven, de vier gemeenterollen hebben overal dezelfde naam, coördinatoren krijgen de rechten die bij hun rol horen en de placeholdernaam "Mijn omgeving" verdwijnt uit de aanvraag-PDF. Hieronder lees je per onderdeel wat er verandert.

---

## 🐛 Opgeloste problemen

### Behandelaars kunnen weer zelf een zaak oppakken of vrijgeven

**Voor wie:** Behandelaars, Coördinatoren

Met de introductie van de coördinatorrol in versie 1.1.0 werden zaken alleen nog via de coördinator toegewezen. Daardoor kon een behandelaar een openstaande zaak niet meer zelf op zijn naam zetten, iets wat daarvoor wel kon. Dat is hersteld met twee knoppen op de zaakpagina, allebei met een bevestiging vooraf:

- **Zaak oppakken:** zet een nog niet toegewezen zaak op je eigen naam. Dit kan alleen zolang er nog geen behandelaar aan de zaak hangt (een zaak van een collega overnemen blijft aan de coördinator).
- **Zaak vrijgeven:** haal je eigen naam van een zaak af, zodat deze weer terugkomt in de lijst met openstaande zaken. De coördinator krijgt hiervan een melding "Zaak vrijgegeven". Heeft de gemeente geen coördinator, dan gaat die melding naar alle behandelaars. Deze melding kun je, net als andere meldingen, per kanaal aan- of uitzetten in je accountinstellingen.

De werkvoorraadfilters, de tellers in het menu en het activiteitenlog verwerken deze wijzigingen automatisch.

---

### Coördinatoren hebben de juiste rechten

**Voor wie:** Coördinatoren

Coördinatoren misten enkele rechten die wel bij hun rol horen, terwijl ze de status van een zaak al wél mochten aanpassen. Dat is nu gelijkgetrokken. Een coördinator kan voortaan binnen de eigen gemeente:

- documenten uploaden en een nieuwe versie van een document toevoegen;
- het activiteitenlog van een zaak bekijken via "Bekijk activiteiten";
- een zaak afronden.

---

### Gemeenterollen hebben overal dezelfde naam

**Voor wie:** Gemeentebeheerders, Gemeentemedewerkers, Behandelaars, Coördinatoren

De vier rollen die een gemeentemedewerker kan hebben, werden op verschillende plekken net iets anders benoemd. Vanaf nu heten ze overal hetzelfde:

- Behandelaar
- Coördinator (+behandelaar)
- Gemeentelijk beheerder
- Gemeentelijk beheerder (+behandelaar)

De toevoeging "(+behandelaar)" maakt duidelijk dat ook een coördinator zelf zaken behandelt. Daarnaast werd in het berichtenoverzicht van een gesprek bij een bericht van een coördinator een verkeerde, technische tekst getoond in plaats van de rolnaam. Dat is opgelost, en de uitlegtekst bij het uitnodigen van een coördinator klopt weer met wat de rol daadwerkelijk mag.

---

### "Mijn omgeving" niet meer in de aanvraag-PDF

**Voor wie:** Organisatoren

Bij een aanvraag op persoonlijke titel stond in de kop van de aanvraag-PDF nog "Organisator: Mijn omgeving". Die placeholdernaam is nu uit de PDF gehaald. Bij een aanvraag op persoonlijke titel vervalt de regel "Organisator" helemaal, omdat de aanvrager al direct daaronder staat. Bij een aanvraag namens een organisatie blijft de organisatienaam gewoon staan.

*Let op: de PDF wordt eenmalig bij het indienen gemaakt en bewaard. Al ingediende aanvragen houden hun bestaande PDF.*

---

### Zakenoverzicht toont evenementlocaties

**Voor wie:** Gemeentemedewerkers, Behandelaars, Coördinatoren

In het zakenoverzicht stond een kolom met de evenementtypen. Deze is vervangen door een kolom met de evenementlocaties, wat in de praktijk bruikbaarder is om zaken uit elkaar te houden.

---

## 📱 Wat moet je doen?

**Niets!** Alle verbeteringen werken automatisch. Behandelaars en coördinatoren zien de nieuwe knoppen en rechten meteen na de update.

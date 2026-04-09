# Meldingvragen

Meldingvragen zijn de ja/nee-vragen die in het aanvraagformulier aan de organisator worden gesteld om te bepalen of voor een evenement een melding voldoende is of dat er een vergunning nodig is. Elke gemeente heeft een eigen set van maximaal 10 vragen die per stuk geconfigureerd kunnen worden.

## Werking

Wanneer een organisator een aanvraag doet, worden alle actieve meldingvragen opeenvolgend gesteld. De logica werkt als volgt.

- Beantwoordt de organisator **alle** actieve vragen met **ja**, dan is een melding voldoende en worden de vergunningvragen in het formulier niet getoond.
- Beantwoordt de organisator **één of meer** vragen met **nee**, dan is een vergunning nodig.

Het is daarom belangrijk dat elke vraag zo geformuleerd is dat "ja" betekent dat er geen bijzonderheden zijn en "nee" betekent dat er wél iets bijzonders aan de hand is.

## Standaardvragen

Bij het aanmaken van een nieuwe gemeente worden automatisch 10 standaardvragen aangemaakt. Dit zijn de volgende vragen.

| Volgorde | Vraag |
|----------|-------|
| 1 | Is het aantal aanwezigen bij uw evenement minder dan XX personen? |
| 2 | Het evenement vindt plaats op een maandag, dinsdag, woensdag, donderdag tussen 09.00 - 22.00 uur. |
| 3 | Het evenement vindt plaats op een vrijdag of zaterdag tussen 09.00 en 24.00 uur. |
| 4 | Het evenement vindt plaats op een zon- en of feestdag tussen 09.00 en 23.00 uur. |
| 5 | Is de geluidsproductie lager dan 80 dB(A) bronvermogen, gemeten op 3 meter afstand van de bron? |
| 6 | Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten? |
| 7 | Indien er objecten geplaatst worden, zijn deze dan kleiner XX m2? |
| 8 | Er worden geen gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer? |
| 9 | Het evenement valt niet onder de categorie een braderie, snuffelmarkt of optocht. |
| 10 | Er wordt geen vuurwerk afgestoken. |

De "XX" in vraag 1 en 7 zijn placeholders. De gemeente past de vraagtekst aan naar de voor hen geldende waarden.

---

## Voor platformbeheerders

Platformbeheerders kunnen de meldingvragen van alle gemeenten inzien en bewerken via het beheerpaneel. De vragen zijn te vinden op de detailpagina van een gemeente, onder het tabblad "Meldingvragen".

### Wat kan een platformbeheerder?

- De vraagtekst van een bestaande vraag aanpassen.
- Een vraag activeren of deactiveren. Inactieve vragen worden niet getoond in het formulier en tellen niet mee in de beoordeling.
- De volgorde van de vragen aanpassen via drag-and-drop (klik op "Volgorde aanpassen", sleep de vragen naar de gewenste positie en klik op "Volgorde opslaan").
- Het nieuwe meldingvragensysteem in- of uitschakelen voor een gemeente via de toggle "Gebruik nieuwe meldingvragen" (zie ook het hoofdstuk Overgang hieronder).

### Wat kan een platformbeheerder niet?

Meldingvragen kunnen niet worden aangemaakt of verwijderd. Elke gemeente heeft altijd de 10 standaardvragen. Vragen die niet van toepassing zijn kunnen worden gedeactiveerd.

---

## Voor gemeentebeheerders

Gemeentebeheerders kunnen de meldingvragen van hun eigen gemeente beheren via het instellingenpaneel, onder "Instellingen > Meldingvragen".

### Wat kan een gemeentebeheerder?

- De vraagtekst van een bestaande vraag aanpassen. Pas de vraag aan zodat deze past bij de lokale regels en drempelwaarden (bijvoorbeeld de "XX" invullen met het geldende aantal personen).
- Een vraag activeren of deactiveren. Gebruik dit wanneer een vraag niet van toepassing is voor de gemeente.
- De volgorde van de vragen aanpassen via drag-and-drop (klik op "Volgorde aanpassen", sleep de vragen naar de gewenste positie en klik op "Volgorde opslaan").
- Het nieuwe meldingvragensysteem in- of uitschakelen via de toggle "Gebruik nieuwe meldingvragen" onderaan de pagina.

### Wat kan een gemeentebeheerder niet?

Meldingvragen kunnen niet worden aangemaakt of verwijderd.

### Een vraag bewerken

1. Ga naar "Instellingen > Meldingvragen".
2. Klik op het potloodicoon achter de vraag die je wilt bewerken.
3. Pas de vraagtekst aan en/of wijzig de actief-status.
4. Klik op "Opslaan".

### De volgorde aanpassen

1. Ga naar "Instellingen > Meldingvragen".
2. Klik op de knop "Volgorde aanpassen".
3. Sleep de vragen naar de gewenste positie.
4. Klik op "Volgorde opslaan" om de nieuwe volgorde te bevestigen.

---

## Overgang van variabelen naar meldingvragen

Voorheen werden meldingvragen door sommige gemeenten beheerd als gemeente-variabelen van het type "ReportQuestion". Het nieuwe systeem vervangt deze aanpak met een eigen tabel en een gecentraliseerde beheerinterface.

### Hoe werkt de overgang?

De overgang wordt per gemeente geactiveerd via de toggle **"Gebruik nieuwe meldingvragen"** op de meldingvragenpagina. Deze toggle is beschikbaar voor zowel platform- als gemeentebeheerders.

**Standaard staat de toggle uitgeschakeld.** Een gemeente gebruikt dan nog het oude systeem met variabelen.

Zodra de toggle wordt ingeschakeld geldt het volgende.

- Het nieuwe meldingvragensysteem is actief. De API levert de actieve meldingvragen uit de nieuwe tabel.
- De gemeente-variabelen van het type "ReportQuestion" worden automatisch uitgesloten van de variabelen-API. Zo worden dubbele meldingvragen in het formulier voorkomen.

### Stappenplan voor de overgang per gemeente

1. Controleer en pas indien nodig de standaardvragen aan via "Instellingen > Meldingvragen". Controleer met name de vraagteksten met "XX" en vul de juiste drempelwaarden in.
2. Deactiveer vragen die niet van toepassing zijn voor de gemeente.
3. Stel de gewenste volgorde in.
4. Schakel de toggle "Gebruik nieuwe meldingvragen" in. Vanaf dit moment worden de nieuwe meldingvragen gebruikt en de variabelen van het type ReportQuestion genegeerd.

Het is veilig om de toggle tijdelijk weer uit te schakelen als er iets mis gaat. De gemeente valt dan terug op het oude systeem totdat de toggle opnieuw wordt ingeschakeld.

---

## API-gedrag

De meldingvragen zijn beschikbaar via de REST API voor externe systemen (zoals het aanvraagformulier).

**Endpoint:** `GET /api/report-questions/{brk_identification}`

- Wanneer "Gebruik nieuwe meldingvragen" **uitgeschakeld** is, retourneert dit endpoint een lege lijst.
- Wanneer "Gebruik nieuwe meldingvragen" **ingeschakeld** is, retourneert dit endpoint alle actieve vragen van de gemeente, gesorteerd op volgorde.

De variabelen-API (`/api/municipality-variables/{brk_identification}`) sluit automatisch variabelen van het type ReportQuestion uit wanneer het nieuwe systeem actief is.

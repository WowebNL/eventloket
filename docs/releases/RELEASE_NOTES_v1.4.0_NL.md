# Eventloket versie 1.4.0: wat is er nieuw?

**Releasedatum:** 7 oktober 2026

---

Deze versie bouwt verder op de koppeling met het eigen zaaksysteem van een gemeente uit versie 1.3.0. Zaken komen nu altijd in het juiste zaaksysteem terecht, ook als een gemeente tijdelijk terugvalt op het centrale zaaksysteem, en gemeenten langs de route van een evenement krijgen hun doorkomstzaak betrouwbaarder. Verder blijft het zaakscherm werken als een bestand niet kan worden opgehaald, werkt de GeoJSON-export uit de kalender weer, en zijn er een paar kleinere verbeteringen in het aanvraagformulier. Aan het einde staat één bekend punt met een tijdelijke oplossing.

---

## ✨ Verbeteringen

### Doorkomstzaken voor gemeenten langs de route

**Voor wie:** Gemeenten, behandelaars

Gaat de route van een evenement door meerdere gemeenten, dan krijgt elke gemeente waar de route doorheen gaat een eigen doorkomstzaak. Daar zijn drie dingen aan verbeterd:

* Lukte het aanmaken van de doorkomstzaak bij één gemeente niet, dan kregen de gemeenten die daarna op de route lagen ook geen doorkomstzaak. Nu wordt elke gemeente apart afgehandeld. Een probleem bij één gemeente houdt de rest niet meer tegen, en bij een nieuwe poging worden alleen de gemeenten die nog geen doorkomstzaak hebben opnieuw geprobeerd.
* Werkt een gemeente met een eigen zaaksysteem en kan de koppeling daarmee op dat moment niet worden gebruikt, dan wordt de doorkomstzaak nu volledig in het centrale zaaksysteem aangemaakt. Voorheen mislukte het aanmaken in die situatie.
* Een doorkomstzaak, en de documenten die daarvoor naar een ander zaaksysteem worden gekopieerd, staan nu op naam van de organisatie van het zaaksysteem waarin ze worden aangemaakt. Voorheen stonden ze op naam van de organisatie van de hoofdzaak.

### Het zaakscherm blijft werken als een bestand niet kan worden opgehaald

**Voor wie:** Iedereen die zaken bekijkt (organisatoren, gemeentemedewerkers, behandelaars en adviseurs)

Kon één bestand bij een zaak niet uit het zaaksysteem worden opgehaald, dan gaf het hele zaakscherm een foutmelding. Je kon de zaak dan helemaal niet meer bekijken.

Het zaakscherm blijft nu gewoon werken. Een bestand dat niet kan worden opgehaald, wordt weggelaten, en boven de lijst staat een melding dat er bestanden ontbreken. Dat geldt voor de bestanden bij de zaak en voor de bestanden bij een besluit. Er zijn twee soorten meldingen:

* Is het zaaksysteem tijdelijk niet bereikbaar of gaat er iets mis bij het ophalen, dan zie je hoeveel bestanden nu niet kunnen worden getoond. Probeer het dan later opnieuw.
* Mag een bestand via de koppeling van Eventloket niet worden opgehaald, dan zie je de melding "Niet beschikbaar via deze koppeling". Dat is een instelling in het zaaksysteem, dus later opnieuw proberen helpt dan niet. Heb je zo'n bestand nodig, neem dan contact op met de beheerder.

---

## 🐛 Opgeloste problemen

### Aanvragen voor een gemeente die terugvalt op het centrale zaaksysteem mislukten

**Voor wie:** Gemeenten, organisatoren

Kan de koppeling met het eigen zaaksysteem van een gemeente niet worden gebruikt, bijvoorbeeld omdat die op inactief staat, dan maakt Eventloket nieuwe aanvragen van die gemeente aan in het centrale zaaksysteem. In die situatie kon het indienen van een aanvraag mislukken, omdat Eventloket het soort zaak (het zaaktype) nog uit het eigen zaaksysteem van de gemeente haalde. Het zaaktype komt nu uit hetzelfde zaaksysteem als de zaak zelf, zodat de aanvraag gewoon in het centrale zaaksysteem wordt aangemaakt.

Voor beheerders van de koppeling zijn er twee hulpmiddelen bijgekomen:

* In het overzicht van koppelingen staat een koppeling die wel op actief staat, maar waarvan de instellingen niet bruikbaar zijn, nu als "Actief, niet bruikbaar". Zaken van die gemeente komen dan in het centrale zaaksysteem terecht.
* De verbindingstest controleert nu ook of de adressen voor zaken en voor zaaktypen naar hetzelfde zaaksysteem wijzen. Is een van de twee leeg terwijl de koppeling een eigen zaaksysteem gebruikt, dan meldt de test dat.

### Indienen kon mislukken door een zaaknummer dat al bestond

**Voor wie:** Organisatoren, gemeenten

Het zaaknummer van een zaak wordt uitgegeven door het zaaksysteem. Sinds gemeenten een eigen zaaksysteem kunnen koppelen, kunnen twee zaaksystemen los van elkaar hetzelfde nummer uitgeven. Eventloket accepteerde zo'n nummer maar één keer. Kreeg een nieuwe aanvraag een nummer dat al bij een zaak uit een ander zaaksysteem hoorde, dan zag de organisator een foutmelding, terwijl de zaak in het zaaksysteem wel was aangemaakt.

Een zaaknummer hoeft nu alleen uniek te zijn binnen het zaaksysteem dat het heeft uitgegeven. Bestaande zaken zijn bij deze update aan hun eigen zaaksysteem gekoppeld.

### De GeoJSON-export uit de kalender werkt weer

**Voor wie:** Gemeentebeheerders, adviseurs en beheerders

De export naar GeoJSON in de kalender kon mislukken. Dat gebeurde als de einddatum van de exportperiode leeg was, wat in de lijstweergave standaard zo was, en ook als er in de gekozen periode één geïmporteerd evenement zat. De export werkt nu weer:

* In de lijstweergave is de einddatum van de export nu vooraf ingevuld, één maand na de begindatum. Je kunt die gewoon aanpassen.
* Ontbreekt de begin- of einddatum toch, dan krijg je een melding bij het veld in plaats van een foutmelding.
* Geïmporteerde evenementen, waarvan geen gegevens uit het zaaksysteem bekend zijn, worden overgeslagen en komen niet in de GeoJSON-export.
* De export bevat nu een vaste set gegevens per evenement: zaaknummer, naam van het evenement, zaaktype, gemeente, naam van de locatie, begin en einde, risicoclassificatie, status en de kleur van de status. Voorheen stonden er veel meer gegevens in.

### Tenten, podia of overkappingen kiezen zonder er één te beschrijven

**Voor wie:** Organisatoren

In het aanvraagformulier kun je bij de vervolgvragen aangeven dat je tenten, podia of overkappingen plaatst. Daarna kon je de lijst van die tenten, podia of overkappingen leeg laten, en toch doorgaan. De aanvraag kwam dan binnen zonder dat er één bouwsel was beschreven. Kies je nu een van deze soorten, dan moet je er minstens één invullen. Kies je een soort niet, dan verandert er niets.

De knoppen om een tent, podium of overkapping toe te voegen hebben nu ook dezelfde tekst, bijvoorbeeld "Wilt u (nog) een tent toevoegen?".

### Voettekst van de bevestigingsmail en het aanvraagoverzicht

**Voor wie:** Organisatoren, gemeenten

In de voettekst van de bevestigingsmail na het indienen en van het PDF-overzicht van een aanvraag stond naast Eventloket ook de naam van één specifieke regio. Daar staat nu alleen nog Eventloket.

---

## 🔒 Privacy

**Voor wie:** Iedereen, maar er is niets zichtbaars aan

Na het indienen van een aanvraag maakt Eventloket het BSN en het KvK-nummer in de opgeslagen aanvraag onleesbaar. Dat gebeurt als laatste stap, nadat onder meer de bevestigingsmail is verstuurd. Ging er in een eerdere stap na het indienen iets mis, dan werd die laatste stap niet uitgevoerd en bleven deze nummers leesbaar opgeslagen. Nu worden ze ook in dat geval onleesbaar gemaakt. Aanvragen waarbij dit eerder niet is gebeurd, worden bij het uitrollen van deze versie alsnog bijgewerkt.

---

## 🔧 Overige verbeteringen

* Een technische verbetering in hoe de kalender evenementen laadt. Je merkt hier niets van.

---

## ⚠️ Bekend punt

### Een bestaande route of een bestaand vlak verslepen wordt niet opgeslagen

**Voor wie:** Organisatoren, en gemeenten die vragen krijgen van organisatoren

Staat er bij het openen van een aanvraag al een route of vlak op de kaart, bijvoorbeeld bij een kopie van een eerdere aanvraag, een hervatte aanvraag of een concept dat opnieuw is geladen, en versleep je die route of dat vlak daarna, dan wordt de wijziging niet opgeslagen. Op het scherm verschuift de lijn wel, maar Eventloket houdt de oude route vast.

Dit is geen nieuwe fout in deze versie. Het zat er al in sinds versie 1.0. Een route of vlak dat je nieuw tekent, wordt wel goed opgeslagen, en verwijderen werkt ook.

**Tijdelijke oplossing:** versleep een bestaande route of een bestaand vlak niet, maar verwijder het en teken het opnieuw. Je kunt ook een nieuwe aanvraag starten.

Een oplossing volgt in een volgende versie.

---

## 📱 Wat moet je doen?

### Voor organisatoren

**In de meeste gevallen niets.** Wil je een route of vlak aanpassen dat al op de kaart stond, verwijder het dan en teken het opnieuw (zie het bekende punt hierboven).

### Voor gemeenten, behandelaars en adviseurs

**Niets.** De verbeteringen werken zodra de update live staat. Krijg je vragen van organisatoren over een route die niet goed is overgekomen, wijs dan op de tijdelijke oplossing bij het bekende punt.

Lees je de GeoJSON-export in een eigen programma in, bijvoorbeeld een kaartprogramma? Kijk dan na de update even na of de kolommen nog kloppen, want de export bevat nu een vaste, kleinere set gegevens.

### Voor beheerders van een koppeling met een eigen zaaksysteem

Kijk na de update in het overzicht van koppelingen of er een koppeling op "Actief, niet bruikbaar" staat. Zo ja, test de verbinding om te zien wat er misgaat. Zolang dat zo is, komen nieuwe aanvragen van die gemeente in het centrale zaaksysteem terecht.

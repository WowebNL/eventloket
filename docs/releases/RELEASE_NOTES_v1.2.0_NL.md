# Eventloket versie 1.2.0: wat is er nieuw?

**Releasedatum:** 27 augustus 2026

---

Deze versie brengt drie nieuwe functies en een reeks verbeteringen. Gemeenten kunnen voortaan eigen vragen aan het aanvraagformulier toevoegen, organisatoren kunnen een vooraankondiging omzetten naar een definitieve aanvraag, en bij meerdaagse evenementen zijn per dag aparte tijden voor het evenement, de opbouw en de afbouw in te vullen. Daarnaast zijn de aanvrager- en organisatiegegevens op de zaakpagina overzichtelijker gemaakt en is een aantal problemen opgelost, waaronder een dat ertoe kon leiden dat een aanvraag bij de verkeerde gemeente terechtkwam. Hieronder lees je per onderdeel wat er verandert.

---

## ✨ Nieuwe functionaliteit

### Eigen vragen per gemeente in het aanvraagformulier

**Voor wie:** Gemeentebeheerders, Organisatoren

Een gemeente kan voortaan tot vijftien eigen vragen aan het aanvraagformulier toevoegen. Per vraag kies je het type (tekstvak, keuze uit één optie of aankruisvakjes), een label, een eventuele toelichting, of de vraag verplicht is en op welke aanvraagsoorten hij van toepassing is. De organisator krijgt deze vragen in een nieuwe stap "Aanvullende vragen", vlak voor de bijlagen, en de antwoorden komen in de samenvatting en in de aanvraag-PDF terecht.

De functie is optioneel: zolang een gemeente geen vragen instelt, verandert er niets aan het formulier. Vragen zijn te beheren door platformbeheerders, gemeentebeheerders en beheerders van een controlerende gemeente. Al ingediende aanvragen blijven de vragen tonen zoals die op het moment van indienen luidden, ook als een vraag later wordt aangepast of verwijderd.

---

### Een vooraankondiging omzetten naar een definitieve aanvraag

**Voor wie:** Organisatoren, Gemeentemedewerkers, Behandelaars

Heeft een organisator eerder een vooraankondiging ingediend, dan kan die nu worden omgezet naar een definitieve vergunningaanvraag. Bij een ingediende vooraankondiging verschijnt daarvoor de actie "Definitieve aanvraag indienen", die de gegevens overneemt en het zaaknummer van de vooraankondiging vastlegt. Ook in het formulier zelf kan de organisator bij de vraag over een eerdere vooraankondiging kiezen uit de eigen vooraankondigingen; op basis van de evenementdatum wordt de meest waarschijnlijke alvast voorgesteld, maar de organisator bevestigt de keuze altijd zelf.

Op beide zaken is het verband zichtbaar, in beide richtingen ("Vervangt vooraankondiging" en "Opgevolgd door"), met een link naar de andere zaak. De vervangen vooraankondiging wordt niet meer los in de kalender getoond, zodat hetzelfde evenement er niet dubbel in staat. Het omzetten geldt alleen op het vergunningtraject en een vooraankondiging kan één keer worden vervangen.

---

### Tijden per dag voor meerdaagse evenementen, opbouw en afbouw

**Voor wie:** Organisatoren, Gemeentemedewerkers, Behandelaars

Een evenement had één start- en eindmoment, waardoor bij een meerdaags evenement niet was aan te geven wanneer elke afzonderlijke dag begint en eindigt. Datzelfde gold voor de opbouw en de afbouw. Loopt een periode over meerdere dagen, dan verschijnt nu onder de begin- en eindtijd een overzicht met per dag een start- en eindtijd. Die dagregels volgen automatisch de gekozen periode en zijn niet los toe te voegen of te verwijderen.

Een eindtijd in de kleine uurtjes hoort nog bij de avond ervoor: een evenement van 16:00 tot 02:00 blijft dus eendaags. Pas als de eindtijd na 06:00 de volgende ochtend valt, telt een dag als een aparte dag. De ingevulde tijden komen terug in de samenvatting, in de aanvraag-PDF en op de zaakpagina. In de kalender blijft het evenement één blok over de hele periode. Bij deze wijziging is ook de controle op overlappende evenementen nauwkeuriger gemaakt.

---

## 🐛 Opgeloste problemen

### Aanvrager- en organisatiegegevens overzichtelijker en beter afgeschermd

**Voor wie:** Gemeentemedewerkers, Behandelaars, Adviseurs, Beheerders, Organisatoren

De zaakpagina toont voor gemeenten, adviseurs en beheerders nu ook de naam van de indiener, de organisatienaam, het evenementadres en het KvK-nummer bij de zaakgegevens. De velden zijn opnieuw geordend (evenement, betrokken partijen, zaakadministratie) en het veld voor de indiener heet nu "Naam indiener". Een lege waarde wordt weggelaten, zoals elders in de applicatie al gebeurde.

Voor de organisator zijn er twee dingen rechtgezet. De organisator ziet deze gegevens nu bij zaken van de eigen organisatie, maar niet bij zaken van andere organisaties in de gedeelde kalender. En de placeholder "Mijn omgeving" wordt niet langer als organisatienaam getoond bij aanvragen op persoonlijke titel. Tot slot is de export-actie in de kalender voor de organisator uitgezet, omdat die kalender niet tot één organisatie beperkt is.

---

### Ingetrokken of afgevinkte antwoorden verdwijnen nu echt uit de aanvraag

**Voor wie:** Organisatoren, Gemeenten

Een veld dat eerst was ingevuld en daarna verborgen raakte, hield tot nu toe zijn waarde. Dat gebeurde bijvoorbeeld bij een gekopieerde aanvraag waarin de organisator een andere locatiesoort koos: het adres van het oorspronkelijke evenement bleef achter de schermen staan. Dat verborgen antwoord kon meereizen naar de ingediende aanvraag, het zaaksysteem en de PDF, en bij een locatie zelfs meewegen in de bepaling van de gemeente. In het slechtste geval kwam een aanvraag daardoor bij de verkeerde gemeente terecht.

Bij het indienen worden nu alleen nog de antwoorden meegenomen die op dat moment ook echt aan de organisator zijn gevraagd. Een teruggetrokken of afgevinkt antwoord telt niet meer mee, in de aanvraag, in het zaaksysteem, in de PDF en bij de gemeentebepaling. De overzichten die je onderweg te zien krijgt, tonen precies dezelfde set, zodat het overzicht en de ingediende aanvraag niet meer van elkaar kunnen afwijken. Een tussentijds bewaard concept houdt zijn eigen ingevulde antwoorden.

---

### Aanvraag met meerdere routes informeert nu alle betrokken gemeenten

**Voor wie:** Organisatoren, Gemeenten

Tekende een organisator meerdere routes op de kaart, dan werden de doorkomstzaken alleen voor de eerste route aangemaakt. De gemeenten waar de tweede en volgende routes doorheen liepen, kregen daardoor stilzwijgend geen melding. Voor iedere ingetekende route worden nu doorkomstzaken aangemaakt, zodat alle betrokken gemeenten worden geïnformeerd. Ook een route die in meerdere delen is getekend, laat de verwerking niet meer vastlopen.

---

### Bijlagen bij de resultaatmail worden begrensd

**Voor wie:** Gemeentemedewerkers, Behandelaars, Organisatoren

Bij het afronden van een zaak kan een behandelaar documenten meesturen met het resultaatbericht. Er zat geen grens op de gezamenlijke omvang van die bijlagen, waardoor een paar grote documenten het bericht over de limiet van de ontvangende mailserver konden duwen. Die weigert dan de hele mail, dus de organisator kreeg in dat geval niets: geen bericht, geen link en geen documenten.

De gezamenlijke omvang van de bijlagen is nu begrensd. Documenten worden meegestuurd tot die grens is bereikt; een document dat er niet meer bij past, wordt overgeslagen zonder de kleinere documenten daarachter te blokkeren. De mail benoemt welke documenten niet zijn meegestuurd en die blijven gewoon op de zaak beschikbaar, bereikbaar via de knop die al in de mail staat.

---

### Risicoclassificatie wijzigen geeft geen onduidelijke foutmelding meer

**Voor wie:** Gemeentemedewerkers, Behandelaars

Het wijzigen van de risicoclassificatie kon een algemene foutmelding geven. Ook kon een tweede wijziging in dezelfde sessie mislukken doordat nog met verouderde gegevens werd gewerkt. Beide zijn opgelost: gaat er bij het opslaan iets mis in het zaaksysteem, dan volgt nu een duidelijke melding, en een tweede wijziging werkt weer met de actuele gegevens.

---

### Zoeken naar een adres laat de pagina niet meer vastlopen

**Voor wie:** Organisatoren

Voor het opzoeken van adressen gebruikt Eventloket een externe dienst. Was die dienst even niet bereikbaar, dan kon de pagina met een fout stoppen in plaats van gewoon zonder adres te tonen. De pagina blijft nu werken als de adresdienst tijdelijk uitvalt en herstelt vanzelf zodra de dienst weer bereikbaar is. De zoekopdrachten geven daarnaast sneller op, zodat je niet lang op een hangende pagina wacht.

---

### Risicoscan-vraag verscheen ten onrechte, en een melding werd verkeerd benoemd

**Voor wie:** Organisatoren, Gemeenten

Bij een melding en bij een vooraankondiging wordt de risicoscan overgeslagen. Toch verscheen in de samenvatting en de PDF een los seizoensveld dat automatisch uit de begindatum wordt afgeleid, waardoor er een risicoscan-blokje opdook met een vraag die de organisator nooit had beantwoord. Dat veld wordt nu weggelaten zodra de risicoscan niet van toepassing is. Verder werd een melding op de laatste stap, in de samenvatting en in de PDF soms als "Evenementenvergunning" aangeduid bij gemeenten die met eigen rapportvragen werken; ook dat klopt nu. Tot slot kan een rapportvraag niet meer leeg worden opgeslagen.

---

### Berichten in een gesprek staan weer op volgorde

**Voor wie:** Gemeentemedewerkers, Behandelaars, Adviseurs

In sommige zaken stonden de berichten in een gesprek door elkaar, bijvoorbeeld met een reactie boven de vraag waar die bij hoorde. Dit gebeurde nadat er een document aan een bericht was toegevoegd. De berichten worden nu altijd op tijdstip getoond, in de juiste volgorde.

---

### Hele rij klikbaar in de kalenderlijst

**Voor wie:** Alle gebruikers met toegang tot de kalender

In de lijstweergave van de kalender opende alleen de knop een zaak, terwijl klikken op de rest van de rij niets deed. Voortaan opent een klik ergens op de rij de zaak, in alle panelen.

---

### Kleinere aanpassingen

**Voor wie:** Alle gebruikers

* De risicoklasse die eerder als "0" werd getoond, verschijnt nu overal als "M". De onderliggende waarde verandert niet.
* De twee vragen over het geluidsniveau (dB(A) en dB(C)) zijn duidelijker geformuleerd. Alleen de teksten zijn aangepast, de antwoorden blijven ongewijzigd.
* Een instructietekst is aangepast van "check" naar "Raadpleeg".
* Het logo is vervangen door het huidige beeldmerk en er is een favicon toegevoegd. Doordat de bestandsnamen zijn ververst, tonen browsers niet langer een oud logo uit hun eigen cache.

---

## 📱 Wat moet je doen?

### Voor gemeentebeheerders

Wil je eigen vragen aan het aanvraagformulier toevoegen? Stel die in via de instellingen van de gemeente. Zolang je niets instelt, blijft het formulier zoals het was. Werk je met eigen rapportvragen, dan is het goed om te weten dat een rapportvraag niet meer leeg kan worden opgeslagen.

### Voor organisatoren

**Niets verplicht.** De nieuwe mogelijkheden staan voor je klaar: een eerder ingediende vooraankondiging kun je omzetten naar een definitieve aanvraag, en bij een meerdaags evenement vul je per dag de tijden voor het evenement, de opbouw en de afbouw in.

### Voor gemeentemedewerkers, behandelaars en adviseurs

**Niets!** Alle verbeteringen werken automatisch na de update.

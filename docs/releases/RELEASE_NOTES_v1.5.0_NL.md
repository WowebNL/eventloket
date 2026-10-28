# Eventloket versie 1.5.0: wat is er nieuw?

**Releasedatum:** 28 oktober 2026

---

Deze versie maakt het mogelijk dat een gemeente meerdere soorten aanvragen op één zaaktype in het eigen zaaksysteem laat binnenkomen. Daarom staat in de overzichten nu het aanvraagtype van een zaak, en is de kolom met het zaaktype standaard verborgen. De GeoJSON-export uit de kalender bevat meer gegevens. Verder gaat het aantal aanwezigen bij elke soort aanvraag goed naar het zaaksysteem, worden doorkomstzaken voor gemeenten langs de route betrouwbaarder aangemaakt, en zijn er verbeteringen in hoe documenten en bestanden worden getoond. Bij doorkomstzaken verdwijnt de risicoclassificatie; dat staat apart beschreven onder "Wat is er weg of anders". Ook vraagt Eventloket nu je wachtwoord als je tweestapsverificatie met een app beheert.

---

## ✨ Nieuwe functies

### Meerdere soorten aanvragen op één zaaktype

**Voor wie:** Gemeentebeheerders, beheerders van een koppeling met een eigen zaaksysteem

Eventloket kent vier soorten aanvragen: evenementenvergunning, melding, vooraankondiging en doorkomst. Tot nu toe moest elke soort een eigen zaaktype in het zaaksysteem hebben, omdat Eventloket uit het zaaktype afleidde om wat voor aanvraag het ging. Nu legt Eventloket bij elke zaak zelf vast welk soort aanvraag het is. Een gemeente kan daardoor kiezen:

* meerdere soorten aanvragen koppelen aan hetzelfde zaaktype (tot en met één zaaktype voor alle vier), of
* zoals nu een eigen zaaktype per soort aanvraag houden.

Bij het instellen van de zaaktype-koppelingen geeft Eventloket geen foutmelding meer als een zaaktype al voor een andere soort aanvraag van dezelfde gemeente wordt gebruikt. Elke soort aanvraag koppel je nog steeds één keer per gemeente.

Wil het zaaksysteem de soorten aanvragen op een gedeeld zaaktype uit elkaar houden, dan kan het zaaktype een eigenschap `aanvraag_type` krijgen. Eventloket vult die dan met "Vergunning", "Melding", "Vooraankondiging" of "Doorkomst". Die eigenschap is niet verplicht en wordt alleen ingevuld als het zaaktype haar kent. Houd er bij de keuze voor een gedeeld zaaktype rekening mee dat het zaaktype één set statussen en resultaten heeft, en dat rechten in het zaaksysteem per zaaktype gelden. De handleiding voor het koppelingbeheer beschrijft dit verder.

Bestaande zaken hebben bij deze update hun soort aanvraag gekregen op basis van hun zaaktype. Voor organisatoren verandert er niets aan het aanvraagformulier.

### Het aanvraagtype in de overzichten

**Voor wie:** Organisatoren, gemeentemedewerkers, behandelaars, adviseurs en beheerders

Omdat een zaaktype nu voor meerdere soorten aanvragen kan staan, zegt het zaaktype niet meer altijd wat voor aanvraag een zaak is. De overzichten tonen daarom het aanvraagtype:

* In de lijst met zaken (en de lijstweergave van de kalender) staat een nieuwe kolom "Aanvraagtype". Je kunt erop sorteren en zoeken.
* Het filter op soort aanvraag in de lijst met zaken en in de kalender heet nu "Aanvraagtype". Ook de kalender van organisatoren heeft dit filter.
* De kolom en het filter "Gemeente" zie je nu in alle lijsten die zaken van meerdere gemeenten kunnen tonen, dus ook als organisator en als adviseur. Alleen in de lijst met zaken in de omgeving van een gemeente staan ze er niet, omdat je daar al binnen één gemeente werkt.
* De inbox met adviesvragen voor adviseurs toont het aanvraagtype.
* Op de pagina van een zaak staat het aanvraagtype naast het zaaktype.
* De CSV-export en de GeoJSON-export uit de kalender bevatten het aanvraagtype naast het zaaktype en de gemeente. Organisatoren hebben deze exports niet.

De kolom "Zaaktype" in de lijst met zaken is standaard verborgen. Zie daarvoor "Wat is er weg of anders".

### Meer gegevens in de GeoJSON-export

**Voor wie:** Gemeentebeheerders, adviseurs en beheerders

Elk evenement in de GeoJSON-export uit de kalender bevat nu ook:

* het resultaat van de zaak (leeg zolang de zaak nog geen resultaat heeft);
* het verwachte aantal aanwezigen, zoals de organisator dat heeft opgegeven;
* het aanvraagtype (zie hierboven).

Dit zijn gegevens die dezelfde gebruikers ook al via de zaakpagina en de CSV-export konden zien.

---

## 🐛 Opgeloste problemen

### Het aantal aanwezigen ging niet naar het zaaksysteem

**Voor wie:** Gemeenten, behandelaars

Bij een evenementenvergunning of een melding stuurde Eventloket de zaakeigenschap `aanwezigen` niet naar het zaaksysteem, omdat de waarde uit een vraag werd gehaald die alleen bij een vooraankondiging wordt gesteld. In Eventloket zelf stond het aantal wel. Bij een vooraankondiging was het andersom: het zaaksysteem kreeg het aantal wel, maar Eventloket bewaarde het niet bij de zaak.

Nu komen het aantal in het zaaksysteem en het aantal in Eventloket uit dezelfde vraag:

* bij een evenementenvergunning of melding het maximale aantal personen dat tegelijk aanwezig is;
* bij een vooraankondiging het verwachte aantal aanwezigen uit de eerste stap van het formulier.

De naam van de zaakeigenschap blijft `aanwezigen`, dus bestaande zaaktypen en koppelingen werken zonder aanpassing. Dit geldt voor aanvragen die na de update worden ingediend.

### Doorkomstzaken worden betrouwbaarder aangemaakt

**Voor wie:** Gemeenten langs de route, behandelaars

Gaat de route van een evenement door meerdere gemeenten, dan krijgt elke gemeente op de route een eigen doorkomstzaak. Daar is het volgende aan verbeterd:

* Gegevens van de hoofdzaak worden nu in de vorm gezet die het zaaktype van de ontvangende gemeente verwacht. Gebruikt dat zaaktype bijvoorbeeld een datum met tijd waar de hoofdzaak alleen een datum heeft, dan weigert het zaaksysteem de eigenschap niet meer.
* Weigert het zaaksysteem toch één eigenschap, dan wordt de rest van de doorkomstzaak gewoon aangemaakt: de overige eigenschappen, de initiatiefnemer, de documenten en de status. Voorheen stopte het aanmaken halverwege.
* Een nieuwe poging na een storing maakt geen tweede doorkomstzaak meer aan in het zaaksysteem van de ontvangende gemeente.
* Is een doorkomstzaak niet helemaal compleet geworden, bijvoorbeeld omdat een document niet kon worden gekopieerd, dan ziet het beheerteam dat, met het zaaknummer van de hoofdzaak en de onderdelen die ontbreken.

### Bestandsnamen in het aanvraagoverzicht

**Voor wie:** Organisatoren, gemeenten, behandelaars

In het PDF-overzicht van een ingediende aanvraag, en in de samenvatting aan het einde van het formulier, stonden bij geüploade bestanden onherkenbare namen (een lange reeks letters en cijfers). Daar staat nu de naam waaronder de organisator het bestand heeft geüpload. PDF-overzichten die al zijn gemaakt, worden niet opnieuw gemaakt.

### Opbouw- en afbouwdatums na het verschuiven van het evenement

**Voor wie:** Organisatoren

In de stap over de tijden konden de datumkiezers voor de opbouw en de afbouw vastlopen als je de datum van het evenement verschoof terwijl de opbouw of afbouw al was ingevuld. Dat gebeurde vooral bij een kopie van een eerdere aanvraag, omdat alle datums dan meekomen. Je kon dan geen nieuwe datum meer kiezen, of bij de afbouw zelfs helemaal geen dag, tenzij je eerst het andere veld van die periode aanpaste.

De datumkiezers hangen nu alleen nog af van het evenement en van de datum van vandaag. Dat de opbouw of afbouw begint vóór hij eindigt, wordt nog steeds gecontroleerd bij het opslaan, met dezelfde melding als voorheen.

### Een duidelijke melding als een nieuwe versie van een document niet lukt

**Voor wie:** Iedereen die documenten bij een zaak toevoegt (organisatoren, gemeentemedewerkers, behandelaars en adviseurs)

Weigerde het zaaksysteem een nieuwe versie van een document, via "Nieuwe versie" in de lijst met documenten of in een bericht, dan kreeg je een algemene foutmelding. Je ziet nu de melding "Nieuwe versie niet opgeslagen" met uitleg:

* Geeft het zaaksysteem geen toestemming voor een nieuwe versie, dan staat dat in de melding. Als organisator vragen we je dan contact op te nemen met de gemeente. Andere gebruikers vragen we de beheerder van de zaaksysteemkoppeling dit na te laten kijken.
* Lukt het om een andere reden niet, dan vragen we je het later opnieuw te proberen, en contact op te nemen met de beheerder als het blijft gebeuren.

Het bestand dat je probeerde te uploaden wordt in beide gevallen niet bewaard. Voor beheerders van de koppeling staat onder "Wat moet je doen?" welke rechten hiervoor nodig zijn.

### Een collega ontkoppelen in de instellingen van een adviesdienst

**Voor wie:** Beheerders van een adviesdienst

In de instellingen van een adviesdienst gaf het ontkoppelen van een gebruiker uit de gebruikerslijst een foutmelding, zowel voor één gebruiker als voor meerdere tegelijk. Dat werkt nu weer. Net als voorheen kun je alleen gebruikers ontkoppelen die ook nog bij een andere adviesdienst horen, zodat niemand zonder adviesdienst achterblijft. Kies je meerdere gebruikers en horen er een paar alleen bij deze adviesdienst, dan worden de anderen ontkoppeld en zie je een melding hoeveel gebruikers zijn overgeslagen, omdat zij alleen bij deze adviesdienst horen.

---

## 📋 Wat is er weg of anders

### Geen risicoclassificatie meer bij doorkomstzaken

**Voor wie:** Gemeenten langs de route, behandelaars, adviseurs

De risicoclassificatie wordt beoordeeld voor het evenement op de hoofdzaak. Een doorkomstzaak gaat alleen over het deel van de route door een andere gemeente, dus de classificatie hoort daar niet bij. Voor doorkomstzaken verandert daarom het volgende:

* Op de pagina van een doorkomstzaak, ook een bestaande, staat de risicoclassificatie niet meer, niet bij de gegevens en niet bij de acties.
* De actie om de risicoclassificatie en de toelichting daarop te wijzigen, is bij een doorkomstzaak verdwenen.
* Een nieuwe doorkomstzaak krijgt de risicoclassificatie en de toelichting van de hoofdzaak niet meer mee als zaakeigenschap.
* Een nieuwe doorkomstzaak krijgt daardoor ook niet meer automatisch de concept-adviesvragen, want die worden afgeleid van de risicoclassificatie.

Bestaande doorkomstzaken houden de classificatie die ze al hebben, in het zaaksysteem en in Eventloket. In de lijst met zaken, de kalender en de export kun je bij zo'n bestaande doorkomstzaak dus nog een classificatie zien. Voor evenementenvergunningen, meldingen en vooraankondigingen verandert er niets.

### De kolom "Zaaktype" is standaard verborgen en het filter heet "Aanvraagtype"

**Voor wie:** Organisatoren, gemeentemedewerkers, behandelaars, adviseurs en beheerders

In de lijst met zaken staat de kolom "Zaaktype" niet meer standaard aan; de nieuwe kolom "Aanvraagtype" vertelt nu welk soort aanvraag een zaak is. Wil je het zaaktype toch zien, zet de kolom dan weer aan via de kolomkeuze boven de lijst.

Het filter dat "Zaaktype" heette, in de lijst met zaken en in de kalender, heet nu "Aanvraagtype". Je kiest er dezelfde soorten aanvragen mee als voorheen.

### Besluitdocumenten in bewerking en vervallen documenten blijven uit beeld

**Voor wie:** Iedereen die zaken bekijkt (organisatoren, gemeentemedewerkers, behandelaars en adviseurs)

* Op het tabblad met besluiten staan alleen nog documenten die zijn vastgesteld. Een besluitdocument dat in het zaaksysteem nog in bewerking is of ter vaststelling ligt, zie je daar niet meer. Dat was bij de gewone documenten van een zaak al zo. Of een besluit zelf wordt getoond, verandert niet.
* Een document waarvan het zaaksysteem aangeeft dat de inhoud is vervallen, wordt niet meer getoond, ook niet als het definitief is. Je krijgt over zo'n document ook geen melding meer. Een besluit waarvan het enige document is vervallen, blijft wel zichtbaar, zonder dat document.
* Verwijst een bericht naar een document dat je niet (meer) kunt zien, dan staat daar nu "Dit document is niet meer beschikbaar". Voorheen kon het bericht dan niet worden getoond.

### Nieuwe naam voor de instelling die het blok Acties verbergt

**Voor wie:** Beheerders van een koppeling met een eigen zaaksysteem

De instelling "Status niet wijzigbaar door behandelaar" bij een zaaksysteemkoppeling heet nu "Het blok Acties verbergen". De instelling verbergt het hele blok Acties op een zaak, dus ook het aanpassen van de risicoclassificatie, het afronden van de zaak en het invullen van het interne zaaknummer, en niet alleen de status. De nieuwe naam en uitleg zeggen dat nu. Wat de instelling doet, is niet veranderd.

---

## 🔒 Beveiliging

### Je wachtwoord bij het beheren van tweestapsverificatie met een app

**Voor wie:** Iedereen die inlogt

Wil je op je profielpagina tweestapsverificatie met een authenticator-app instellen, uitzetten of nieuwe herstelcodes maken, dan vraagt Eventloket nu eerst je huidige wachtwoord. Voorheen werd daar niet om gevraagd. Het inloggen zelf is niet veranderd.

### Overige beveiliging

**Voor wie:** Iedereen, maar er is niets zichtbaars aan

* Een aantal onderdelen waarop Eventloket draait, is bijgewerkt naar een versie met beveiligingsoplossingen.
* De gebruikerslijst in de instellingen van een adviesdienst hoort bij de adviesdienst die je op dat moment bekijkt: alleen beheerders van die adviesdienst kunnen hem openen.

---

## 📱 Wat moet je doen?

### Voor organisatoren

**Niets.** Krijg je een melding dat een nieuwe versie van een document niet is opgeslagen, neem dan contact op met de gemeente.

### Voor gemeenten, behandelaars en adviseurs

**In de meeste gevallen niets.** Mis je de kolom "Zaaktype" in de lijst met zaken, zet die dan weer aan via de kolomkeuze.

Werk je met doorkomstzaken en gebruikte je de risicoclassificatie of de concept-adviesvragen daarvan? Die komen bij nieuwe doorkomstzaken niet meer automatisch mee. Kijk daarvoor naar de hoofdzaak van het evenement.

Lees je de CSV- of GeoJSON-export in een eigen programma in, bijvoorbeeld een kaartprogramma? In de CSV-export staat de nieuwe kolom "Aanvraagtype" tussen "Identificatie" en "Zaaktype", dus de kolommen daarna schuiven één plek op. De GeoJSON-export heeft drie nieuwe velden: `aanvraagtype`, `resultaat` en `aantal_aanwezigen`. Kijk dus na de update even na of je programma de juiste kolommen en velden gebruikt.

### Voor gemeentebeheerders en beheerders van een koppeling met een eigen zaaksysteem

* Wil je meerdere soorten aanvragen op één zaaktype laten binnenkomen, dan kan dat vanaf deze versie in de zaaktype-koppelingen. Wil je ze in het zaaksysteem uit elkaar houden, voeg dan de eigenschap `aanvraag_type` aan het zaaktype toe. Hoeft er niets te veranderen, dan hoef je niets te doen.
* Voor een nieuwe versie van een document heeft de koppeling in de Documenten API de rechten `documenten.lock` en `documenten.bijwerken` nodig, naast `documenten.aanmaken`, voor elk informatieobjecttype van de koppeling. Zonder die rechten zien gebruikers de melding dat het zaaksysteem geen toestemming geeft. De handleiding voor het koppelingbeheer (paragraaf 4.3) beschrijft welke rechten per onderdeel nodig zijn.

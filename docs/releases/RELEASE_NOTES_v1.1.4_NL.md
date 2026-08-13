# Eventloket versie 1.1.4: wat is er nieuw?

**Releasedatum:** 12 augustus 2026

---

Deze versie is een onderhoudsrelease met drie opgeloste problemen. Organisatoren die een route intekenen die door meerdere gemeenten loopt, kwamen niet verder dan de locatiestap. Een gekopieerde aanvraag kon bij de verkeerde gemeente terechtkomen. En medewerkers die vanaf hetzelfde kantoornetwerk inloggen, konden elkaar per ongeluk buitensluiten. Daarnaast is een externe softwarecomponent bijgewerkt. Er zijn geen nieuwe functies en er verandert niets aan de manier van werken.

---

## 🐛 Opgeloste problemen

### Route door meerdere gemeenten bleef vragen om een gemeentekeuze

**Voor wie:** Organisatoren

Tekende een organisator een route die in dezelfde gemeente begint en eindigt maar onderweg door een andere gemeente loopt, dan was de locatiestap niet te passeren. Na het kiezen van de gemeente en het klikken op "Volgende" verscheen de melding "Kies een gemeente" en was de gemaakte keuze weer leeg. Wie het opnieuw probeerde, kreeg precies hetzelfde. Routes die in een andere gemeente eindigen dan waar ze beginnen hadden dit probleem niet, waardoor het leek alsof de fout willekeurig optrad.

Juist rondgaande routes komen veel voor bij optochten, processies en wandeltochten, dus een groot deel van de route-aanvragen liep hier tegenaan.

De gemeentekeuze wordt nu alleen nog gewist wanneer die keuze echt niet meer klopt, namelijk als de gemeente niet langer bij de ingetekende route hoort. Verplaatst de organisator de route dus weg van de gekozen gemeente, dan wordt om een nieuwe keuze gevraagd. Blijft de keuze geldig, dan blijft die staan en kan de organisator door naar de volgende stap. In plaats van de onduidelijke melding "Gemeente niet bepaald" wordt nu netjes om een nieuwe keuze gevraagd.

Bij deze verbetering is ook een toelichting weer zichtbaar geworden die door een fout nooit werd getoond. Bij een route door twee of meer gemeenten leest de organisator nu welke gemeenten de route doorkruist en dat de overige gemeenten automatisch worden geïnformeerd.

---

### Gekopieerde aanvraag kwam bij de verkeerde gemeente terecht

**Voor wie:** Organisatoren en gemeenten

Kopieerde een organisator een eerder evenement en tekende daarna een route door andere gemeenten, dan werd de nieuwe aanvraag alsnog bij de gemeente van het oorspronkelijke evenement ingediend. Welke gemeente de organisator ook koos, de aanvraag belandde bij de verkeerde gemeente en dus ook bij de verkeerde behandelaars.

De oorzaak zat in twee dingen die op elkaar ingrepen. Bij het kopiëren werden ook de locatiegegevens van het oorspronkelijke evenement meegenomen, zonder die opnieuw te controleren tegen de nieuwe locatie. En bij het versturen van de aanvraag werd de uitkomst van de locatiecontrole teruggezet naar de stand van het moment waarop de pagina werd geopend, waardoor de gemeente van het oude evenement weer de bovenhand kreeg.

Wat er nu gebeurt:

* Bij het kopiëren van een aanvraag worden de locatiegegevens en de bijbehorende gemeentegegevens niet meer meegenomen. De locatiestap bepaalt ze opnieuw op basis van de nieuwe locatie.
* De uitkomst van de locatiecontrole wordt niet meer door de browser meegestuurd, maar volledig door de server bijgehouden. Dat lost het terugzetten op en sluit tegelijk de mogelijkheid af om via aangepaste gegevens uit de browser een aanvraag naar een andere gemeente te sturen.
* Wordt bij het versturen alsnog een gemeente aangetroffen die niet bij de ingevulde locatie past, dan wordt de aanvraag niet aangemaakt. De organisator krijgt een melding die naar de locatiestap verwijst, in plaats van een algemene foutmelding of, erger, een aanvraag bij de verkeerde gemeente.
* Bij het aanpassen van een adres wordt de eerder gevonden gemeente eerst losgelaten, zodat een mislukte adrescontrole niet stilletjes de oude gemeente laat staan.

Dit probleem trof niet alleen kopieën. Ook een concept dat later werd hervat en waarbij de locatie tussentijds veranderde, kon bij de eerder gevonden gemeente terechtkomen. Dat is met dezelfde aanpassing verholpen.

**Let op voor gemeenten:** aanvragen die vóór deze update bij de verkeerde gemeente zijn aangemaakt, verhuizen niet automatisch mee. Komt u zo'n aanvraag tegen, neem dan contact op met de organisator of met de beheerder.

---

### Inloggen vanaf een gemeenschappelijk kantoornetwerk sloot collega's buiten

**Voor wie:** Iedereen die inlogt, in de praktijk vooral gemeenten en adviesdiensten

Kantoren delen vaak één internetverbinding naar buiten. Voor Eventloket lijken alle medewerkers van zo'n kantoor daardoor van dezelfde plek te komen. De beveiliging tegen het raden van wachtwoorden hield dat aantal pogingen per plek bij en telde daarbij elke inlogpoging mee, ook de geslaagde. Een tweestapsverificatie werd zelfs dubbel geteld. Het gevolg: nadat een paar collega's achter elkaar hadden ingelogd, was de teller vol en kon niemand op dat kantoor nog inloggen, ook al had niemand iets verkeerd gedaan.

Er wordt nu alleen nog geteld wat daadwerkelijk mis is gegaan. Correct inloggen kost geen pogingen meer, en de tweede stap van een tweestapsverificatie ook niet. Verder wordt er niet alleen op locatie geteld, maar ook per account, zodat een collega die zich vertypt alleen zichzelf raakt en niet de rest van het kantoor.

Wat dit in de praktijk betekent:

* Meerdere collega's die achter elkaar gewoon inloggen: geen enkele beperking meer.
* Een collega die zijn wachtwoord een paar keer verkeerd intypt: die medewerker moet even wachten, de rest van het kantoor kan door.
* Iemand die van buitenaf wachtwoorden probeert te raden: wordt nog steeds tegengehouden, en sneller gericht op het aangevallen account in plaats van op het hele netwerk.

Ook is een tweede probleem opgelost dat bij de tweestapsverificatie speelde: die teller werd nooit opgeschoond, waardoor iemand die kort na elkaar een paar keer normaal inlogde zichzelf uit zijn eigen account kon werken.

De bescherming tegen het raden van wachtwoorden is met deze wijziging niet losser geworden, maar gerichter.

---

## 🔒 Beveiliging

**Voor wie:** Iedereen, maar er is niets zichtbaars aan

Een externe softwarecomponent die Eventloket gebruikt voor het opmaken van e-mailberichten, is bijgewerkt naar een versie zonder bekende kwetsbaarheden.

De meldingen zijn nagelopen tegen de code van Eventloket. Het onderdeel wordt alleen gebruikt bij het versturen van e-mail en niet bij het tonen van pagina's, en de gegevens die erdoorheen gaan zijn kort van lengte. Er is dus geen aanleiding om aan te nemen dat dit in Eventloket misbruikt kon worden. De update is uitgevoerd als normaal onderhoud. Aan de werking van de applicatie verandert niets.

---

## 📱 Wat moet je doen?

**Niets!** De verbeteringen werken automatisch na de update.

Wel goed om te weten voor gemeenten: is er eerder een aanvraag bij de verkeerde gemeente binnengekomen doordat de organisator een evenement had gekopieerd, dan blijft die aanvraag daar staan. Zie de opmerking hierboven.

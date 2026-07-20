# Eventloket Versie 1.1.0 — Wat is er nieuw?

**Releasedatum:** 16 juli 2026

---

Deze versie draait om beter samenwerken binnen de gemeente en slimmer documentbeheer. Gemeenten kunnen voortaan een coördinator aanwijzen die nieuwe zaken verdeelt, organisatoren zien direct of hun aanvraag op tijd is ingediend, en documenten kunnen in bulk worden geüpload en gedownload. Daarnaast onthoudt de applicatie je tabelinstellingen en is een flinke reeks problemen in het aanvraagformulier opgelost. Hieronder lees je per onderdeel wat er verandert.

---

## ✨ Nieuwe functionaliteit

### Indieningstermijn per gemeente en risicoclassificatie

**Voor wie:** Organisatoren, Gemeentebeheerders

Gemeenten kunnen per risicoklasse (A, B of C) een indieningstermijn in weken instellen. Vul je als organisator het aanvraagformulier in, dan zie je na de risicoscan en op de samenvatting direct of je aanvraag binnen de termijn valt: een groene melding als je op tijd bent, een oranje melding als de termijn al is verstreken. De termijnstatus wordt ook opgenomen in de PDF van de aanvraag. Heeft een gemeente geen termijn ingesteld, dan verandert er voor die gemeente niets.

---

### Nieuwe rol: coördinator

**Voor wie:** Gemeentebeheerders, Gemeentemedewerkers, Behandelaars

Gemeenten kunnen behandelaars de nieuwe rol coördinator geven. De coördinator ontvangt de meldingen van nieuwe zaken en wijst deze via de nieuwe actie "Behandelaar toewijzen" toe aan een behandelaar. De toegewezen behandelaar krijgt hiervan een notificatie. Is er geen coördinator, dan werkt het zoals voorheen en ontvangen alle behandelaars de melding. Een coördinator kan ook zichzelf als behandelaar toewijzen. Coördinatoren zijn zichtbaar en te beheren in de behandelaarslijst van de gemeente, zowel voor gemeentebeheerders als voor platformbeheerders.

---

### Meerdere documenten tegelijk uploaden en downloaden

**Voor wie:** Organisatoren, Gemeentemedewerkers, Behandelaars, Adviseurs

Je kunt nu meerdere documenten in één keer aan een zaak toevoegen, via slepen of multi-select. Per document geef je een titel op en voor de hele selectie kies je één documenttype. Ook downloaden gaat sneller: selecteer meerdere documenten en download ze als ZIP-bestand. Bij drie documenten of minder start de download direct; bij grotere selecties wordt het ZIP-bestand op de achtergrond klaargezet en ontvang je een notificatie met een downloadlink.

---

### Kleuren in de kalender per status en resultaat

**Voor wie:** Platformbeheerders, alle gebruikers met toegang tot de kalender

Kalenderitems krijgen een kleur op basis van de status en het resultaat van de zaak, zodat je in één oogopslag ziet hoe zaken ervoor staan. Platformbeheerders kunnen per combinatie van status en resultaat een kleur instellen. Er wordt een standaardset kleuren meegeleverd voor de meest voorkomende statussen en resultaten. De kleuren worden ook meegenomen in de export van evenementen.

---

### Intern zaaknummer

**Voor wie:** Gemeentemedewerkers, Behandelaars

Gemeenten kunnen per zaak een eigen intern zaaknummer vastleggen, bewerken en verwijderen. Het interne zaaknummer is zichtbaar als sorteerbare en doorzoekbare kolom in het zakenoverzicht en wordt automatisch gesynchroniseerd naar OpenZaak.

---

### Tabelinstellingen worden onthouden

**Voor wie:** Alle gebruikers

Filters, sortering, zoekopdrachten en kolomkeuzes in tabellen worden voortaan per gebruiker bewaard. Log je uit of verloopt je sessie, dan staan je tabellen bij de volgende keer inloggen weer zoals je ze had achtergelaten.

---

### GPX-routebestanden uploaden

**Voor wie:** Organisatoren

Organiseer je een optocht, wandeling of ander evenement met een route? Dan kun je nu een GPX-routebestand uploaden bij je aanvraag. De inhoud van het bestand wordt gecontroleerd, zodat alleen geldige routebestanden worden geaccepteerd. Alle uploadvelden in het aanvraagformulier zijn daarnaast extra beveiligd: bestanden behouden hun oorspronkelijke bestandsnaam en zijn alleen toegankelijk voor wie daar recht op heeft.

---

## 🐛 Opgeloste problemen

### Nieuwe documentversies alleen door de juiste partij

**Voor wie:** Organisatoren, Gemeentemedewerkers, Behandelaars, Adviseurs

Een nieuwe versie van een document kan voortaan alleen worden toegevoegd door iemand van dezelfde partij (organisatie, gemeente of adviesdienst) die de eerste versie heeft aangeleverd. Voor het aanvraagformulier zelf en documenten zonder eigenaar kan alleen de platformbeheerder nieuwe versies toevoegen. De versiekolom is voor organisatoren verborgen.

---

### Adres opzoeken in het formulier verbeterd

**Voor wie:** Organisatoren

Het opzoeken van een adres bij de locatiestap werkt nauwkeuriger en rustiger. Het gevonden adres wordt nu correct getoond in de samenvatting, de zoekopdracht wacht tot je klaar bent met typen en de adresherkenning geeft exactere resultaten terug.

---

### Betrouwbaardere gemeentebepaling op de kaart

**Voor wie:** Organisatoren

De bepaling in welke gemeente je evenement plaatsvindt, is betrouwbaarder en sneller geworden. De gemeente wordt op één vaste plek in het formulier definitief vastgesteld en de controle wordt niet vaker uitgevoerd dan nodig.

---

### Formulier crasht niet meer op ongeldige invoer

**Voor wie:** Organisatoren

Twee situaties waarin het aanvraagformulier kon vastlopen zijn opgelost: het invullen van een onvolledige of ongeldige datum en het intekenen van een ongeldige vorm op de kaart worden nu netjes afgehandeld.

---

### "Mijn omgeving" niet meer vooringevuld als organisatienaam

**Voor wie:** Organisatoren

Bij aanvragen op persoonlijke titel werd "Mijn omgeving" soms onbedoeld ingevuld als organisatienaam. Dat gebeurt niet meer.

---

### Ontbrekende vertalingen aangevuld

**Voor wie:** Gemeentemedewerkers, Behandelaars

In het zaakoverzicht ontbraken enkele Nederlandse teksten, onder andere bij het interne zaaknummer, de locaties en gerelateerde zaken. Deze zijn aangevuld.

---

### Zoeken in de gebruikerslijst van een adviesdienst werkt weer

**Voor wie:** Platformbeheerders, Adviseurs

Zoeken naar een gebruiker binnen een adviesdienst gaf een foutmelding. Dit is opgelost; zoeken op naam werkt weer in alle panelen.

---

### Info-icoontjes ogen weer als hint

**Voor wie:** Organisatoren

De info-icoontjes bij formuliervragen waren zo donker dat ze op geselecteerde opties leken. Ze zijn lichter gemaakt, zodat duidelijk is dat het om een toelichting gaat.

---

### Crash bij concept-adviesaanvragen opgelost

**Voor wie:** Gemeentemedewerkers, Behandelaars

Bij een net aangemaakte zaak die nog geen status uit het ZGW-register had, kon het aanmaken van een concept-adviesaanvraag vastlopen. Dat is verholpen.

---

## 📱 Wat moet je doen?

### Voor gemeentebeheerders

Wil je gebruikmaken van de indieningstermijn-melding? Stel dan per risicoklasse de termijn in weken in via de gemeente-variabelen (indieningstermijn A, B en C). Zolang je niets instelt, blijft alles werken zoals voorheen.

Wil je met een coördinator werken? Wijzig dan de rol van de betreffende behandelaar naar "Coördinator" in het behandelaarsoverzicht. Nieuwe zaken worden vanaf dat moment bij de coördinator gemeld in plaats van bij alle behandelaars.

### Voor platformbeheerders

De kalenderkleuren worden geleverd met een standaardset. Controleer of deze past en pas de kleuren per status en resultaat aan waar gewenst.

### Voor organisatoren, gemeentemedewerkers en adviseurs

**Niets!** Alle verbeteringen werken automatisch.

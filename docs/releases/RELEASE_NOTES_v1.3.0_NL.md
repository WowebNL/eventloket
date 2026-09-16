# Eventloket versie 1.3.0: wat is er nieuw?

**Releasedatum:** 9 september 2026

---

Deze versie brengt één grote nieuwe functie en een paar kleinere verbeteringen. Een gemeente kan Eventloket voortaan koppelen aan het eigen zaaksysteem in plaats van alleen aan de gedeelde omgeving. Verder is een sorteerprobleem in het zakenoverzicht opgelost, is het aanvraagformulier steviger gemaakt tegen een extreem lange periode, en zijn twee externe softwarecomponenten bijgewerkt naar een veilige versie. Werk je met het centrale zaaksysteem, dan verandert er in de praktijk niets aan de manier van werken.

---

## ✨ Nieuwe functies

### Een gemeente kan Eventloket aan het eigen zaaksysteem koppelen

**Voor wie:** Gemeenten

Tot nu toe schreef Eventloket alle zaken weg naar één centraal zaaksysteem dat voor alle aangesloten gemeenten hetzelfde was. Een gemeente die met een eigen zaaksysteem werkt, kon daar niet rechtstreeks op aansluiten. Vanaf deze versie kan dat wel. Een gemeente kan Eventloket koppelen aan het eigen zaaksysteem, zodat evenementaanvragen en de bijbehorende documenten in de eigen zaakadministratie terechtkomen in plaats van in de gedeelde omgeving. Voor elk soort zaak wordt automatisch de juiste verbinding gebruikt.

Om dit te beheren is er een nieuwe rol bijgekomen: de koppelingbeheerder. Een gemeentebeheerder kan iemand voor die rol uitnodigen. De koppelingbeheerder krijgt in de instellingen drie nieuwe schermen:

* Een scherm om de koppeling met het eigen zaaksysteem in te stellen (de adressen van het systeem, de inloggegevens en enkele technische instellingen). Per gemeente is er één koppeling.
* Een scherm om per soort zaak aan te geven welk zaaktype in het eigen systeem daarbij hoort, en welke velden en statussen Eventloket daarvoor moet gebruiken. De keuzelijsten worden rechtstreeks uit het gekoppelde systeem opgehaald, zodat je kiest uit wat er echt is.
* Een overzicht (alleen-lezen) van het berichtenverkeer tussen Eventloket en het zaaksysteem, waarmee een beheerder kan controleren of de koppeling werkt. Daarin worden alleen technische gegevens bewaard (welke actie, op welk moment en of die slaagde), geen inhoud van aanvragen en geen persoonsgegevens. Deze overzichten worden na 90 dagen automatisch opgeschoond.

Werkt een gemeente niet met een eigen zaaksysteem, dan verandert er niets. Eventloket blijft dan gewoon het centrale zaaksysteem gebruiken, precies zoals voorheen. Ook voor organisatoren verandert er niets aan het indienen van een aanvraag.

---

## 🐛 Opgeloste problemen

### Zaken zonder zaaknummer stonden bovenaan in het overzicht

**Voor wie:** Gemeentemedewerkers, Behandelaars, Adviseurs

In het zakenoverzicht kun je sorteren op zaaknummer. Zaken die nog geen zaaknummer hadden, kwamen bij aflopend sorteren bovenaan te staan in plaats van onderaan. De eerste pagina vulde zich daardoor met die zaken en de sortering leek kapot. Zaken zonder zaaknummer staan nu in beide sorteerrichtingen netjes onderaan de lijst.

---

### Aanvraagformulier liep vast bij een extreem lange periode

**Voor wie:** Organisatoren

Vulde een organisator bij het evenement, de opbouw of de afbouw een einddatum in die heel ver in de toekomst lag, dan kon de pagina vastlopen. Het formulier probeerde namelijk voor elke afzonderlijke dag een regel te maken en bleef dat bij zo'n lange periode eindeloos doen.

De periode is nu begrensd op maximaal 90 dagen. Vult iemand een langere periode in, dan verschijnt een duidelijke melding waarom die niet wordt geaccepteerd, in plaats van een vastgelopen scherm. Negentig dagen is ruim genoeg voor langlopende evenementen, zoals een tijdelijke schaatsbaan of een kermis die weken duurt.

---

## 🔒 Beveiliging

**Voor wie:** Iedereen, maar er is niets zichtbaars aan

Twee externe softwarecomponenten die Eventloket gebruikt, zijn bijgewerkt naar een versie zonder bekende kwetsbaarheden.

De eerste component zit achter het inlogscherm en verzorgt onder meer de tweestapsverificatie. De update bevat beveiligingsverbeteringen die de tweede stap van het inloggen steviger maken. Het gaat om randgevallen in dat proces. De update is uitgevoerd als voorzorg en als normaal onderhoud.

De tweede component gebruikt Eventloket voor het opmaken van e-mailberichten. Die is bijgewerkt naar een nieuwere versie. Het onderdeel wordt alleen gebruikt bij het versturen van e-mail en niet bij het tonen van pagina's, dus er is geen aanleiding om aan te nemen dat dit in Eventloket misbruikt kon worden.

Aan de werking van de applicatie verandert door beide updates niets.

---

## 📱 Wat moet je doen?

### Voor gemeenten

Wil je Eventloket koppelen aan je eigen zaaksysteem? Neem contact op met een platformbeheerder om de koppeling in te richten en een koppelingbeheerder aan te wijzen. Werk je met het centrale zaaksysteem, dan hoef je niets te doen en blijft alles werken zoals voorheen.

### Voor organisatoren, behandelaars en adviseurs

**Niets!** De verbeteringen en de beveiligingsupdates werken automatisch na de update.

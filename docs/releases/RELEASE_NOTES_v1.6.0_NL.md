# Eventloket versie 1.6.0: wat is er nieuw?

**Releasedatum:** nog in te vullen

---

Deze versie brengt de archiefmodule: gemeenten kunnen zaken vernietigen volgens de Archiefwet, met een beoordeling en een permanent vernietigingsrapport, en Eventloket ruimt zijn eigen gegevens over een zaak op zodra het zaaksysteem meldt dat die zaak vernietigd is. Organisatoren kunnen een GPX-bestand van hun route uploaden, en een melding krijgt een eigen indieningstermijn. Daarnaast is de ontvangst van notificaties uit het zaaksysteem robuuster en beter bewaakt. Een paar dingen zijn verdwenen of anders; die staan hieronder als eerste.

---

## 🔄 Wat is er weg of anders

### Dubbele uitkomst in de scan verdwijnt

**Voor wie:** Organisatoren

Beantwoordde je in de scan de vraag over het afsluiten van wegen met "Nee" terwijl er elders in de scan ook een "Nee" stond, dan toonde de scan tegelijk "u heeft een vergunning nodig" en "een melding volstaat". Nu blijft alleen de eerste staan. De stappen daarna gingen al uit van een vergunningsaanvraag; daar verandert niets.

### Termijnen in de datumstap volgen het pad

**Voor wie:** Organisatoren

De stap waarin je de datum van het evenement opgeeft, toonde altijd de drie indieningstermijnen van de risicoklassen A, B en C. Wie een melding doet, ziet die drie termijnen niet meer, maar de termijn voor een melding (als de gemeente die heeft ingesteld). Wie een vergunning aanvraagt, ziet alleen de termijnen van de risicoklassen. Zolang de scan nog niet klaar is, staan alle ingestelde termijnen er. Een vooraankondiging ziet hetzelfde als voorheen.

### Knop "Abonnement registreren" verdwijnt bij een gedeelde Notificaties-omgeving

**Voor wie:** Koppelingbeheerders, beheerders van Eventloket

Per Notificaties API heeft Eventloket voortaan maar één abonnement. Laat je bij een koppeling de Notificaties API-URL leeg, of vul je dezelfde URL in als bij de hoofdkoppeling, of staat er op die Notificaties API al een abonnement van een andere koppeling, dan toont de verbindingstest geen knop **Abonnement registreren** meer. In plaats daarvan staat er een waarschuwing dat de meldingen binnenkomen via het bestaande abonnement. De verbindingstest slaagt dan wel, dus de koppeling kan gewoon geactiveerd worden. OneGround-koppelingen met een eigen client-ID houden elk hun eigen abonnement. Staat hetzelfde abonnement bij twee koppelingen vastgelegd, dan mislukt de stap en vraagt hij je contact op te nemen met de beheerder.

### Documenten van buiten de toegestane adressen worden geweigerd

**Voor wie:** Behandelaars, beheerders van Eventloket

Verwijst het zaaksysteem bij het openen van een document of het maken van een zip door naar een adres dat niet bij de koppeling hoort, dan volgt Eventloket die doorverwijzing niet meer en mislukt het openen.

### Bevestiging bij vernietiging noemt de volgorde

**Voor wie:** Archiefcoördinatoren

De bevestiging voordat een vernietigingslijst wordt uitgevoerd luidt: "Alle zaken op deze lijst worden permanent vernietigd in OpenZaak. Daarna worden de bijbehorende Eventloket-gegevens permanent verwijderd, zodra het zaaksysteem bevestigt dat de zaak is vernietigd." De bijzin "inclusief documenten, besluiten en berichten" is weg.

### Inactieve organisatoraccounts worden geanonimiseerd

**Voor wie:** Organisatoren, beheerders van Eventloket

Eens per maand anonimiseert Eventloket organisatoraccounts die geen zaken meer hebben en langer dan 24 maanden niet hebben ingelogd. Naam, e-mailadres en telefoonnummer worden dan gewist. Dat geldt ook voor eerder verwijderde accounts. De eerste keer is op de eerste dag van de maand na de uitrol, om 03:00.

---

## ✨ Nieuw

### Archiefmodule: zaken vernietigen volgens de Archiefwet

**Voor wie:** Gemeenten (archiefcoördinator, archiefbeoordelaar, beheerder)

- Twee nieuwe rollen in het gemeenteportaal: **Archiefcoördinator** en **Archiefbeoordelaar**. De beheerder van de gemeente nodigt ze uit via **Instellingen**.
- Een nieuw onderdeel **Archief**, alleen voor die twee rollen.
- De coördinator stelt een vernietigingslijst samen uit zaken die volgens het zaaksysteem vernietigd mogen worden (archiefnominatie "vernietigen" en een verstreken archiefactiedatum) en biedt hem aan ter beoordeling. De beoordelaar geeft akkoord of vraagt om wijzigingen. Daarna bevestigt de coördinator de vernietiging.
- Na de vernietiging volgt een permanent vernietigingsrapport (PDF), met een eigen batchnummer, de datum en wijze van vernietiging, de coördinator en per zaak de grondslag en wat er met het formulierinzending-object is gebeurd.
- Vernietigen gebeurt alleen voor zaken op het zaaksysteem van Eventloket zelf. Een gemeente met een eigen zaaksysteem ziet wel de rapporten, maar kan geen vernietigingslijst maken; zij vernietigt in haar eigen systeem.
- Archiefgebruikers zien de inhoud van documenten ten minste tot en met vertrouwelijkheid Confidentieel, ook als de koppeling dat voor andere gemeentegebruikers lager instelt.

### Eventloket ruimt zijn eigen gegevens op na vernietiging

**Voor wie:** Gemeenten

Meldt het zaaksysteem dat een zaak is vernietigd, dan verwijdert Eventloket wat het zelf over die zaak bewaart: berichten en gesprekken, notificaties, het logboek en het formulierinzending-object. Eventloket controleert eerst bij het zaaksysteem dat de zaak er echt niet meer is. Elke nacht bundelt Eventloket deze opruimingen per gemeente in een eigen vernietigingsrapport. Voor het zaaksysteem van Eventloket zelf is er bovendien een nachtelijke controle voor het geval een melding niet aankomt.

Gemeenten waarvan de meldingen via een gedeelde Notificaties-omgeving binnenkomen die geen OneGround is, krijgen in deze versie nog geen automatische opruiming van de Eventloket-gegevens na vernietiging in hun eigen zaaksysteem. Die gegevens blijven tot een volgende versie staan. Vernietiging via de archiefmodule op de eigen OpenZaak werkt wel volledig.

### Route uploaden als GPX-bestand

**Voor wie:** Organisatoren

Bij de vraag naar de route kun je een GPX-bestand uploaden. De route verschijnt meteen op de kaart en blijft daar te bewerken; je hoeft hem niet meer na te tekenen. Eventloket bepaalt met die route ook door welke gemeenten het evenement gaat, net als bij een getekende route.

### Indieningstermijn voor een melding

**Voor wie:** Gemeenten, organisatoren

Een gemeente kan nu een indieningstermijn voor meldingen instellen, op dezelfde plek als de termijnen van de risicoklassen (variabele "Indieningstermijn melding (weken)", standaard 0, dus niet ingesteld). Wie een melding doet, ziet dan of hij op tijd is, net zoals bij een vergunningsaanvraag. Ook de samenvatting en het inzendingsbewijs van een melding tonen die termijn.

### Vooraankondiging omzetten: eigen vragen van de gemeente blijven ingevuld

**Voor wie:** Organisatoren

Zet je een vooraankondiging om naar een definitieve aanvraag, dan zijn de antwoorden op de aanvullende vragen van de gemeente al ingevuld, zolang de gemeente die vragen ook bij de vergunning stelt.

---

## 🛠️ Verbeterd

- **Kaart in samenvatting en inzendingsbewijs.** Een kaart in de samenvatting of het inzendingsbewijs toont geen half gevulde achtergrondkaart meer: is de kaart niet volledig op te halen, dan verschijnt hij op de effen achtergrond. Reageert de kaartdienst niet, dan laadt de samenvatting nu binnen enkele seconden in plaats van lang te blijven hangen.
- **Status van een zaak.** Een zaak waarvan het statustype verder in de lijst van het zaaksysteem stond, toont nu wel zijn status (uiterlijk een dag na de uitrol).
- **Bewaking van notificaties (beheer).** Elke ochtend om 07:00 controleert Eventloket of meldingen uit het zaaksysteem nog binnenkomen en of de abonnementen in orde zijn, en meldt het een probleem. Een koppeling die bewust via de hoofdkoppeling ontvangt, wordt niet als fout gemeld. Het adres waarop het zaaksysteem meldingen aflevert, heeft een limiet per afzender gekregen.
- **Abonnementen per koppeling (beheer).** Twee koppelingen op dezelfde Notificaties API zetten niet langer elkaars sleutel op hetzelfde abonnement, en de verbindingstest meldt het als dat toch zo is vastgelegd.
- Onderliggende onderdelen bijgewerkt.

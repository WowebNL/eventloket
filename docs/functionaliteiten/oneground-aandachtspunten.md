# OneGround aandachtspunten

Dit document verzamelt de bijzonderheden die we zijn tegengekomen bij het koppelen van Eventloket aan een OneGround (Rx.Mission) ZGW-instantie. OneGround gedraagt zich op een aantal punten anders dan de gedeelde Open Zaak van de veiligheidsregio (de hoofdkoppeling). De punten hieronder leggen per geval uit wat er speelt, hoe Eventloket ermee omgaat en wat een beheerder moet instellen.

De achtergrond bij het opzetten van een koppeling staat in [ZGW-koppelingbeheer](zgw-koppelingbeheer.md). Dit document is een aanvulling daarop, specifiek voor OneGround.

---

## 1. Intrekken door een organisator archiveert de zaak direct

Dit is het belangrijkste aandachtspunt. Op een OneGround-instantie mislukt het intrekken van een aanvraag door een organisator.

### Wat er gebeurt

De intrek-flow zet eerst het resultaat ("Afgebroken" of "Ingetrokken") en daarna de eindstatus. Op OneGround zet het zetten van de eindstatus de archiefstatus van de zaak meteen op `gearchiveerd` (in plaats van op `nog_te_archiveren`), omdat de archiefactiedatum voor dit resultaattype direct bepaalbaar is (afleidingswijze `afgehandeld`, einddatum plus de bewaartermijn). Die directe sprong naar `gearchiveerd` activeert de Open Zaak validatie die eist dat álle aan de zaak gekoppelde documenten al de status `gearchiveerd` hebben. Zolang dat niet zo is, komt er een 400 terug ("Er zijn gerelateerde informatieobjecten waarvan de status nog niet gelijk is aan gearchiveerd") en loopt het intrekken vast.

Dit wijkt af van de standaard Open Zaak, waar de eindstatus de archiefstatus niet meteen zet en alleen de archiefnominatie en archiefactiedatum berekent. Getest is dat documentstatus `definitief` niet genoeg is om te deblokkeren, alleen `gearchiveerd` werkt. Van de vier resultaattypen op het Heerlen-zaaktype gebruiken er drie de afleidingswijze `afgehandeld` (Buiten behandeling, Afgebroken, Geweigerd) en die geven allemaal dezelfde directe archivering. Alleen "Verleend" gebruikt `vervaldatum_besluit` en ontsnapt hieraan, maar dat past niet bij intrekken.

### Wat je moet instellen

Zet op de ZGW-koppeling de instelling **Dit is een OneGround koppeling** aan. Daarmee wordt het intrekken door een organisator automatisch geblokkeerd: de instelling "Intrekken door organisator toestaan" wordt uitgezet en vergrendeld, en de intrekken-actie verdwijnt uit Eventloket, zodat een organisator niet in de mislukkende flow terechtkomt. In de bijbehorende zaaktype-koppeling zijn het eind-statustype en het ingetrokken-resultaattype dan ook niet meer nodig en die velden verdwijnen.

### De fundamentele oplossing

Het uitzetten van de instelling is de operationele workaround. De eigenlijke oplossing zit in de catalogus. Pas de archief-configuratie van het "Afgebroken/Ingetrokken"-resultaattype aan (of kaart dit aan bij Roxit/OneGround) zodat de eindstatus de archiefstatus op `nog_te_archiveren` laat, conform GEMMA. Het daadwerkelijk archiveren van documenten blijft dan een latere stap van de archivaris. Documenten programmatisch op `gearchiveerd` zetten als workaround is bewust vermeden, omdat dat ze onomkeerbaar vergrendelt, de status `nog_te_archiveren` overslaat en ook interne adviesdocumenten raakt.

---

## 2. Systeemdocumenten staan op openbaar

Op OneGround krijgen de automatisch gegenereerde documenten (de aanvraag-PDF en de formulier-bijlagen) standaard het vertrouwelijkheidsniveau `openbaar`. Wanneer je het tabblad documenten verbergt bij een koppeling kan een organisator zijn eigen ingediende bestanden niet terugzien na het indienen.

Eventloket lost dit op door een organisator altijd de documenten te tonen die hij zelf heeft ingediend, ongeacht het ingestelde vertrouwelijkheidsniveau. Voor andere rollen en andere documenten blijft de rol-gebaseerde filtering gewoon gelden. Zie ook de sectie over vertrouwelijkheid in [ZGW-koppelingbeheer](zgw-koppelingbeheer.md).

---

## 3. Alleen definitieve documenten worden getoond

Documenten uit het zaaksysteem worden alleen in Eventloket getoond wanneer ze definitief zijn (documentstatus `definitief` of een document zonder status, zoals de eigen uploads van Eventloket). Concepten blijven verborgen. Gearchiveerde documenten worden nooit getoond, ook niet aan iemand die documenten wel mag zien.

Let op dat dit samenhangt met punt 1: zodra een zaak op OneGround wordt afgesloten en gearchiveerd, verdwijnen de bijbehorende documenten uit het bestanden-tabblad, ook voor de behandelaar. Dat is bewust gedrag.

---

## 4. Datums in zaakeigenschappen

OneGround is strikt in het formaat van datumwaarden. Een eigenschap van het type `datum_tijd` moet als veertien tekens worden aangeleverd (YYYYMMDDHHMMSS), niet als een kale datum van acht tekens, anders volgt een 400.

Eventloket bepaalt het formaat nu per eigenschap uit de catalogus zelf (`datum` wordt Ymd, `datum_tijd` wordt YmdHis) in plaats van uit één koppeling-brede instelling. Een beheerder hoeft hier dus niets voor in te stellen. De oude per-koppeling instelling "Datumformaat zaakeigenschappen" is daarmee vervallen. Als bijkomend voordeel worden tekstwaarden die toevallig als datum te lezen zijn (bijvoorbeeld een risicoklasse "B") niet langer per ongeluk in een datum omgezet.

---

## 5. Zaaktypen en catalogus

### Meerdere definitieve versies van een zaaktype

Op OneGround kan één zaaktype-identificatie meerdere definitieve versies hebben. Eventloket kiest de versie die vandaag geldig is (op basis van de geldigheidsdatum, met terugval op een willekeurige definitieve versie). Dit is belangrijk, omdat de verkeerde versie vaak niet de versie is die de eigenschappen en relaties draagt, waardoor eigenschappen anders niet zouden laden.

### Documenttype als omschrijving in plaats van URL

OneGround geeft in de relatie tussen zaaktype en informatieobjecttype de omschrijving rechtstreeks terug (een tekst), waar Open Zaak een URL teruggeeft. Eventloket herkent beide: een URL-waarde wordt opgehaald om de omschrijving te vinden, een niet-URL-waarde wordt direct als omschrijving behandeld. Zo laden de documenttypen ook op OneGround.

---

## 6. Zaakobjecten (adres en locatie)

Deze twee punten raken elke koppeling, maar kwamen naar boven bij de OneGround-integratie.

- **Adres.** Een BAG-adres wordt als zaakobject van type "adres" geregistreerd. Het veld `objectIdentificatie.identificatie` (de BAG nummeraanduiding-id) is verplicht. Zonder dat veld volgt een 400. Eventloket haalt die id op bij de Locatieserver en stuurt de straatnaam mee als openbare-ruimtenaam.
- **GlobaleLocatie.** De evenementlocaties worden als zaakobject van type "overige" (objectTypeOverige "GlobaleLocatie") meegestuurd. Voor dat type moet de identificatie onder `objectIdentificatie.overigeData` staan; een kale `objectIdentificatie.naam` wordt door zowel Open Zaak als OneGround geweigerd met een 400. Hier speelt wel een verschil in vorm. De ZGW-standaard typeert `overigeData` als een vrij-vorm object, dus voor Open Zaak stuurt Eventloket `{"naam": "..."}`. OneGround wijkt af en verwacht (en toont) `overigeData` als een kale tekst. Staat het vinkje **Dit is een OneGround koppeling** aan, dan stuurt Eventloket de locatienamen daarom als kale tekst in plaats van als object.

---

## 7. Doorkomsten over verschillende zaaksystemen

Wanneer een doorkomstgemeente OneGround gebruikt en de hoofdzaak op de gedeelde Open Zaak staat (of andersom), belanden de hoofdzaak en de deelzaak in verschillende zaaksystemen. Dat vraagt om extra afhandeling, omdat verwijzingen uit het ene systeem in het andere systeem niet bestaan.

- **Aanvrager.** De initiator van de deelzaak wordt opnieuw opgebouwd uit de gegevens van het aanvraagformulier, niet gekopieerd uit het bronsysteem. De gekopieerde rol uit het bronsysteem had namelijk een lege identificatie en een verwijzing naar het bronsysteem, wat het doelsysteem weigerde.
- **Documenten.** De aanvraag-PDF en de bijlagen worden bij een deelzaak in een ander systeem gedownload uit het bronsysteem en opnieuw aangemaakt in het doelsysteem. Staan hoofdzaak en deelzaak in hetzelfde systeem, dan wordt het bestaande document gewoon gekoppeld.
- **Documenttype.** Bij het kopiëren wordt in het doelsysteem een documenttype met exact dezelfde naam gezocht, met terugval op het aanvraag- of bijlage-documenttype uit de zaaktype-koppeling.
- **Vertrouwelijkheid.** Een gekopieerd document krijgt het vertrouwelijkheidsniveau van de doelkoppeling (de standaard voor systeemdocumenten), niet dat van het bronsysteem, omdat de vertrouwelijkheidsschema's van twee systemen niet gelijk hoeven te zijn.

De volledige uitleg staat in de sectie over doorkomsten in [ZGW-koppelingbeheer](zgw-koppelingbeheer.md).

---

## 8. Overige bijzonderheden

- **null beschrijving op documenten.** OneGround geeft de beschrijving van een document als `null` terug, waar Open Zaak een lege tekst teruggeeft. Eventloket accepteert beide, zodat het inlezen van een document niet vastloopt.
- **Per-koppeling instellingen.** OneGround wijkt genoeg af dat een gemeente vaak een aangepast gedrag wil. Op de ZGW-koppeling staan daarvoor schakelaars: de status niet wijzigbaar maken door de behandelaar (de status wordt dan volledig in het zaaksysteem beheerd), losse tabbladen (besluiten, bestanden, adviesvragen, organisatievragen) verbergen, en alle notificaties onderdrukken op de ontvangstbevestiging na. Standaard staan die zo dat het gedrag gelijk blijft aan de hoofdkoppeling. Stel ze alleen af als de werkwijze van de gemeente daarom vraagt.

---

## Samenvatting van in te stellen punten voor OneGround

- Zet **Dit is een OneGround koppeling** aan. Dat blokkeert meteen het intrekken door de organisator (punt 1) en zorgt dat de globale locatie in het OneGround-formaat wordt meegestuurd (punt 6).
- Overweeg **Status niet wijzigbaar door behandelaar** als de gemeente de status volledig in OneGround beheert (punt 8).
- Controleer bij het inrichten van de zaaktype-koppeling dat de eigenschappen en documenttypen goed laden (punt 5). Dat is meteen de bevestiging dat de juiste zaaktype-versie is gekozen.
- Voer altijd een proefaanvraag uit en controleer dat de zaak, de eigenschappen, de aanvrager, de documenten en de beginstatus correct in OneGround terechtkomen (zie de eindcontrole in [ZGW-koppelingbeheer](zgw-koppelingbeheer.md)).

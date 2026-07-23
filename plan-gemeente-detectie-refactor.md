# Plan: gemeentedetectie slimmer maken (gate in plaats van per-toetsaanslag)

Status: voorstel ter beoordeling. Nog niets geïmplementeerd. Dit plan beschrijft een herontwerp van de gemeentedetectie in het evenementformulier, met de precieze wijzigingen per bestand, de risico's en de testopzet.

## 1. Uitgangspunten

1. De detectie moet werken op drie inputtypes: adres (zit in een repeater), vlak (polygon) en lijn (route).
2. Harde eis: voordat de gebruiker naar de volgende stap mag, moet er een gemeente bepaald zijn. Dat is de gate.
3. Doel van dit plan: de detectie robuuster, sneller en beter te begrijpen maken, en tegelijk de autofill-race (bevinding A) grotendeels wegnemen.

## 2. Hoe het nu werkt

De detectie is reactief en gebeurt per veldwijziging. `EventFormPage::updated()` roept via `triggerFetchesFor()` bij elke wijziging aan `adresVanDeGebouwEn`, `locatieSOpKaart` of `routesOpKaart` synchroon `ServiceFetcher::fetch('inGemeentenResponse')` aan. Die call doet PostGIS-intersect voor vlakken en lijnen en een PDOK-lookup voor adressen, en merget alle geraakte gemeenten in `inGemeentenResponse.all.items`.

Uit die response leidt `FormDerivedState` af:

* `evenementInGemeente`: de gekozen gemeente. Als `userSelectGemeente` gezet is die, anders bij precies één gevonden gemeente die ene, anders null.
* `binnenVeiligheidsregio` uit `all.within` (stuurt de waarschuwing `NotWithin`).
* `evenementInGemeentenNamen` en `gemeenten` (sturen de keuze-radio `userSelectGemeente` en de bevestigingstekst `content200`).

De gate `afterValidation` op de locatiestap leest alleen `evenementInGemeente` en blokkeert met een melding als die leeg is.

## 3. Wat er mis is

1. De gate leest een reactief bijgehouden waarde in plaats van zelf te berekenen. De hardste eis rust zo op het meest fragiele mechanisme.
2. Elke toetsaanslag in een adres triggert een volledige location-check met externe PDOK-call en PostGIS. Die zware synchrone arbeid vertraagt de re-render en veroorzaakt de autofill-race (bevinding A), die het ergst is bij een adres in de repeater.
3. Voor adressen worden twee PDOK-calls op hetzelfde adres gedaan: één in de autofill (`getBagObjectByPostcodeHuisnummer`, levert onder andere `gemeentecode`) en één in de detectie (`getBrkIdentificationByPostcodeHuisnummer`). De gemeente is dus al bekend bij het aanvullen en wordt onnodig opnieuw opgehaald.
4. De triggers zijn verspreid (drie veld-keys, plus `userSelectGemeente`, plus seeding bij mount, plus de gate), wat het geheel lastig te overzien en te testen maakt.

## 4. Voorgestelde architectuur

Kern: draai de verantwoordelijkheid om. De gate wordt gezaghebbend, en de reactiviteit wordt goedkoop of vervalt voor adressen.

1. **Adressen worden niet meer per toetsaanslag gedetecteerd.** De detectie voor adressen verhuist naar de gate (op Volgende). Daarmee triggert het typen van een adres geen zware synchrone detectie plus re-render meer, en verdwijnt de belangrijkste oorzaak van bevinding A.
2. **Vlakken en lijnen blijven reactief gedetecteerd.** Die committen atomisch bij het tekenen en hebben de typ-race niet. De live bevestiging en de keuze-radio blijven bij kaart-invoer dus gewoon werken.
3. **De adres-gemeente wordt hergebruikt uit de autofill.** De autofill kent al de `gemeentecode` van het adres. Die slaan we op bij de adresrij, zodat de detectie op de gate voor een aangevuld adres geen tweede PDOK-call meer nodig heeft. Voor een handmatig ingevuld adres (autofill mislukte) valt de gate terug op de bestaande BRK-lookup, één keer.
4. **De gate berekent autoritatief.** Op Volgende draait de volledige location-check over alle huidige inputs (adressen uit de repeater, vlakken, lijnen), en beslist:
   * 0 gemeenten: blokkeren met de bestaande melding.
   * 1 gemeente: zetten en doorgaan.
   * 2 of meer: de response zetten (zodat de keuze-radio zichtbaar wordt) en blokkeren tot de gebruiker kiest, daarna doorgaan.

## 5. Concrete wijzigingen per bestand

### 5.1 `app/EventForm/Components/AddressNL.php`

* In `lookupCallback()` bij een succesvolle lookup naast straat en plaats ook de gemeentecode van het BAG-object opslaan in een verborgen subveld, bijvoorbeeld `{$key}.brkGemeente` met waarde `'GM'.$bag->gemeentecode`.
* Bij een mislukte lookup dit subveld leegmaken, net zoals straat en plaats nu al worden leeggemaakt.
* Het subveld toevoegen als verborgen veld (`->hidden()->dehydrated()`), zodat het in de form-state belandt maar niet in de samenvatting of PDF verschijnt. Let op: `dehydrated(false)` mag hier niet, want de waarde moet juist bewaard blijven.
* `SUBFIELDS` en `REQUIRED_SUBFIELDS` bewust niet uitbreiden met dit veld: het is intern, niet verplicht, en hoort niet in de rapportage-introspectie.

### 5.2 `app/EventForm/Services/ServiceFetcher.php`

* `collectAddressesFromEditgrid()` per adres het opgeslagen `brkGemeente` meenemen in de teruggegeven structuur, bijvoorbeeld als optioneel veld `brkIdentification`.
* De cache-sleutel voor `inGemeentenResponse` (nu al op de gefilterde input sinds PR #448) blijft correct: de gefilterde adressen bevatten dan ook de brk-identificatie, dus een gewijzigde gemeente bust de cache netjes.

### 5.3 `app/EventForm/Services/LocationServerCheckInput.php` en `LocationServerCheckService.php`

* De adres-structuur uitbreiden met een optionele `brkIdentification`.
* In `absorbAddress()`: als het adres een `brkIdentification` meekrijgt, die direct gebruiken (municipality-lookup op `brk_identification`) en de PDOK-call overslaan. Alleen bij een ontbrekende `brkIdentification` (handmatig adres) terugvallen op `getBrkIdentificationByPostcodeHuisnummer`. De `within`-afhandeling blijft gelijk.

### 5.4 `app/Filament/Organiser/Pages/EventFormPage.php`

* In `triggerFetchesFor()` `adresVanDeGebouwEn` uit de reactieve trigger voor `inGemeentenResponse` halen. Vlakken en lijnen blijven de detectie reactief triggeren. Adres-wijzigingen triggeren dan geen location-check meer tijdens het typen.
* `refreshFetchesFromExistingState()` (mount, voor hervatten van een concept) ongewijzigd laten: dat draait één keer bij laden en heeft de typ-race niet.

### 5.5 `app/EventForm/Schema/Steps/LocatieVanHetEvenement2Step.php`

* `afterValidation` uitbreiden van alleen-lezen naar berekenen. De stappen:
  1. `app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state)` draaien (dekt adressen, vlakken en lijnen in één keer).
  2. Vervolgens `gemeenteVariabelen` en `evenementenInDeGemeente` fetchen, net zoals de huidige `triggerFetchesFor` dat na de location-check doet, zodat de latere stappen hun gemeente-afhankelijke labels hebben.
  3. `evenementInGemeente` opnieuw uitlezen.
  4. Als er 2 of meer gemeenten zijn en er nog geen `userSelectGemeente` gekozen is: een melding tonen ("kies de gemeente waarvoor u de aanvraag doet") en `throw new Halt`. De response staat dan al in de state, dus de keuze-radio en de bijbehorende teksten worden zichtbaar op de gehalte render.
  5. Als `evenementInGemeente` leeg blijft (0 gemeenten): de bestaande melding tonen en `throw new Halt`.
  6. Anders doorlaten.

### 5.6 Zichtbaarheidsregels

Geen wijziging nodig. `content200`, `userSelectGemeente`, `NotWithin` en de route-info lezen `evenementInGemeente`, `evenementInGemeentenNamen` en `inGemeentenResponse.all.within`. Omdat de gate de response zet vóór de Halt, evalueren die regels correct op de gehalte render. Bij kaart-invoer werken ze zoals nu, reactief.

## 6. Multi-gemeente en de routeflow

* Multi-gemeente (adres of vlak in twee gemeenten): de keuze-radio verschijnt op de eerste Volgende in plaats van al tijdens het typen. De gebruiker kiest, en de tweede Volgende laat door. Dit is een gedragswijziging, maar aantoonbaar duidelijker.
* Route die meerdere gemeenten doorkruist: dezelfde behandeling. De doorkruist-info en de start-eind-gemeente-logica draaien mee in de location-check op de gate. De reactieve tekening van de route blijft de live detectie voeden, dus voor lijnen verandert de beleving nauwelijks.

## 7. Wat expliciet blijft werken

* Vlak- en lijndetectie: reactief bij tekenen, plus autoritatief op de gate. Dit is de expliciete eis uit je opdracht en wordt in de tests apart geborgd.
* Hervatten van een concept: `refreshFetchesFromExistingState()` blijft bij mount de detectie draaien zodat een hervat concept meteen een gemeente heeft.

## 8. Risico's

1. Refactor van kern-reactiviteit met regressierisico op de OF-afgeleide zichtbaarheidsregels en op de routeflow. De equivalentietests (`tests/Feature/EventForm/Equivalence`) zijn hier het vangnet en moeten groen blijven.
2. Het verborgen `brkGemeente`-subveld mag niet in de samenvatting, de PDF of de ZGW-payload lekken. Dit vraagt een expliciete test op de rapportage en de submit.
3. De gate doet nu werk (PDOK plus PostGIS) op de klik "Volgende". Dat is een korte extra wachttijd op dat moment. Acceptabel, want het gebeurt één keer in plaats van per toetsaanslag, en de cache uit PR #448 vangt herhaalde klikken op.
4. Gedragswijziging: de live bevestiging voor adressen verschijnt pas na Volgende. Als je de live hint voor adressen tóch wilt behouden, kan dat goedkoop terug via de hergebruikte gemeentecode (in-memory, geen PDOK), maar dat is een optionele uitbreiding die de race weer iets dichterbij brengt. Mijn advies: eerst zonder, en alleen toevoegen als het gemist wordt.

## 9. Testopzet

1. Pest, gate-gedrag op `LocatieVanHetEvenement2Step` via `EventFormPage`: adres met 1 gemeente laat door; 0 gemeenten blokkeert met melding; 2 gemeenten blokkeert en toont de radio, en na keuze laat door.
2. Pest, adres-hergebruik: een aangevuld adres levert op de gate geen tweede PDOK-call op (via `Http::assertSentCount`), een handmatig adres wel één BRK-lookup.
3. Pest, expliciet voor de eis: vlak-detectie en lijn-detectie blijven werken, zowel reactief als op de gate. Aanhaken op de bestaande `ServiceFetcherIntersectTest` en `LocationServerCheckServiceTest`.
4. Pest, lek-check: `brkGemeente` komt niet voor in de samenvatting-rapportage en niet in de ZGW-submit.
5. Equivalentietests: de volledige `EventForm/Equivalence`-suite moet groen blijven.
6. Playwright, menselijk getimede test (uit PR #447, de `test.fixme` omzetten): twee adres-rijen invullen met menselijk typgedrag, beide krijgen straat en plaats, en na Volgende is de gemeente bepaald. Plus een scenario met een getekend vlak en een getekende lijn dat aantoont dat de gemeente daar nog steeds bepaald wordt.

## 10. Uitrol

1. Aparte fix-branch vanaf `main`.
2. De wijziging in logische commits: eerst de adres-gemeente-hergebruik (AddressNL plus service), dan de gate-verschuiving (EventFormPage plus stap), dan de tests.
3. Zoals eerder: PR naar `main`, daarna via de beta naar `next/v1.2`.
4. De `test.fixme` voor bevinding A in PR #447 omzetten naar een echte test zodra de gate-aanpak staat.

## 11. Beslissingen die ik van jou nodig heb

1. Akkoord op de kernkeuze: adressen alleen op de gate detecteren, vlakken en lijnen reactief plus op de gate.
2. Is de gedragswijziging akkoord dat de keuze-radio en de gemeente-bevestiging voor adressen pas op Volgende verschijnen, in plaats van al tijdens het typen?
3. Wil je de optionele goedkope live-hint voor adressen (via de hergebruikte gemeentecode) wel of niet meenemen?
4. Akkoord dat dit een aparte PR wordt, los van de reeds openstaande PR's (#446, #447, #448)?

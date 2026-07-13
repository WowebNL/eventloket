# Plan: fix bevinding A (adres-autofill gaat verloren bij meerdere locaties)

Status: optie A is geïmplementeerd en in de browser getest, en werkt NIET. Zie sectie 0 hieronder. Er is geen PR aangemaakt. De rest van dit plan blijft staan als achtergrond en voor de vervolgkeuze.

## 0. Uitkomst van optie A (getest, werkt niet)

Optie A is geïmplementeerd als een verfijning van de cache-sleutel van `inGemeentenResponse` in `ServiceFetcher`: hashen op de gefilterde, effectieve input (complete adressen en gefilterde geometrieën) in plaats van de ruwe editgrids. Doel: een half getypt adres bust de cache niet meer, dus die commit wordt een snelle cache-hit en de re-render landt niet in het typ-venster.

Resultaat in de browser (menselijk typen, teken voor teken, drie runs): het tweede adres blijft leeg. In de server-logs verschijnt voor dat adres geen enkele autofill-aanroep, dus de velden van die rij synchroniseren helemaal niet naar de server. Ook een variant met `->live(onBlur: true)` op de adresvelden (directe commit op blur) maakte het niet beter, eerder slechter.

Wat dit betekent: de aanname onder optie A klopt niet volledig. Het probleem is niet primair de snelheid van de gemeente-detectie, maar de re-render zelf. Elke veld-commit laat Livewire het formulier opnieuw renderen, en die re-render wist de nog niet gecommitte invoer van een net toegevoegde repeater-rij, ongeacht of de fetch snel of traag is. Alleen met een ruime pauze tussen de velden (circa 2,5 seconde) synchroniseert de rij wel, wat bevestigt dat het een re-render-race is die dieper zit dan de gemeente-detectie.

Conclusie: optie A alleen lost bevinding A niet op. Een werkende oplossing vraagt om het voorkomen dat de re-render de invoer van een rij in bewerking wist. Dat is óf een grotere herstructurering (de gemeente-reactiviteit isoleren van de adresvelden, richting optie B), óf een framework-niveau ingreep op hoe de Filament-repeater zijn rijen opnieuw rendert. Beide vallen buiten de kleine, laag-risico wijziging die optie A beoogde.

Aanbeveling voor de vervolgstap staat in sectie 9 (nieuw).

---

## Oorspronkelijk voorstel (achtergrond)

## 1. Het probleem, precies vastgesteld

Bij het invullen van een adres in de locatie-stap wordt straat en plaats soms niet automatisch aangevuld. Welke rij faalt hangt van de timing af (de tester zag rij 1 falen, in mijn browsertest faalde rij 2).

Wat er onder water gebeurt, is met browser-reproductie en server-logging vastgesteld:

1. Het is een Livewire re-render-race, geen logische fout. Server-side klopt de keten: de lookup vult straat en plaats correct als je hem los aanroept.
2. Tijdens het typen in een adresrij landt een re-render van het formulier. Die re-render overschrijft de nog niet naar de server gesynchroniseerde invoer van die rij. Daardoor draait de autofill-callback voor die rij nooit. In de logs verschijnt voor het getroffen adres geen enkele PDOK-lookup.
3. De trage, ongecachte gemeente-detectie is de menselijke trigger. Die vertraagt de re-render tot in het venster waarin de gebruiker het volgende veld typt. Bij een nieuw adres (andere gemeente dan een eerdere rij) is die detectie niet gecachet en dus traag.

Kort samengevat: veld-commit van rij X start een trage gemeente-detectie, de daaruit volgende re-render landt terwijl de gebruiker rij X of rij Y verder invult, en die invoer gaat verloren.

## 2. Wat aantoonbaar NIET werkt

Deze aanpakken zijn in de browser getest en lossen het niet op:

1. De sync-modifier van de adresvelden veranderen. Getest met `->live(debounce: '750ms')` (huidig), `->live(onBlur: true)` en `->live()` (direct per toetsaanslag). In alle drie de gevallen synct de getroffen rij nog steeds niet en blijft straat en plaats leeg. De modifier is dus niet de oorzaak.
2. De gemeente-detectie volledig uitzetten. Bij Playwright's instant-invoer (nul vertraging tussen velden) blijft het falen, omdat elke veld-commit sowieso een re-render veroorzaakt. Bij menselijke timing (ongeveer 600 ms tussen velden) vult het dan wel. Dat bevestigt dat het puur een timing-race is.

De les: het gaat niet om de veldbinding maar om de re-render die tijdens het typen landt. Snelle re-renders (zonder de trage gemeente-detectie) vallen buiten het typ-venster van een normale gebruiker. Trage re-renders vallen er middenin.

## 3. Voorgestelde fix

Kernidee: de trage gemeente-detectie loskoppelen van de veld-commit, zodat de re-render die op een adreswijziging volgt snel terugkomt en niet in het typ-venster van de gebruiker landt.

Concreet, in volgorde van voorkeur:

### Optie A (voorkeur): gemeente-detectie pas draaien wanneer een adres compleet is, en niet per tussentijdse wijziging

In `EventFormPage::triggerFetchesFor()` draait `inGemeentenResponse` nu bij elke wijziging onder `adresVanDeGebouwEn` (dus ook op alleen een postcode, of een huisletter). Voorstel: de gemeente-detectie voor adressen alleen uitvoeren wanneer de gewijzigde rij een compleet adres heeft (postcode en huisnummer aanwezig). `ServiceFetcher::collectAddressesFromEditgrid()` filtert al op complete adressen, dus dit is ook logisch correct: een gemeente is pas te bepalen bij een compleet adres.

Effect: postcode-commits worden snel (geen zware detectie). De detectie draait pas op de huisnummer-commit, op het moment dat de rij af is en de gebruiker verdergaat. Dat verkleint het venster waarin een trage re-render kan botsen met typen.

Kanttekening: dit verkleint de race sterk maar sluit hem bij extreem snel typen niet volledig uit. Voor echte gebruikers is dat naar verwachting voldoende.

### Optie B (verdergaand): gemeente-detectie uitstellen tot de gebruiker pauzeert

De gemeente-detectie in een aparte, gedebouncede vervolgronde draaien (bijvoorbeeld via een Alpine-listener die na circa 800 ms stilte een Livewire-methode aanroept), in plaats van synchroon in `updated()`. De adres-commit zelf blijft dan licht en rendert snel; de detectie en de bijbehorende re-render gebeuren pas als de gebruiker klaar is met de locatie-stap.

Effect: sterkste ontkoppeling. Nadeel: meer code (custom JS), en de reactieve gemeente-bevestiging ("U gaat verder met deze aanvraag voor de gemeente X") verschijnt met een korte vertraging in plaats van direct. Dat raakt het gedrag uit bevinding 1.

### Optie C (aanvullend, niet als losse fix): straat en plaats robuuster terugzetten

Onafhankelijk van A of B kan de autofill defensiever: mocht een rij na een adreswijziging leeg blijven terwijl postcode en huisnummer wel gevuld zijn, dan de lookup opnieuw uitvoeren. Dit is een pleister, geen oorzaakfix, en heeft alleen zin in combinatie met A of B.

Mijn advies: beginnen met optie A. Die is de kleinste, meest gerichte wijziging, is logisch correct, en raakt de reactieve UX het minst. Optie B alleen als A in de praktijk onvoldoende blijkt.

## 4. Risico's en impact

1. Kern-reactiviteit van het formulier. De gemeente-detectie stuurt zichtbaarheid van latere velden en de gemeente-bevestiging aan. Een wijziging hier kan die reactiviteit subtiel veranderen. Regressierisico op bevinding 1 en op de doorkruiste-gemeenten-logica bij routes.
2. Verificatie is niet deterministisch. Een Playwright-test met instant-invoer racet altijd en is dus geen betrouwbare gate. Een test met menselijk getimede invoer (teken voor teken met vertraging) benadert de werkelijkheid, maar zulke timing-tests zijn inherent gevoelig voor flakiness in CI.
3. De bestaande gedragsverwachting uit bevinding 2 (aanvulling wacht tot je klaar bent met typen) moet intact blijven. Optie A raakt dit niet, want de debounce op de velden blijft staan.

## 5. Verificatieplan

1. Playwright-scenario dat menselijk typgedrag nabootst: postcode teken voor teken met een kleine vertraging, tab, huisnummer teken voor teken, tab, en direct doorgaan naar een tweede rij. Assert dat beide rijen straat en plaats gevuld hebben. Dit scenario moet vóór de fix falen en erna slagen.
2. De bestaande Pest-tests op `AddressNL` en `EventFormPage` blijven groen (geen regressie op de server-side keten).
3. Handmatige controle in de browser door jou, met een tweede locatie in een andere gemeente dan de eerste (dat is de trage, ongecachte situatie waarin het misging).
4. Regressiecheck op bevinding 1: de gemeente-bevestiging verschijnt nog steeds nadat een adres is ingevuld.

## 6. Uitrol

1. Werk verder op de bestaande branch `chore/playwright-organiser-seeder` (die de Playwright-infra en de `test.fixme` al bevat), of een nieuwe branch vanaf `main`, jouw keuze.
2. De `test.fixme` voor bevinding A omzetten naar een echte test met menselijke timing zodra de fix werkt.
3. Losse commit voor de fix, gescheiden van de infra-commit, zodat de review overzichtelijk blijft.
4. Zoals eerder: fix op `main` via PR, daarna via de beta naar `next/v1.2`.

## 7. Beslissingen die ik van jou nodig heb

1. Akkoord op de richting: eerst optie A, en pas naar optie B als A onvoldoende blijkt?
2. Accepteer je dat de verificatie niet 100 procent deterministisch is (menselijk getimede test plus handmatige controle), gezien de aard van de race?
3. Als optie B nodig blijkt: is de kleine vertraging op de reactieve gemeente-bevestiging acceptabel?
4. Op welke branch wil je dat ik dit doe?

## 8. Inschatting

Optie A: kleine codewijziging, plus een menselijk getimede Playwright-test en handmatige controle. Grootste kostenpost is zorgvuldig testen tegen regressie op de gemeente-logica, niet de wijziging zelf. Optie B is duidelijk meer werk door de custom JS en de extra reactiviteitspaden.

## 9. Aanbevolen vervolgstap (na de mislukte optie A)

Nu duidelijk is dat de re-render zelf de invoer wist, zijn er drie realistische wegen. Ik heb geen voorkeur opgedrongen; dit is jouw keuze.

1. Optie B uitwerken: de gemeente-detectie loskoppelen van de veld-commit zodat het formulier tijdens het typen van een adres niet opnieuw rendert, en de detectie plus de bijbehorende re-render pas draaien nadat de gebruiker de locatie-stap afrondt of even pauzeert. Dit is de kansrijkste echte oplossing, maar meer werk en met UX-impact op de reactieve gemeente-bevestiging (bevinding 1). Verificatie blijft niet-deterministisch (menselijk getimede test plus handmatige controle).

2. De repeater-reactiviteit isoleren: de gemeente-gerelateerde InfoTexts en de gemeente-keuze in een apart Livewire-onderdeel plaatsen dat losstaat van de adres-repeater, zodat een gemeente-update de adresvelden niet opnieuw rendert. Structureel de nette oplossing, maar de grootste ingreep.

3. Accepteren en mitigeren: bevinding A als bekende, gedocumenteerde beperking laten staan (de test.fixme), en de impact verzachten. De velden straat en plaats zijn al verplicht en handmatig invulbaar, dus de gebruiker kan altijd verder. Eventueel een korte hint tonen dat aanvullen soms even duurt bij een tweede locatie.

Los hiervan is er nog een kleine, losstaande verbetering die ik tijdens het testen vond en die veilig is, maar bevinding A niet oplost: de cache-sleutel van `inGemeentenResponse` op de gefilterde input hashen in plaats van de ruwe editgrids. Dat scheelt overbodige PDOK-aanroepen bij half getypte adressen. Wil je die als aparte, kleine PR, dan kan dat, maar hij lost het gerapporteerde probleem niet op en ik lever hem dus niet onder de vlag van een bevinding-A-fix.

# Eventloket versie 1.3.2: wat is er nieuw?

**Releasedatum:** 15 september 2026

---

Deze versie herstelt de kaarten. Sinds kort verscheen er op de plek van de kaart een grijs vlak met de tekst "Access blocked", zowel in het aanvraagformulier als bij een zaak. Eventloket gebruikt vanaf nu de Nederlandse achtergrondkaart van het Kadaster in plaats van de kaartlaag die daarvoor werd gebruikt. Daarmee werken de kaarten weer, en ze zien er iets anders uit dan je gewend was.

---

## 🐛 Opgeloste problemen

### De kaart liet geen kaartmateriaal meer zien

**Voor wie:** Organisatoren die een aanvraag indienen, gemeentemedewerkers en behandelaars

Op elke plek waar Eventloket een kaart toont, verscheen in plaats van het kaartbeeld een grijs vlak met daarin de melding dat de toegang geblokkeerd was. Het intekenen van een locatie of een route bleef technisch werken, maar je zag niet meer waar je stond, en daarmee was het in de praktijk onbruikbaar.

De oorzaak lag niet in Eventloket zelf. Het kaartmateriaal kwam van een dienst die door vrijwilligers wordt onderhouden en die grenzen stelt aan hoeveel een enkele website mag opvragen. Een publiek aanvraagformulier met kaarten zit al snel boven die grens, en op een gegeven moment is de toegang vanaf Eventloket geweigerd. Dat is geen storing die vanzelf overgaat zolang het kaartmateriaal daarvandaan blijft komen.

Eventloket haalt de kaart daarom nu bij het Kadaster, via de landelijke achtergrondkaart die daar voor dit soort gebruik beschikbaar is. Daar gelden deze beperkingen niet.

**Wat je merkt:** de kaart ziet er anders uit. Het is dezelfde soort kaart met straten, gebouwen en plaatsnamen, maar met de vormgeving van het Kadaster. Onderin staat nu een bronvermelding.

---

## ⚠️ Buiten Nederland is er geen kaartbeeld

**Voor wie:** Organisatoren en behandelaars bij een evenement of route over de grens

De landelijke achtergrondkaart houdt op bij de landsgrens. Ligt een deel van een route of een locatie in België of Duitsland, dan blijft het kaartvlak daar leeg. Je kunt er wel gewoon op tekenen en de coördinaten kloppen, maar je ziet geen ondergrond.

Dit is een bewuste keuze bij deze versie. De kaarten moesten op korte termijn weer werken, en de landelijke kaart is daarvoor de meest geschikte bron. Loop je hier in de praktijk tegenaan bij een grensoverschrijdend evenement, laat het dan weten, dan kijken we naar een oplossing voor het buitenland.

---

## ✨ Verder verbeterd

**Voor wie:** Iedereen die met kaarten werkt

* **De kaart is scherper op gewone beeldschermen.** De kaart werd opgebouwd uit beeldtegels van een ander formaat dan de dienst levert, waardoor het beeld op schermen zonder hoge resolutie wazig oogde. Dat is rechtgezet.
* **Op elke kaart staat nu een bronvermelding.** Op de kaarten in het aanvraagformulier ontbrak die.
* **De kaart in de pdf van een inzending.** Bij een zeer grote route werd die kaart soms half opgebouwd. Er zit nu een grens op het aantal kaartdelen dat wordt opgehaald. Wordt die grens bereikt, dan toont de pdf een egale ondergrond in plaats van een kaart die er half op staat.

---

## 📱 Wat moet je doen?

### Voor gemeenten, behandelaars en organisatoren

**Niets.** De kaarten werken weer zodra de update live staat.

Zie je toch nog een grijs vlak op de plek van een kaart, ververs dan eerst de pagina. Blijft het staan, laat het dan weten.

### Aanvragen die je eerder hebt ingediend

Aanvragen die zijn ingediend toen de kaart niet werkte, zijn gewoon compleet. De ingetekende locaties en routes zijn altijd correct opgeslagen; alleen de ondergrond was niet zichtbaar. Je hoeft niets opnieuw in te dienen of te controleren.

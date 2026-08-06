# Eventloket versie 1.1.3: wat is er nieuw?

**Releasedatum:** 5 augustus 2026

---

Deze versie is een korte onderhoudsrelease. De coördinator kan bij het uploaden van een document weer zelf kiezen wie het mag inzien, de zichtbaarheid van besluitdocumenten is aangescherpt met een extra controle, en er is een spelfout in het aanvraagformulier hersteld. Er zijn geen nieuwe functies en er verandert niets aan de manier van werken.

---

## 🐛 Opgeloste problemen

### Coördinator kan weer kiezen wie een document mag inzien

**Voor wie:** Coördinatoren bij gemeenten

Bij het toevoegen van een bestand op het tabblad Documenten ontbrak voor de coördinator de keuze "Wie mag dit document inzien?". Behandelaars en gemeentelijk beheerders kregen die keuze wel te zien. Omdat het veld ontbrak, viel de upload terug op de standaardinstelling "vertrouwelijk" en was het document dus niet zichtbaar voor de organisator, ook als dat wel de bedoeling was.

De coördinator krijgt de keuze nu net als de andere rollen, met dezelfde drie niveaus. In dezelfde beweging zijn twee kleinere verschillen rechtgetrokken: de coördinator ziet nu ook de kolom "Intern zaaknummer" in het zakenoverzicht en beschikt bij een aanvraag over dezelfde gegevens over de organisator en organisatie als de behandelaar.

**Let op voor beheerders:** documenten die een coördinator vóór deze update heeft geüpload, staan op "vertrouwelijk" en zijn daarom niet zichtbaar voor de organisator. Die documenten worden niet automatisch omgezet. Moet zo'n document alsnog zichtbaar zijn voor de organisator, upload het dan opnieuw met de juiste instelling.

---

### Extra controle op de zichtbaarheid van besluitdocumenten

**Voor wie:** Organisatoren, behandelaars, coördinatoren en adviseurs

Voor gewone zaakdocumenten bepaalt de rol van de gebruiker al welke documenten zichtbaar zijn. Bij besluitdocumenten werd die controle nog niet overal even consequent toegepast. Dat is nu rechtgezet, zodat overal in de applicatie dezelfde regel geldt.

Wat er is verbeterd:

* Bij een besluit ziet iedere gebruiker voortaan alleen de bijbehorende documenten die bij zijn of haar rol horen. Voor behandelaars, coördinatoren, gemeentelijk beheerders en beheerders verandert er niets: zij zagen en zien alle besluitdocumenten. Adviseurs zien geen documenten meer met het niveau waar ze geen recht toe hebben, precies zoals dat bij gewone zaakdocumenten al het geval was. De organisator ziet de documenten die voor de organisator bedoeld zijn.
* Bij het afronden van een aanvraag worden alleen nog documenten aangeboden die voor de organisator zichtbaar zijn. Een besluitdocument is per definitie bedoeld voor de organisator, dus een document met verkeerde vertrouwelijkheid kan daar niet meer per ongeluk voor worden gekozen.
* Wordt een document opgevraagd dat bij de eigen rol niet hoort, dan volgt nu een nette "niet gevonden" melding in plaats van een foutmelding.

**Let op voor beheerders:** in combinatie met de vorige verbetering betekent dit dat documenten die een coördinator eerder als "vertrouwelijk" heeft geüpload, niet bij de besluitdocumenten van de organisator verschijnen. Ook hier geldt: opnieuw uploaden met de juiste instelling als het document wel zichtbaar moet zijn.

---

### Spelfout hersteld in het aanvraagformulier

**Voor wie:** Organisatoren

In de eerste stap van het aanvraagformulier ("Type aanvraag") stond een spelfout in de tekst van de eerste keuzeoptie. Er stond "evementen" in plaats van "evenement". Alleen de tekst is aangepast. Aan de werking van het formulier, de ingevulde antwoorden en de vervolgstappen verandert niets.

---

## 📱 Wat moet je doen?

**Niets!** De verbeteringen werken automatisch na de update.

Wel goed om te weten voor gemeenten: controleer bij lopende aanvragen of documenten die een coördinator eerder heeft geüpload, wel de juiste zichtbaarheid hebben. Zie de twee opmerkingen hierboven.

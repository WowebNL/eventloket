# Eventloket versie 1.3.1: wat is er nieuw?

**Releasedatum:** 10 september 2026

---

Deze versie lost twee problemen op die na de vorige update zijn ontstaan. Gedownloade en meegestuurde documenten waren niet te openen, en het filter op soort zaak liet niet alle zaken zien. Beide zijn hersteld. Daarnaast is het duidelijker geworden wat er aan de hand is wanneer een document een keer niet opgehaald kan worden, in plaats van dat je een onbruikbaar bestand krijgt zonder uitleg.

---

## 🐛 Opgeloste problemen

### Gedownloade en meegestuurde documenten waren niet te openen

**Voor wie:** Gemeentemedewerkers, behandelaars en organisatoren, en iedereen die een resultaatmail ontvangt

Documenten die je als zip-bestand downloadde bij een zaak, waren na het uitpakken niet te openen. Het archief zelf opende wel, maar de bestanden erin waren onbruikbaar. Hetzelfde gold voor de bijlagen bij de mail die na het afronden van een zaak naar de aanvrager gaat.

De oorzaak lag in de manier waarop Eventloket die bestanden ophaalde bij het zaaksysteem. Bij het losse bekijken en downloaden van één document ging dat goed, maar op de twee plekken waar meerdere bestanden tegelijk worden verzameld, werd niet het document zelf opgehaald maar een technische omschrijving ervan. Dat verklaart ook waarom het bekijken van een document wel gewoon werkte.

Beide plekken halen de documenten nu op dezelfde manier op als het losse bekijken, en Eventloket controleert bovendien of het zaaksysteem het bestand daadwerkelijk heeft geleverd voordat het wordt weggeschreven of meegestuurd.

**Let op:** documenten die je vóór deze update hebt gedownload of ontvangen, worden hier niet mee gerepareerd. Download je ze opnieuw, dan zijn ze weer bruikbaar.

---

### Het filter op soort zaak liet niet alle zaken zien

**Voor wie:** Gemeentemedewerkers, behandelaars en adviseurs

Filterde je in het zakenoverzicht of in de kalender op soort zaak, dan verscheen een deel van de zaken niet in de lijst. Het vervelende daaraan is dat de lijst er compleet uitzag: er stond geen melding bij dat er iets ontbrak.

Het filter keek naar een veld dat alleen gevuld is bij zaaktypen die via een koppeling met een eigen zaaksysteem zijn ingericht. Zaaktypen die al langer in Eventloket stonden, hadden dat veld niet, en vielen daarom buiten het filter. Het filter kijkt nu naar het werkelijke soort zaak, ongeacht hoe het zaaktype ooit is aangemaakt.

Voor de kalender geldt dit sinds de vorige versie; in het zakenoverzicht is dit filter met de vorige versie nieuw toegevoegd en heeft het dus nooit volledig gewerkt.

---

## ✨ Duidelijker bij een document dat niet opgehaald kan worden

**Voor wie:** Gemeentemedewerkers en behandelaars

Het kan gebeuren dat een document tijdelijk niet op te halen is bij het zaaksysteem, of dat het niet meer aan de aanvraag gekoppeld is. Voorheen merkte je dat niet of pas als je het bestand probeerde te openen. Vanaf nu is het zichtbaar.

* **Bij een download** zit er in het zip-bestand een tekstbestand `ONTBREKENDE-BESTANDEN.txt` met de namen van de bestanden die er niet in zitten. Daarin staat apart welke bestanden tijdelijk niet opgehaald konden worden (opnieuw proberen kan dan zin hebben) en welke niet meer aan de aanvraag gekoppeld zijn (opnieuw proberen helpt daar niet).
* **Bij het afronden van een zaak** controleert Eventloket vooraf of de bijlagen die je meestuurt wel opgehaald kunnen worden. Lukt dat niet, dan wordt het afronden gestopt met een melding die de betreffende documenten bij naam noemt. Zo gaat er nooit een resultaatmail de deur uit waarin een bijlage stilzwijgend ontbreekt.

Die tweede controle betekent dat je een zaak niet kunt afronden zolang een geselecteerde bijlage niet beschikbaar is. Dat is een bewuste keuze: als behandelaar moet je erop kunnen vertrouwen dat de bestanden die je meestuurt ook echt aankomen. Je kunt de bijlage in dat geval uit de selectie halen, of een ander document meesturen.

---

## 📱 Wat moet je doen?

### Voor gemeenten, behandelaars en organisatoren

**Niets.** De verbeteringen werken automatisch na de update.

Heb je op 10 september een download of een resultaatmail gekregen met bestanden die niet te openen waren, download die documenten dan opnieuw bij de zaak. Ze zijn daarna gewoon bruikbaar. Alleen op die dag speelde dit; de update die het veroorzaakte kwam de avond ervoor live.

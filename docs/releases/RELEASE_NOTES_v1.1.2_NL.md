# Eventloket versie 1.1.2: wat is er nieuw?

**Releasedatum:** 28 juli 2026

---

Deze versie is een korte onderhoudsrelease. Er zijn twee storingen opgelost waarbij een scherm volledig vastliep, en er is een aantal externe softwarecomponenten bijgewerkt naar een veilige versie. Er zijn geen nieuwe functies en er verandert niets aan de manier van werken.

---

## 🐛 Opgeloste problemen

### Adviesinbox liep vast na het verwijderen van een aanvraag

**Voor wie:** Adviseurs

Stond er in de adviesinbox een adviesvraag waarvan de bijbehorende aanvraag inmiddels was verwijderd, dan liep het hele dashboard vast met een foutmelding. Niet alleen die ene regel, maar het volledige overzicht was daardoor onbereikbaar.

Zulke adviesvragen worden nu niet meer in de inbox getoond. Dat is ook logisch: de bijbehorende aanvraag bestaat niet meer, dus er valt niets meer te openen of te beantwoorden. De rest van de inbox werkt weer gewoon.

---

### Aanvraag zonder gekoppelde organisatie liet het scherm vastlopen

**Voor wie:** Organisatoren

Opende een organisator een aanvraag die niet aan een organisatie gekoppeld is, dan liep de pagina vast met een foutmelding in plaats van dat er iets zinnigs werd getoond. Dat is opgelost. Zo'n aanvraag is voor een organisator niet toegankelijk, en dat wordt nu netjes afgehandeld in plaats van met een storing.

---

## 🔒 Beveiliging

**Voor wie:** Iedereen, maar er is niets zichtbaars aan

Een aantal externe softwarecomponenten dat Eventloket gebruikt, is bijgewerkt naar een versie zonder bekende kwetsbaarheden. Het gaat om onderdelen voor het maken van PDF-bestanden, het versturen van verzoeken naar andere systemen en het bouwen van de schermopmaak.

Deze updates lossen meldingen op die door de beveiligingscontrole van de broncode zijn gesignaleerd. Er zijn geen aanwijzingen dat er misbruik van is gemaakt. Aan de werking van de applicatie verandert niets.

---

## 📱 Wat moet je doen?

**Niets!** De verbeteringen werken automatisch na de update.

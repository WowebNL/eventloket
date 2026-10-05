# Eventloket versie 1.3.5: wat is er nieuw?

**Releasedatum:** 5 oktober 2026

---

Deze versie lost één probleem op met de manier waarop Eventloket de datums van een evenement bewaarde nadat er in het zaaksysteem iets aan de zaak was gewijzigd. Er zijn geen nieuwe functies en er verandert niets aan de manier van werken.

---

## 🐛 Opgelost probleem

### Datums van een evenement werden in een andere schrijfwijze bewaard

**Voor wie:** Gemeentemedewerkers, behandelaars en adviseurs

Werd er in het zaaksysteem een gegeven van een zaak bijgewerkt, bijvoorbeeld de begin- of einddatum van het evenement, dan nam Eventloket die datum over in een andere schrijfwijze dan het zelf gebruikt. Daardoor kon een evenement in de kalender, in het filter op periode in de lijstweergave of bij de controle op overlappende evenementen verkeerd worden meegenomen. De zaak zelf en de gegevens erin bleven gewoon bestaan.

Eventloket zet die datums nu altijd om naar de eigen schrijfwijze, ook bij doorkomstzaken. Evenementen waarbij dit al was gebeurd, worden bij deze update in één keer hersteld.

---

## 📱 Wat moet je doen?

**Niets.** De datums worden bij deze update in één keer goedgezet, en nieuwe wijzigingen uit het zaaksysteem worden voortaan in de juiste schrijfwijze bewaard. Er zijn geen gegevens verloren gegaan.

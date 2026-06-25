# Versie 0.6.6 — Wat is er nieuw?

**Releasedatum:** 15 juni 2026

---

## 🐛 Opgeloste problemen

### Adviseurs ontvangen geen meldingen meer voor concept- of afgeronde adviesvragen

**Voor wie:** Adviseurs, Gemeentemedewerkers, Behandelaars

Adviseurs die gekoppeld waren aan een adviesvraag konden meldingen ontvangen over nieuwe of bijgewerkte documenten en berichten, ook wanneer de adviesvraag nog in concept stond of juist al was afgerond. Een conceptadviesvraag is nog niet naar de adviesdienst verstuurd en een afgeronde adviesvraag vraagt geen actie meer, dus in beide gevallen zijn meldingen ongewenst.

Adviseurs ontvangen nu uitsluitend meldingen zolang de adviesvraag actief is. Voor conceptadviesvragen en afgeronde adviesvragen (akkoord, akkoord met voorwaarden, afgewezen of geen reactie) worden geen document- en berichtmeldingen meer verstuurd. Dit sluit aan op de bestaande regel dat een adviseur een zaak pas ziet zodra de adviesvraag niet langer in concept staat.

---

### Geüploade documenten zonder bestandsextensie zijn weer te openen

**Voor wie:** Organisatoren, Gemeentemedewerkers, Behandelaars

Bestanden die werden geüpload zonder een extensie in de bestandsnaam (bijvoorbeeld "plattegrond" in plaats van "plattegrond.pdf") werden zonder extensie opgeslagen. Daardoor konden deze documenten daarna niet meer worden geopend of gedownload.

Dit is op twee manieren opgelost:

- **Bij het uploaden** wordt nu automatisch de juiste extensie afgeleid uit het bestandstype en aan de bestandsnaam toegevoegd, zowel bij een nieuwe upload als bij een nieuwe versie van een bestaand document (ook vanuit het berichtenscherm).
- **Voor reeds opgeslagen documenten** wordt bij het openen of downloaden de extensie alsnog op het juiste bestandstype afgeleid, zodat ook eerder opgeslagen bestanden zonder extensie weer correct te openen zijn.

---

## 🔧 Technische verbeteringen

### Beveiligingsupdate van onderliggende ontwikkelpakketten

**Voor wie:** Beheerders

Enkele onderliggende ontwikkel- en bouwpakketten zijn bijgewerkt om bekende beveiligingswaarschuwingen op te lossen. Dit betreft uitsluitend gereedschap dat tijdens het bouwen van de applicatie wordt gebruikt en heeft geen invloed op de werking voor eindgebruikers.

---

## 📱 Wat moet je doen?

### Voor eindgebruikers
**Niets!** Alle wijzigingen werken direct na de update.

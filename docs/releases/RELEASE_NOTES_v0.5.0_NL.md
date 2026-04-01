# Versie 0.5.0 — Wat is er nieuw?

**Releasedatum:** 1 april 2026

---

## ✨ Nieuwe functionaliteit

### Automatische aanmaak doorkomstzaken voor deelnemende gemeentes

**Voor wie:** Gemeentemedewerkers, Beheerders, Organisatoren

Wanneer een route door meerdere gemeentes loopt, worden nu automatisch deelzaken aangemaakt voor elke gemeente waar de route doorheen gaat. Dit zorgt ervoor dat alle betrokken gemeentes direct geïnformeerd worden en hun eigen zaak hebben om mee te werken.

Beheerders kunnen per gemeente instellen of dit automatisch moet gebeuren en welk zaaktype gebruikt moet worden voor doorkomstzaken. De deelzaken zijn zichtbaar in een apart tabblad bij de hoofdzaak.

---

### Zaken verwijderen (soft delete)

**Voor wie:** Beheerders

Beheerders kunnen nu zaken verwijderen via een soft delete functie. Bij het verwijderen kan gekozen worden om de zaak ook in OpenZaak te verwijderen. Let op: als een zaak in OpenZaak is verwijderd, kan deze niet meer worden hersteld.

Verwijderde zaken zijn niet meer zichtbaar in overzichten, maar blijven wel in de database bewaard (tenzij ook in OpenZaak verwijderd).

---

## 🐛 Opgeloste problemen

### Kaartweergave volledig uitgezoomd bij zaakweergave vanuit kalender

**Voor wie:** Gemeentemedewerkers, Beheerders

Bij het openen van een zaak vanuit de kalenderweergave werd de kaart volledig uitgezoomd weergegeven in plaats van ingezoomd op de juiste locatie. Dit is opgelost: de kaart toont nu altijd het juiste zoom-niveau, ook wanneer de zaak in een modal wordt geopend.

---

### Automatische vertrouwelijkheid instellen bij document upload

**Voor wie:** Organisatoren, Adviseurs, Gemeentemedewerkers

Het handmatig instellen van vertrouwelijkheidsniveaus bij document uploads leidde soms tot verwarring en fouten. Dit is aangepast:

- **Organisator uploads:** Automatisch zaakvertrouwelijk (iedereen bij de zaak ziet het document)
- **Adviesdienst uploads:** Automatisch vertrouwelijk (alleen adviesdienst en behandelaar zien het)
- **Behandelaar uploads:** Handmatige keuze tussen zaakvertrouwelijk, vertrouwelijk en confidentieel

Organisatoren en adviseurs hoeven nu niet meer handmatig een keuze te maken, wat fouten voorkomt.

---

## 📱 Wat moet je doen?

### Voor beheerders
Als je gebruik wilt maken van de automatische doorkomstzaken functionaliteit, moet je per gemeente instellen:
1. Of automatische aanmaak van doorkomstzaken is ingeschakeld
2. Welk zaaktype gebruikt moet worden voor deze doorkomstzaken

Deze instellingen zijn te vinden in de gemeente-instellingen in het beheerportaal. Woweb heeft deze instellingen al toegepast bij deployment.

### Voor eindgebruikers
**Niets!** Alle overige wijzigingen werken direct na de update.

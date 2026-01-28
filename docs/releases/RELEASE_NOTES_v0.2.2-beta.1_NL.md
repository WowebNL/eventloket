# Versie 0.2.2-beta.1 - Wat is er nieuw?

**Releasedatum:** 28 januari 2026

> Dit is een **beta release**. Bedoeld om te testen op de testomgeving; functionaliteit kan nog wijzigen richting de definitieve v0.2.2.

---

## 🔧 Verbeteringen en oplossingen

### Minder notificatie-ruis voor gemeente-beheerders

**Voor wie:** Gemeentemedewerkers / Gemeente-beheerders

Gemeente-beheerders (MunicipalityAdmin) ontvangen niet langer notificaties die bedoeld zijn voor beoordelaars (reviewers) rondom:
- nieuwe documenten bij een zaak
- nieuwe organisatie- en adviesvragen

Dit voorkomt onnodige meldingen en maakt notificaties relevanter.

---

### Betere “Bekijk zaak” links in notificaties

**Voor wie:** Organisatoren, Adviseurs, Gemeentemedewerkers

De “Bekijk zaak” link in notificaties rondom **nieuwe documenten bij een zaak** is betrouwbaarder gemaakt. De link opent nu consistenter in de juiste omgeving/tenant (organisator, adviseur of gemeente), zodat je direct op de juiste zaak uitkomt. Dit lost de 404 fout op.

---

### Gemeente-beheer: lijst met beheerders strikter per gemeente

**Voor wie:** Gemeente-beheerders

De beheerlijst met gemeente-beheerders is strikter gekoppeld aan de eigen gemeente (tenant). Een gemeente-beheerder hoort alleen beheerders van de eigen gemeente te zien.

---

### Kalenderwidget: stabieler wisselen tussen kalender en tabel

**Voor wie:** Alle gebruikers met toegang tot de kalender

Wisselen tussen de **kalenderweergave** en **tabelweergave** is stabieler. De kalender blijft geladen (wordt niet meer telkens “opnieuw opgebouwd”), en bij filterwijzigingen ververst de kalender correct.

---

## 📱 Wat moet je doen?

### Voor eindgebruikers
**Niets!** De verbeteringen worden automatisch toegepast na de update.

---

## ✅ Testinstructie (beta)

- Notificaties (document):
  - Upload een nieuw document bij een zaak.
  - Controleer dat een reviewer de notificatie ontvangt.
  - Controleer dat een gemeente-beheerder **geen** notificatie ontvangt.
  - Open de “Bekijk zaak” link (email/notification) als organisator, adviseur en reviewer en controleer dat je op de juiste zaak uitkomt.

- Notificaties (organisatie of adviesvragen):
  - Start een organisatie of adviesvraag en plaats een bericht.
  - Controleer dat relevante reviewers/ontvangers notificaties ontvangen.
  - Controleer dat een gemeente-beheerder **geen** notificatie ontvangt.

- Gemeente-beheerders:
  - Log in als gemeente-beheerder van Gemeente A en controleer dat je geen beheerders van Gemeente B ziet.

- Kalenderwidget:
  - Wissel meerdere keren tussen “Kalender” en “Tabel”.
  - Stel filters in en controleer dat de kalender ververst en dat filters behouden blijven.

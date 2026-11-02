# Eventloket versie 1.4.1: wat is er nieuw?

**Releasedatum:** 12 oktober 2026

---

Deze versie lost twee problemen met de kaart in het aanvraagformulier op. Een bestaande route of een bestaand vlak verslepen wordt nu goed opgeslagen; dat was het bekende punt uit versie 1.4.0. En bij een nieuwe aanvraag op basis van een eerdere zaak is de kaart weer gewoon zichtbaar. Verder zijn er een paar verbeteringen in de beveiliging waar je als gebruiker niets van merkt.

---

## 🐛 Opgeloste problemen

### Een bestaande route of een bestaand vlak verslepen wordt nu opgeslagen

**Voor wie:** Organisatoren, en gemeenten die vragen krijgen van organisatoren

Stond er bij het openen van een aanvraag al een route of vlak op de kaart, bijvoorbeeld bij een kopie van een eerdere aanvraag, een hervatte aanvraag of een concept dat opnieuw is geladen, en versleepte je die route of dat vlak, dan werd de wijziging niet opgeslagen. Op het scherm verschoof de lijn wel, maar Eventloket hield de oude route vast. Dat was het bekende punt uit versie 1.4.0.

Dit is opgelost. Een aangepaste route of een aangepast vlak wordt nu opgeslagen, en de controle op de locatie en de ingediende aanvraag gebruiken de nieuwe vorm. De tijdelijke oplossing (verwijderen en opnieuw tekenen) is niet meer nodig.

### De kaart bleef grijs bij een nieuwe aanvraag op basis van een eerdere zaak

**Voor wie:** Organisatoren

Start je een nieuwe aanvraag vanuit een bestaande zaak ("Nieuwe aanvraag met deze gegevens") en had die zaak een route of een vlak, dan bleef de kaart bij de stap over de locatie grijs. Er was geen kaart te zien, de route of het vlak was niet zichtbaar en uitzoomen werkte niet. Pas na het opnieuw laden van de pagina deed de kaart het weer. De kaart wordt nu meteen goed getoond, met de route of het vlak van de eerdere zaak erop.

---

## 🔒 Beveiliging

**Voor wie:** Iedereen, maar er is niets zichtbaars aan

* Een aantal onderdelen waarop Eventloket draait, is bijgewerkt naar een versie met beveiligingsoplossingen.
* Meldingen over technische fouten die naar het beheerteam gaan, bevatten nu alleen een korte samenvatting en geen gegevens uit de aanvraag meer.
* Eventloket heeft nu een vaste plek waar staat hoe je een beveiligingsprobleem kunt melden (`/.well-known/security.txt`, volgens de internationale standaard daarvoor).

---

## 📱 Wat moet je doen?

### Voor organisatoren

**Niets.** Je kunt een bestaande route of een bestaand vlak gewoon weer verslepen.

### Voor gemeenten, behandelaars en adviseurs

**Niets.** De verbeteringen werken zodra de update live staat.

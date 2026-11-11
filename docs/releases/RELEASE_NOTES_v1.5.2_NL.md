# Eventloket versie 1.5.2: wat is er nieuw?

**Releasedatum:** 11 november 2026

---

Deze versie bevat vooral beveiligingsverbeteringen. Een adviesdienst houdt altijd minimaal één beheerder: de rol van de laatste beheerder kun je niet meer wijzigen, en de laatste beheerder of jezelf kun je niet meer ontkoppelen. Alleen beheerders van de adviesdienst waarin je werkt beheren de gebruikers van die adviesdienst. Bij het toevoegen van documenten controleert Eventloket strenger of het bestand echt is geüpload en of het document bij de zaak hoort. Verder zitten er beveiligingsupdates van onderliggende onderdelen in. Formulieren, schermen en werkwijzen voor organisatoren en gemeenten blijven gelijk.

---

## 🔄 Wat is er weg of anders

### De laatste beheerder van een adviesdienst blijft beheerder

**Voor wie:** Beheerders van een adviesdienst, beheerders van Eventloket

Een adviesdienst houdt altijd minimaal één beheerder. Is iemand de enige beheerder, dan staat het keuzeveld voor de rol van die gebruiker uit, in het adviseurspaneel en in het beheerpaneel. Maak je eerst een tweede gebruiker beheerder, dan kun je de rol van de eerste daarna wel wijzigen.

### Ontkoppelen van gebruikers bij een adviesdienst

**Voor wie:** Beheerders van een adviesdienst, beheerders van Eventloket

Bij het ontkoppelen van gebruikers gelden nu drie regels:

* Jezelf ontkoppelen kan niet meer. Bij je eigen regel staat de knop "Ontkoppelen" niet meer.
* De laatste beheerder van een adviesdienst kun je niet ontkoppelen.
* Een gebruiker die maar bij één adviesdienst hoort, kun je niet ontkoppelen. Dat was al zo.

Selecteer je meerdere gebruikers tegelijk, dan ontkoppelt Eventloket de gebruikers die mogen en slaat het de rest over. Je krijgt een melding hoeveel gebruikers zijn overgeslagen en waarom. Ook als je in één keer alle beheerders selecteert, blijft er één beheerder over.

### Meerdere gebruikers tegelijk verwijderen

**Voor wie:** Beheerders van een adviesdienst

Verwijder je meerdere gebruikers tegelijk, dan geldt voor elke geselecteerde gebruiker dezelfde controle als bij het verwijderen van één gebruiker. Je eigen account zit daar nooit bij.

### Gebruikers beheren alleen in je eigen adviesdienst

**Voor wie:** Adviseurs die bij meer dan één adviesdienst horen

Ben je beheerder van de ene adviesdienst en gewone adviseur bij een andere, dan zag je tot nu toe ook bij die andere adviesdienst de lijst met gebruikers. Nu zie en beheer je de gebruikers alleen bij de adviesdienst waar je zelf beheerder bent.

### Strengere controle bij het toevoegen van documenten

**Voor wie:** Organisatoren, behandelaars en adviseurs

Bij het toevoegen van een document of een nieuwe versie van een document, bij een zaak of in een bericht, controleert Eventloket nu of het bestand echt in dat formulier is geüpload. Een nieuwe versie kan alleen worden toegevoegd aan een document dat bij de zaak hoort. Bij normaal gebruik merk je hier niets van. Lukt het uploaden niet, dan krijg je bij het bestandsveld de melding dat het uploaden is mislukt, en kun je het bestand opnieuw kiezen.

---

## 🔧 Onder de motorkap

* Beveiligingsupdates in onderdelen die Eventloket gebruikt om verzoeken te verwerken en schermen te tonen. Voor gebruikers verandert hier niets aan.
* Bijgewerkte onderdelen die alleen bij het ontwikkelen worden gebruikt. Die draaien niet op productie.
* Onderhoud aan de repository en de documentatie voor ontwikkelaars, zonder gevolgen voor de applicatie.

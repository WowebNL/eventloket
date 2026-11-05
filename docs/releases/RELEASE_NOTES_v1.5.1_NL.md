# Eventloket versie 1.5.1: wat is er nieuw?

**Releasedatum:** nog te bepalen

---

Staat bij een koppeling het tabblad bestanden uit, dan kan een koppelingbeheerder met een nieuwe schakelaar adviesdiensten met inzage in alle zaken de documenten toch laten inzien, zonder dat ze iets kunnen toevoegen. Zonder die schakelaar wordt het uitzetten van het tabblad strenger: adviesdiensten openen documenten van zo'n zaak dan alleen nog via hun eigen adviesvraag of via een besluit. Staat het tabblad bestanden aan, dan verandert er niets. Verder krijgt een bijlage in een bericht weer de naam van het bestand als titel, en krijg je een duidelijke melding als een e-mailadres al in gebruik is. Organisatoren zien in de lijst met zaken de kolommen "Zaaktype" en "Behandelaar" niet meer, en er zitten beveiligingsupdates in.

---

## ✨ Nieuwe functies

### Documenten alleen inzien voor adviesdiensten als het tabblad bestanden uit staat

**Voor wie:** Koppelingbeheerders, adviesdiensten die alle zaken mogen inzien

Staat het tabblad bestanden bij een koppeling aan, dan verandert er niets: adviesdiensten zien de bestanden zoals ze dat nu doen. Staat het tabblad uit, dan zien adviesdiensten het tabblad niet. Wil je dat adviesdiensten met inzage in alle zaken de documenten dan toch kunnen lezen, dan zet je bij de instellingen van de koppeling de nieuwe schakelaar aan: "Bestanden tonen aan adviesdiensten met inzage in alle zaken (alleen lezen)". Die adviesdiensten zien dan bij zaken van die koppeling het tabblad bestanden. Ze kunnen de bestanden bekijken en downloaden, ook als zip, maar ze kunnen geen bestanden toevoegen en geen nieuwe versie maken.

* De schakelaar is alleen te kiezen als het tabblad bestanden uit staat. Zet je het tabblad bestanden weer aan, dan gaat de schakelaar vanzelf uit en zien adviesdiensten de bestanden weer zoals voorheen.
* De instelling geldt voor elke adviesdienst die alle zaken mag inzien, ook als die adviesdienst niet aan de gemeente gekoppeld is.
* Welke documenten een adviesdienst ziet, hangt nog steeds af van de maximale vertrouwelijkheid die voor adviseurs is ingesteld.
* Standaard staat de schakelaar uit. Er verandert dus niets tot een gemeente hem aanzet.

---

## 🔄 Wat is er weg of anders

### Adviesdiensten en documenten als het tabblad bestanden uit staat

**Voor wie:** Adviesdiensten, gemeentebeheerders

Staat bij een koppeling het tabblad bestanden uit, dan openen adviesdiensten zonder leestoegang tot de bestanden een document van zo'n zaak alleen nog:

* als bijlage in hun eigen adviesvraag (zolang het tabblad adviesvragen aan staat), of
* als document bij een besluit (zolang het tabblad besluiten aan staat).

Dat geldt ook voor een link naar een document die eerder is opgeslagen of gedeeld. Tot nu toe was het uitzetten van het tabblad vooral een keuze in het scherm; nu bepaalt het ook echt wat een adviesdienst mag openen. Moet een adviesdienst de documenten wel kunnen lezen, gebruik dan de nieuwe schakelaar hierboven.

### Minder kolommen in de lijst met zaken voor organisatoren

**Voor wie:** Organisatoren

In de lijst met zaken, en in de lijstweergave van de kalender, zien organisatoren de kolommen "Zaaktype" en "Behandelaar" niet meer. Welk soort aanvraag een zaak is, staat in de kolom "Aanvraagtype". Omdat de kolom "Behandelaar" weg is, kun je ook niet meer zoeken op de naam van de behandelaar.

---

## 🐛 Opgeloste problemen

### Bijlage in een bericht kreeg een willekeurige titel

**Voor wie:** Organisatoren, behandelaars en adviseurs

Voegde je in een bericht een nieuw bestand toe via "Bestand bijvoegen", dan werd de titel vooraf ingevuld met een willekeurige tijdelijke naam (bijvoorbeeld `phpa53aqmi26kamfrpWBnk`) in plaats van de naam van het bestand. Nu staat de bestandsnaam zonder extensie als titel klaar. Heb je zelf al een titel ingevuld, dan blijft die staan.

Documenten die eerder met zo'n tijdelijke naam zijn opgeslagen, houden die titel. Die worden met deze versie niet aangepast.

### Melding bij een e-mailadres dat al in gebruik is

**Voor wie:** Organisatoren, gemeentebeheerders en iedereen die een account aanmaakt of wijzigt

Gebruikte je bij het registreren, in je profiel, bij het accepteren van een uitnodiging of bij het beheren van gebruikers een e-mailadres dat al bij een ander account hoort, maar met andere hoofdletters (bijvoorbeeld `Naam@Voorbeeld.nl` tegenover `naam@voorbeeld.nl`), dan kreeg je een foutpagina. Nu krijg je de gewone melding dat het e-mailadres al in gebruik is.

---

## 🔧 Onder de motorkap

* Beveiligingsupdates in onderdelen die Eventloket gebruikt om verzoeken te verwerken en schermen te tonen. Voor gebruikers verandert hier niets aan.
* Bijgewerkte onderdelen die alleen bij het bouwen van de applicatie worden gebruikt, met beveiligingsupdates. Voor gebruikers verandert hier niets aan.
* Openstaande uitnodigingen worden voortaan met een e-mailadres in kleine letters opgeslagen.

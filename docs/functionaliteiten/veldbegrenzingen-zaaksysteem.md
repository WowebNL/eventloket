# Veldbegrenzingen richting het zaaksysteem

Versie: 1.0
<br>Datum: 24-08-2026
<br>Door: Michel Verhoeven

Dit document legt uit waarom sommige antwoorden uit het aanvraagformulier verkort of helemaal niet in het zaaksysteem terechtkomen, welke velden dat precies zijn en wat een behandelaar daarvan merkt. Het is bedoeld voor functioneel beheerders en behandelaars die zich afvragen waarom een naam in Open Zaak of OneGround korter is dan in Eventloket, of waarom een adres of KvK-nummer ontbreekt.

De inrichting van een koppeling staat in [ZGW-koppelingbeheer](zgw-koppelingbeheer.md). De afwijkingen die specifiek voor OneGround gelden staan in [OneGround aandachtspunten](oneground-aandachtspunten.md).

---

## Waarom Eventloket velden begrenst

De ZGW-standaard legt op vrijwel elk tekstveld een maximale lengte vast. Het aanvraagformulier is ruimer dan die maxima: op zijn langste tekstvelden accepteert het tot 1000 tekens. Een organisator kan dus een antwoord invullen dat het zaaksysteem niet accepteert.

Dat wordt niet één veld dat mislukt, maar de hele registratie. De aanvrager (de "rol" bij de zaak) wordt in één bericht naar het zaaksysteem gestuurd. Is één veld in dat bericht te lang, dan weigert het zaaksysteem het complete bericht met een foutmelding. De zaak bestaat dan wel, maar zonder aanvrager, en alles wat daarna in de rij stond (de locatiegegevens, de adresobjecten, de doorkomstzaken, het onleesbaar maken van identificerende gegevens) draait ook niet meer. Eén te lang antwoord in één veld kostte zo de hele aanmelding.

Eventloket brengt de waarden daarom vóór verzending binnen de grenzen van het schema. Dat gebeurt op twee manieren: **afkappen** of **weglaten**.

---

## Wat wordt afgekapt

Een veld wordt afgekapt wanneer een verkorte waarde nog steeds bruikbaar is. Een ingekorte naam is nog herkenbaar, en dat is beter dan een aanvraag die helemaal niet registreert. Een waarde die precies op de grens zit blijft ongewijzigd.

| Gegeven uit de aanvraag | Veld in het zaaksysteem | Maximum |
| --- | --- | --- |
| Achternaam | `betrokkeneIdentificatie.geslachtsnaam` | 200 |
| Voornaam | `betrokkeneIdentificatie.voornamen` | 200 |
| Voornaam en achternaam samen | `afwijkendeNaamBetrokkene` | 625 |
| Organisatienaam (vestiging) | `betrokkeneIdentificatie.handelsnaam` | 625 |
| Organisatienaam (eigen zaaksysteem) | `betrokkeneIdentificatie.statutaireNaam` | 500 |
| Naam contactpersoon | `contactpersoonRol.naam` | 40 of 200, zie hieronder |
| Telefoonnummer contactpersoon | `contactpersoonRol.telefoonnummer` | 20 |
| Woonplaats | `verblijfsadres.wplWoonplaatsNaam` | 80 |
| Straatnaam | `verblijfsadres.gorOpenbareRuimteNaam` | 80 |

Woonplaats en straatnaam zijn verplicht binnen een adres. Ze worden daarom afgekapt en niet weggelaten, want zonder die twee is het adres als geheel ongeldig.

### De naam van de contactpersoon: 40 of 200

Voor `contactpersoonRol.naam` verschillen de gepubliceerde schema's van elkaar. De release 1.5.1 in de VNG-repository `zaken-api` noemt een maximum van 200 tekens; de GEMMA-publicatie van dezelfde standaard (`gemma-zaken`, versies 1.6.0 en 1.7.0) noemt 40 tekens. Beide zijn officiële publicaties van dezelfde standaard.

Eventloket kiest daarom per koppeling. Op een koppeling die als OneGround is gemarkeerd wordt 40 aangehouden, omdat die zaaksystemen de striktere lezing afdwingen. Op alle andere koppelingen, waaronder de gedeelde Open Zaak, wordt 200 aangehouden, zodat daar geen namen worden ingekort voor een grens die dat systeem niet stelt. Zet het vinkje **Dit is een OneGround koppeling** dus goed; het bepaalt mede hoeveel van een naam doorkomt.

---

## Wat wordt weggelaten

Sommige gegevens betekenen alleen iets als ze compleet zijn. Een half KvK-nummer is het nummer van een ander bedrijf, een afgekapte postcode wijst naar een andere plaats, en een afgekapt e-mailadres is of geen adres meer of de mailbox van iemand anders. Bij zulke velden is een gedeeltelijke waarde schadelijker dan geen waarde, dus laat Eventloket ze weg.

| Gegeven | Wordt weggelaten wanneer | Gevolg |
| --- | --- | --- |
| KvK-nummer | langer dan 8 tekens, of al onleesbaar gemaakt | de aanvrager wordt alleen op de organisatienaam geregistreerd |
| E-mailadres contactpersoon | langer dan 254 tekens | de contactpersoon staat zonder e-mailadres bij de zaak |
| Postcode | langer dan 7 tekens | het adres komt zonder postcode mee |
| Huisletter | langer dan 1 teken | het adres komt zonder huisletter mee |
| Huisnummertoevoeging | langer dan 4 tekens | het adres komt zonder toevoeging mee |
| Het hele verblijfsadres | huisnummer niet numeriek, of hoger dan 99999 | er komt geen adres bij de aanvrager te staan |

Dat laatste geval verdient toelichting. Het huisnummer is binnen een adres verplicht en het schema staat er alleen een getal tot en met 99999 toe, terwijl het formulier een langere invoer accepteert. Een adres dat geen geldig huisnummer kan aanleveren kan dus niet worden verstuurd, en dan is het hele adres weglaten de enige manier om de aanvraag toch te registreren.

---

## Wat een behandelaar hiervan merkt

De organisator krijgt geen melding: zijn aanvraag komt gewoon binnen en in Eventloket staan zijn antwoorden onverkort. Het verkorten of weglaten gebeurt alleen op het bericht naar het zaaksysteem.

In het zaaksysteem kan een behandelaar daardoor tegenkomen:

- een naam die korter is dan in Eventloket;
- een aanvrager zonder e-mailadres, KvK-nummer of adres;
- een adres zonder postcode, huisletter of huisnummertoevoeging.

De volledige, onverkorte gegevens staan altijd in Eventloket zelf en in de aanvraag-PDF bij de zaak. Kom je zoiets tegen, raadpleeg dan die bron in plaats van het zaaksysteem.

Merk je dit vaker bij hetzelfde veld, dan is dat een signaal dat het formulier op dat veld een strakkere invoerbegrenzing zou moeten krijgen, zodat de organisator het meteen ziet in plaats van dat het stil wordt ingekort.

# Gedragsspecificatie evenementformulier

_Automatisch gegenereerd op 22-04-2026 10:44 via `php artisan eventform:gedrags-rapport`._

**Samenvatting:** ✅ Alle scenarios slagen — 48 geslaagd, 0 gefaald, 48 totaal.

Dit document beschrijft in mensentaal hoe het evenementformulier zich gedraagt, gegroepeerd per pagina. Elke beschrijving is gekoppeld aan een geautomatiseerde test die het gedrag bewijst — ✅ betekent: de Filament-versie reageert exact zoals Open Forms zou doen onder dezelfde omstandigheden. ❌ betekent: er is een afwijking die onderzocht moet worden.

---

## Inhoudsopgave

- ✅ [Stap 1: Contactgegevens](#stap-1-contactgegevens) — 1 scenario
- ✅ [Stap 10: Vergunningaanvraag: kenmerken](#stap-10-vergunningaanvraag-kenmerken) — 1 scenario
- ✅ [Stap 12: Vergunningsaanvraag: voorwerpen](#stap-12-vergunningsaanvraag-voorwerpen) — 1 scenario
- ✅ [Pagina-overstijgend gedrag](#pagina-overstijgend-gedrag) — 45 scenarios

---

## Stap 1: Contactgegevens

_Conditionele zichtbaarheid van velden en stappen_ — Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ KvK-gebruiker — adresgegevens verborgen

Gebruiker ingelogd via eHerkenning/KvK heeft de organisatie-gegevens al uit de KvK-koppeling. "Organisatie-informatie" wordt zichtbaar om de opgehaalde gegevens te tonen; "Adresgegevens" wordt verborgen omdat het adres al bekend is.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `eventloketSession.kvk` = "12345678"

**Dan verwachten we:**
- Veld "Organisatie informatie" _(op Stap 1: Contactgegevens)_ wordt **zichtbaar**
- Veld "Adresgegevens" _(op Stap 1: Contactgegevens)_ wordt **verborgen**

---

## Stap 10: Vergunningaanvraag: kenmerken

_Conditionele zichtbaarheid van velden en stappen_ — Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ Bouwsels >10 m² — velden en stap zichtbaar na aanvinken

Als de organisator bij "wat is van toepassing voor uw evenement" de optie A3 (bouwsels groter dan 10 m²) aanvinkt, moeten de vervolg-velden zichtbaar worden en wordt de stap "Vergunningsaanvraag: extra activiteiten" in de sidebar actief.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan wat van toepassing is voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Bouwsels plaatsen groter dan 10m2, zoals tenten of podia" aangevinkt

**Dan verwachten we:**
- Veld "Bouwsels > 10m<sup>2</sup> " _(op Stap 10: Vergunningaanvraag: kenmerken)_ wordt **zichtbaar**
- Veld "Wat voor bouwsels plaats u op de locaties?" _(op Stap 10: Vergunningaanvraag: kenmerken)_ wordt **zichtbaar**
- Stap 10: Vergunningaanvraag: kenmerken wordt **van toepassing** (getoond in sidebar)

---

## Stap 12: Vergunningsaanvraag: voorwerpen

_Conditionele zichtbaarheid van velden en stappen_ — Veel velden in het formulier zijn pas relevant als de organisator een specifieke keuze maakt op een ander veld. Dezelfde logica activeert soms ook een volledige stap in de wizard-sidebar. Een fout hier betekent dat de gebruiker velden niet ziet die gevraagd zouden moeten worden, of velden ziet die nu niet van toepassing zijn — beide leiden tot onvolledige of verwarrende aanvragen.

### ✅ Speeltoestellen — voorwerpen-stap van toepassing na A25

Als de organisator aangeeft speeltoestellen te plaatsen (optie A25 in "welke voorwerpen gaat u plaatsen"), moeten "Speeltoestellen" en "voorwerpen" zichtbaar zijn én wordt de stap "Vergunningsaanvraag: voorwerpen" actief.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke voorwerpen gaat u plaatsen bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Speeltoestellen Attractietoestellen" aangevinkt

**Dan verwachten we:**
- Veld "Speeltoestellen" _(op Stap 12: Vergunningsaanvraag: voorwerpen)_ wordt **zichtbaar**
- Veld "Voorwerpen" _(op Stap 12: Vergunningsaanvraag: voorwerpen)_ wordt **zichtbaar**
- Stap 12: Vergunningsaanvraag: voorwerpen wordt **van toepassing** (getoond in sidebar)

---

## Pagina-overstijgend gedrag

Gedrag dat niet aan één specifieke pagina gekoppeld is — routering, afgeleide berekeningen, service-uitwisseling met externe systemen.

_Registratie-backend per gemeente en aanvraagsoort_ — Elke nieuwe zaak wordt gerouteerd naar één van 45 registratie-backends. Welke backend krijgt een zaak hangt af van twee dingen: de gemeente waar het evenement plaatsvindt (herkend via de BRK-code) en de aanvraagsoort die de organisator kiest (vergunning, vooraankondiging, of melding). 

15 deelnemende gemeentes × 3 aanvraagsoorten = 45 combinaties. Elke afwijking hier betekent dat zaken in het verkeerde doel-systeem terechtkomen — dus moet elke combinatie exact matchen met de OF-configuratie.

### ✅ GM0882 + vergunning → backend23

Voor gemeente GM0882 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend23' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0882"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend23"

### ✅ GM0882 + vooraankondiging → backend22

Voor gemeente GM0882 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend22' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0882"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend22"

### ✅ GM0882 + melding → backend24

Voor gemeente GM0882 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend24' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0882"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend24"

### ✅ GM0888 + vergunning → backend3

Voor gemeente GM0888 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend3' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0888"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend3"

### ✅ GM0888 + vooraankondiging → backend9

Voor gemeente GM0888 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend9' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0888"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend9"

### ✅ GM0888 + melding → backend8

Voor gemeente GM0888 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend8' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0888"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend8"

### ✅ GM0899 + vergunning → backend15

Voor gemeente GM0899 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend15' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0899"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend15"

### ✅ GM0899 + vooraankondiging → backend14

Voor gemeente GM0899 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend14' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0899"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend14"

### ✅ GM0899 + melding → backend13

Voor gemeente GM0899 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend13' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0899"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend13"

### ✅ GM0917 + vergunning → backend1

Voor gemeente GM0917 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend1' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0917"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend1"

### ✅ GM0917 + vooraankondiging → backend4

Voor gemeente GM0917 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend4' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0917"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend4"

### ✅ GM0917 + melding → backend6

Voor gemeente GM0917 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend6' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0917"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend6"

### ✅ GM0928 + vergunning → backend21

Voor gemeente GM0928 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend21' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0928"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend21"

### ✅ GM0928 + vooraankondiging → backend20

Voor gemeente GM0928 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend20' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0928"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend20"

### ✅ GM0928 + melding → backend19

Voor gemeente GM0928 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend19' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0928"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend19"

### ✅ GM0938 + vergunning → backend26

Voor gemeente GM0938 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend26' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0938"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend26"

### ✅ GM0938 + vooraankondiging → backend25

Voor gemeente GM0938 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend25' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0938"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend25"

### ✅ GM0938 + melding → backend27

Voor gemeente GM0938 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend27' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0938"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend27"

### ✅ GM0965 + vergunning → backend29

Voor gemeente GM0965 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend29' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0965"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend29"

### ✅ GM0965 + vooraankondiging → backend28

Voor gemeente GM0965 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend28' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0965"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend28"

### ✅ GM0965 + melding → backend30

Voor gemeente GM0965 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend30' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0965"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend30"

### ✅ GM0971 + vergunning → backend35

Voor gemeente GM0971 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend35' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0971"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend35"

### ✅ GM0971 + vooraankondiging → backend34

Voor gemeente GM0971 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend34' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0971"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend34"

### ✅ GM0971 + melding → backend36

Voor gemeente GM0971 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend36' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0971"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend36"

### ✅ GM0981 + vergunning → backend38

Voor gemeente GM0981 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend38' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0981"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend38"

### ✅ GM0981 + vooraankondiging → backend37

Voor gemeente GM0981 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend37' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0981"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend37"

### ✅ GM0981 + melding → backend39

Voor gemeente GM0981 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend39' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0981"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend39"

### ✅ GM0986 + vergunning → backend44

Voor gemeente GM0986 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend44' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0986"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend44"

### ✅ GM0986 + vooraankondiging → backend43

Voor gemeente GM0986 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend43' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0986"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend43"

### ✅ GM0986 + melding → backend45

Voor gemeente GM0986 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend45' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0986"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend45"

### ✅ GM0994 + vergunning → backend41

Voor gemeente GM0994 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend41' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0994"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend41"

### ✅ GM0994 + vooraankondiging → backend40

Voor gemeente GM0994 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend40' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0994"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend40"

### ✅ GM0994 + melding → backend42

Voor gemeente GM0994 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend42' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0994"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend42"

### ✅ GM1729 + vergunning → backend2

Voor gemeente GM1729 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend2' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1729"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend2"

### ✅ GM1729 + vooraankondiging → backend5

Voor gemeente GM1729 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend5' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1729"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend5"

### ✅ GM1729 + melding → backend7

Voor gemeente GM1729 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend7' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1729"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend7"

### ✅ GM1883 + vergunning → backend32

Voor gemeente GM1883 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend32' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1883"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend32"

### ✅ GM1883 + vooraankondiging → backend31

Voor gemeente GM1883 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend31' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1883"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend31"

### ✅ GM1883 + melding → backend33

Voor gemeente GM1883 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend33' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1883"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend33"

### ✅ GM1903 + vergunning → backend18

Voor gemeente GM1903 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend18' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1903"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend18"

### ✅ GM1903 + vooraankondiging → backend17

Voor gemeente GM1903 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend17' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1903"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend17"

### ✅ GM1903 + melding → backend16

Voor gemeente GM1903 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend16' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1903"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend16"

### ✅ GM1954 + vergunning → backend10

Voor gemeente GM1954 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend10' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1954"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend10"

### ✅ GM1954 + vooraankondiging → backend12

Voor gemeente GM1954 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend12' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1954"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend12"

### ✅ GM1954 + melding → backend11

Voor gemeente GM1954 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend11' routeren.

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1954"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend11"

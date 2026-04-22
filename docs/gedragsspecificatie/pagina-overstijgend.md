# Pagina-overstijgend gedrag

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 45/45 gedekt.

Dit bestand verzamelt gedrag dat niet aan één specifieke pagina gekoppeld is: routering naar registratie-backends, afgeleide berekeningen die van meerdere pagina's tegelijk afhangen, en service-uitwisseling met externe systemen.

## Registratie-backend per gemeente en aanvraagsoort

Elke nieuwe zaak wordt gerouteerd naar één van 45 registratie-backends. Welke backend krijgt een zaak hangt af van twee dingen: de gemeente waar het evenement plaatsvindt (herkend via de BRK-code) en de aanvraagsoort die de organisator kiest (vergunning, vooraankondiging, of melding). 

15 deelnemende gemeentes × 3 aanvraagsoorten = 45 combinaties. Elke afwijking hier betekent dat zaken in het verkeerde doel-systeem terechtkomen — dus moet elke combinatie exact matchen met de OF-configuratie.

### ✅ GM0882 + vergunning → backend23

Voor gemeente GM0882 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend23' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0882"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend23"

### ✅ GM0882 + vooraankondiging → backend22

Voor gemeente GM0882 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend22' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0882"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend22"

### ✅ GM0882 + melding → backend24

Voor gemeente GM0882 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend24' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0882"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend24"

### ✅ GM0888 + vergunning → backend3

Voor gemeente GM0888 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend3' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0888"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend3"

### ✅ GM0888 + vooraankondiging → backend9

Voor gemeente GM0888 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend9' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0888"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend9"

### ✅ GM0888 + melding → backend8

Voor gemeente GM0888 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend8' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0888"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend8"

### ✅ GM0899 + vergunning → backend15

Voor gemeente GM0899 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend15' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0899"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend15"

### ✅ GM0899 + vooraankondiging → backend14

Voor gemeente GM0899 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend14' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0899"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend14"

### ✅ GM0899 + melding → backend13

Voor gemeente GM0899 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend13' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0899"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend13"

### ✅ GM0917 + vergunning → backend1

Voor gemeente GM0917 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend1' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0917"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend1"

### ✅ GM0917 + vooraankondiging → backend4

Voor gemeente GM0917 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend4' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0917"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend4"

### ✅ GM0917 + melding → backend6

Voor gemeente GM0917 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend6' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0917"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend6"

### ✅ GM0928 + vergunning → backend21

Voor gemeente GM0928 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend21' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0928"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend21"

### ✅ GM0928 + vooraankondiging → backend20

Voor gemeente GM0928 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend20' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0928"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend20"

### ✅ GM0928 + melding → backend19

Voor gemeente GM0928 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend19' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0928"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend19"

### ✅ GM0938 + vergunning → backend26

Voor gemeente GM0938 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend26' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0938"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend26"

### ✅ GM0938 + vooraankondiging → backend25

Voor gemeente GM0938 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend25' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0938"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend25"

### ✅ GM0938 + melding → backend27

Voor gemeente GM0938 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend27' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0938"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend27"

### ✅ GM0965 + vergunning → backend29

Voor gemeente GM0965 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend29' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0965"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend29"

### ✅ GM0965 + vooraankondiging → backend28

Voor gemeente GM0965 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend28' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0965"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend28"

### ✅ GM0965 + melding → backend30

Voor gemeente GM0965 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend30' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0965"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend30"

### ✅ GM0971 + vergunning → backend35

Voor gemeente GM0971 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend35' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0971"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend35"

### ✅ GM0971 + vooraankondiging → backend34

Voor gemeente GM0971 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend34' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0971"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend34"

### ✅ GM0971 + melding → backend36

Voor gemeente GM0971 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend36' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0971"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend36"

### ✅ GM0981 + vergunning → backend38

Voor gemeente GM0981 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend38' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0981"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend38"

### ✅ GM0981 + vooraankondiging → backend37

Voor gemeente GM0981 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend37' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0981"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend37"

### ✅ GM0981 + melding → backend39

Voor gemeente GM0981 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend39' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0981"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend39"

### ✅ GM0986 + vergunning → backend44

Voor gemeente GM0986 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend44' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0986"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend44"

### ✅ GM0986 + vooraankondiging → backend43

Voor gemeente GM0986 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend43' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0986"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend43"

### ✅ GM0986 + melding → backend45

Voor gemeente GM0986 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend45' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0986"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend45"

### ✅ GM0994 + vergunning → backend41

Voor gemeente GM0994 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend41' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0994"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend41"

### ✅ GM0994 + vooraankondiging → backend40

Voor gemeente GM0994 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend40' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0994"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend40"

### ✅ GM0994 + melding → backend42

Voor gemeente GM0994 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend42' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM0994"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend42"

### ✅ GM1729 + vergunning → backend2

Voor gemeente GM1729 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend2' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1729"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend2"

### ✅ GM1729 + vooraankondiging → backend5

Voor gemeente GM1729 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend5' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1729"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend5"

### ✅ GM1729 + melding → backend7

Voor gemeente GM1729 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend7' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1729"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend7"

### ✅ GM1883 + vergunning → backend32

Voor gemeente GM1883 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend32' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1883"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend32"

### ✅ GM1883 + vooraankondiging → backend31

Voor gemeente GM1883 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend31' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1883"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend31"

### ✅ GM1883 + melding → backend33

Voor gemeente GM1883 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend33' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1883"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend33"

### ✅ GM1903 + vergunning → backend18

Voor gemeente GM1903 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend18' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1903"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend18"

### ✅ GM1903 + vooraankondiging → backend17

Voor gemeente GM1903 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend17' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1903"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend17"

### ✅ GM1903 + melding → backend16

Voor gemeente GM1903 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend16' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1903"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend16"

### ✅ GM1954 + vergunning → backend10

Voor gemeente GM1954 bij een vergunningaanvraag (volledige evenementenvergunning) moet het systeem de zaak naar registratie-backend 'backend10' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1954"
- Veld `isVergunningaanvraag` = **ja**

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend10"

### ✅ GM1954 + vooraankondiging → backend12

Voor gemeente GM1954 bij een vooraankondiging (alleen aankondiging, nog geen vergunning) moet het systeem de zaak naar registratie-backend 'backend12' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1954"
- Veld "Waarvoor wilt u Eventloket gebruiken?" = "vooraankondiging" (_U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in_)

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend12"

### ✅ GM1954 + melding → backend11

Voor gemeente GM1954 bij een melding (lichter regime, geen wegafsluiting) moet het systeem de zaak naar registratie-backend 'backend11' routeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld `evenementInGemeente.brk_identification` = "GM1954"
- Veld "Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer?" = "Nee"

**Dan verwachten we:**
- Systeem-waarde `registration_backend` = "backend11"

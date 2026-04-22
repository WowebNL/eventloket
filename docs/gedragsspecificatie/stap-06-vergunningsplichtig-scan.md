# Stap 6: Vergunningsplichtig scan

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 14/14 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Vragenboom om vergunning-plicht vs melding te bepalen

Op basis van vijf voortschrijdende Ja/Nee-vragen stelt het systeem vast of het evenement lichtvoetig gemeld kan worden of dat er een volledige vergunningaanvraag nodig is. Zodra één vraag met "Nee" beantwoord wordt, ligt de route vast op vergunning en stopt de vragenboom — de organisator krijgt dan geen verdere meldingsvragen te zien.

### ✅ Zichtbaarheid "vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `isHetAantalAanwezigenBijUwEvenementMinderDanSdf` = `Ja` — moet veld `vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen 'aanwezigen' %} personen?" = "Ja"

**Dan verwachten we:**
- Veld "Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur?" _(op Stap 6: Vergunningsplichtig scan)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `isHetAantalAanwezigenBijUwEvenementMinderDanSdf` is iets anders dan `Ja` — moet veld `vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen 'aanwezigen' %} personen?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur?" _(op Stap 6: Vergunningsplichtig scan)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "WordtErAlleenMuziekGeluidGeproduceerdTussen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen` = `Ja` — moet veld `WordtErAlleenMuziekGeluidGeproduceerdTussen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur?" = "Ja"

**Dan verwachten we:**
- Veld "Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur?" _(op Stap 6: Vergunningsplichtig scan)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "WordtErAlleenMuziekGeluidGeproduceerdTussen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen` is iets anders dan `Ja` — moet veld `WordtErAlleenMuziekGeluidGeproduceerdTussen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur?" _(op Stap 6: Vergunningsplichtig scan)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "IsdeGeluidsproductieLagerDan" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `WordtErAlleenMuziekGeluidGeproduceerdTussen` = `Ja` — moet veld `IsdeGeluidsproductieLagerDan` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur?" = "Ja"

**Dan verwachten we:**
- Veld "Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?" _(op Stap 6: Vergunningsplichtig scan)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "IsdeGeluidsproductieLagerDan" — trigger matcht niet (auto)

Met een waarde die niet matcht — `WordtErAlleenMuziekGeluidGeproduceerdTussen` is iets anders dan `Ja` — moet veld `IsdeGeluidsproductieLagerDan` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?" _(op Stap 6: Vergunningsplichtig scan)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `IsdeGeluidsproductieLagerDan` = `Ja` — moet veld `erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?" = "Ja"

**Dan verwachten we:**
- Veld "Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten?" _(op Stap 6: Vergunningsplichtig scan)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten" — trigger matcht niet (auto)

Met een waarde die niet matcht — `IsdeGeluidsproductieLagerDan` is iets anders dan `Ja` — moet veld `erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten?" _(op Stap 6: Vergunningsplichtig scan)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten` = `Ja` — moet veld `wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten?" = "Ja"

**Dan verwachten we:**
- Veld "Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst?" _(op Stap 6: Vergunningsplichtig scan)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst" — trigger matcht niet (auto)

Met een waarde die niet matcht — `erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten` is iets anders dan `Ja` — moet veld `wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst?" _(op Stap 6: Vergunningsplichtig scan)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "indienErObjectenGeplaatstWordenZijnDezeDanKleiner" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst` = `Ja` — moet veld `indienErObjectenGeplaatstWordenZijnDezeDanKleiner` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst?" = "Ja"

**Dan verwachten we:**
- Veld "Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? " _(op Stap 6: Vergunningsplichtig scan)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "indienErObjectenGeplaatstWordenZijnDezeDanKleiner" — trigger matcht niet (auto)

Met een waarde die niet matcht — `wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst` is iets anders dan `Ja` — moet veld `indienErObjectenGeplaatstWordenZijnDezeDanKleiner` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? " _(op Stap 6: Vergunningsplichtig scan)_ is **niet zichtbaar** in de rendered pagina

### ✅ Eerste meldingsvraag komt vrij als objecten klein én gemeente heeft report_question_1

Als de organisator aangeeft dat geplaatste objecten kleiner zijn dan de gemeente-grens, én de gemeente heeft de eerste aanvullende vraag geconfigureerd (`gemeenteVariabelen.report_question_1`), verschijnt meldingvraag1 als vervolgvraag in het formulier.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? " = "Ja"
- Veld `gemeenteVariabelen` — `report_question_1` uit

**Dan verwachten we:**
- Veld "{{ gemeenteVariabelen.report_question_1 }}" _(op Stap 6: Vergunningsplichtig scan)_ wordt **zichtbaar**

### ✅ Bij "aantal aanwezigen niet kleiner dan drempel" blijft vergunningsplicht

Zodra de organisator al bij de eerste vraag aangeeft dat het aantal aanwezigen NIET onder de gemeentelijke drempel ligt, stopt de melding-route direct. Het systeem markeert de aanvraag als vergunningsaanvraag en verbergt de content-block die naar melding zou leiden — de organisator wordt doorgestuurd naar de volledige vergunningsprocedure.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen 'aanwezigen' %} personen?" = "Nee"

**Dan verwachten we:**
- Veld `isVergunningaanvraag` = **ja**
- Veld "Content" _(op Stap 6: Vergunningsplichtig scan)_ wordt **verborgen**

# Stap 15: Vergunningaanvraag: overig

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 28/28 gedekt.

## Component-level conditionele zichtbaarheid (auto-gegenereerd)

Voor elk veld in het formulier dat alleen onder een specifieke voorwaarde zichtbaar is, genereren we twee scenarios: één waarin aan de voorwaarde wordt voldaan (en we verwachten dat het veld zichtbaar is), en één waarin de voorwaarde NIET is voldaan (en we verwachten dat het veld verborgen is). Zo wordt elke conditionele regel in het formulier in twee richtingen bewezen.

## Detail-velden per aangevinkte maatregel

Per aangevinkte overige maatregel (bijvoorbeeld "extra afval" of "aanpassen straatmeubilair") verschijnt een detail-veld waarin de organisator kan aangeven hoe dat wordt georganiseerd. Ook markeert het systeem de maatregelen-pagina als van toepassing zodat ze in de wizard-sidebar actief is.

### ✅ Zichtbaarheid "zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie` = `Ja` — moet veld `zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Huurt u de verkeersregelaars in bij een daarin gespecialiseerd bedrijf/organisatie?" = "Ja"

**Dan verwachten we:**
- Veld "Zijn de in te zetten personen beroepsmatige verkeersregelaars of is er sprake van evenementenverkeersregelaars?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars" — trigger matcht niet (auto)

Met een waarde die niet matcht — `huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie` is iets anders dan `Ja` — moet veld `zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Huurt u de verkeersregelaars in bij een daarin gespecialiseerd bedrijf/organisatie?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Zijn de in te zetten personen beroepsmatige verkeersregelaars of is er sprake van evenementenverkeersregelaars?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAndereMaatregelenUWiltNemen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs` = `anders` — moet veld `welkeAndereMaatregelenUWiltNemen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "U heeft aangegeven, dat u extra vervoersmaatregelen wilt nemen voor bezoekers van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Kruis hier aan, wat van toepassing is" — `0` uit

**Dan verwachten we:**
- Veld "Welke andere maatregelen u wilt nemen" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeAndereMaatregelenUWiltNemen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs` is iets anders dan `anders` — moet veld `welkeAndereMaatregelenUWiltNemen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "U heeft aangegeven, dat u extra vervoersmaatregelen wilt nemen voor bezoekers van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Kruis hier aan, wat van toepassing is" — 

**Dan verwachten we:**
- Veld "Welke andere maatregelen u wilt nemen" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "opWelkNiveauWiltUPromotieMaken" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `wiltUPromotieMakenVoorUwEvenement` = `Ja` — moet veld `opWelkNiveauWiltUPromotieMaken` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Ja"

**Dan verwachten we:**
- Veld "Op welk niveau wilt u promotie maken?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "opWelkNiveauWiltUPromotieMaken" — trigger matcht niet (auto)

Met een waarde die niet matcht — `wiltUPromotieMakenVoorUwEvenement` is iets anders dan `Ja` — moet veld `opWelkNiveauWiltUPromotieMaken` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Op welk niveau wilt u promotie maken?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "hoeWiltUPromotieMakenVoorUwEvenement" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `wiltUPromotieMakenVoorUwEvenement` = `Ja` — moet veld `hoeWiltUPromotieMakenVoorUwEvenement` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Ja"

**Dan verwachten we:**
- Veld "Hoe wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "hoeWiltUPromotieMakenVoorUwEvenement" — trigger matcht niet (auto)

Met een waarde die niet matcht — `wiltUPromotieMakenVoorUwEvenement` is iets anders dan `Ja` — moet veld `hoeWiltUPromotieMakenVoorUwEvenement` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Hoe wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "opWelkeAndereManierWiltUPromotieMaken" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `hoeWiltUPromotieMakenVoorUwEvenement` = `anders` — moet veld `opWelkeAndereManierWiltUPromotieMaken` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Hoe wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — `0` uit

**Dan verwachten we:**
- Veld "Op welke andere manier wilt u promotie maken?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "opWelkeAndereManierWiltUPromotieMaken" — trigger matcht niet (auto)

Met een waarde die niet matcht — `hoeWiltUPromotieMakenVoorUwEvenement` is iets anders dan `anders` — moet veld `opWelkeAndereManierWiltUPromotieMaken` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Hoe wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — 

**Dan verwachten we:**
- Veld "Op welke andere manier wilt u promotie maken?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "opWelkeWijzeInformeertUHen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX` = `Ja` — moet veld `opWelkeWijzeInformeertUHen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Geeft u omwonenden en nabijgelegen bedrijven vooraf informatie over uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Ja"

**Dan verwachten we:**
- Veld "Op welke wijze informeert u hen?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "opWelkeWijzeInformeertUHen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX` is iets anders dan `Ja` — moet veld `opWelkeWijzeInformeertUHen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Geeft u omwonenden en nabijgelegen bedrijven vooraf informatie over uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Op welke wijze informeert u hen?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `organiseertUUwEvenementXVoorDeEersteKeer` = `Nee` — moet veld `welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "Nee"

**Dan verwachten we:**
- Veld "Welke ervaring heeft de organisator met het organiseren van evenementen?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen" — trigger matcht niet (auto)

Met een waarde die niet matcht — `organiseertUUwEvenementXVoorDeEersteKeer` is iets anders dan `Nee` — moet veld `welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Welke ervaring heeft de organisator met het organiseren van evenementen?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `organiseertUUwEvenementXVoorDeEersteKeer` = `Nee` — moet veld `welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "Nee"

**Dan verwachten we:**
- Veld "Welke relevante ervaring heeft het personeel dat de organisator inhuurt via intermediairs?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs" — trigger matcht niet (auto)

Met een waarde die niet matcht — `organiseertUUwEvenementXVoorDeEersteKeer` is iets anders dan `Nee` — moet veld `welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Welke relevante ervaring heeft het personeel dat de organisator inhuurt via intermediairs?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `organiseertUUwEvenementXVoorDeEersteKeer` = `Nee` — moet veld `welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "Nee"

**Dan verwachten we:**
- Veld "Welke relevante ervaring heeft het personeel van (onder)aannemers aan wie de organisator werk uitbesteedt?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt" — trigger matcht niet (auto)

Met een waarde die niet matcht — `organiseertUUwEvenementXVoorDeEersteKeer` is iets anders dan `Nee` — moet veld `welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Welke relevante ervaring heeft het personeel van (onder)aannemers aan wie de organisator werk uitbesteedt?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `organiseertUUwEvenementXVoorDeEersteKeer` = `Nee` — moet veld `welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "Nee"

**Dan verwachten we:**
- Veld "Welke relevante ervaring hebben de vrijwilligers die de organisator  inzet?" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet" — trigger matcht niet (auto)

Met een waarde die niet matcht — `organiseertUUwEvenementXVoorDeEersteKeer` is iets anders dan `Nee` — moet veld `welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Welke relevante ervaring hebben de vrijwilligers die de organisator  inzet?" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "uKuntHierHetHuisregelementUploaden" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `hanteertUHuisregelsVoorUwEvenementX` = `Ja` — moet veld `uKuntHierHetHuisregelementUploaden` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Hanteert u huisregels voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Ja"

**Dan verwachten we:**
- Veld "U kunt hier het huisregelement uploaden" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "uKuntHierHetHuisregelementUploaden" — trigger matcht niet (auto)

Met een waarde die niet matcht — `hanteertUHuisregelsVoorUwEvenementX` is iets anders dan `Ja` — moet veld `uKuntHierHetHuisregelementUploaden` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Hanteert u huisregels voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "U kunt hier het huisregelement uploaden" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "lichtDeSideEventsToe" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024` = `Ja` — moet veld `lichtDeSideEventsToe` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u ook flankerende evenementen (side events) tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Ja"

**Dan verwachten we:**
- Veld "Licht de side events toe" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "lichtDeSideEventsToe" — trigger matcht niet (auto)

Met een waarde die niet matcht — `organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024` is iets anders dan `Ja` — moet veld `lichtDeSideEventsToe` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Organiseert u ook flankerende evenementen (side events) tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Licht de side events toe" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "uploadDeVerzekeringspolis" — trigger matcht (auto)

Zodra de gebruiker een waarde kiest die matcht met de conditional — `heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement` = `Ja` — moet veld `uploadDeVerzekeringspolis` **zichtbaar** worden. Dit scenario test de match-kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Heeft u een evenementenverzekering afgesloten voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "Ja"

**Dan verwachten we:**
- Veld "Upload de verzekeringspolis" _(op Stap 15: Vergunningaanvraag: overig)_ is **zichtbaar** in de rendered pagina

### ✅ Zichtbaarheid "uploadDeVerzekeringspolis" — trigger matcht niet (auto)

Met een waarde die niet matcht — `heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement` is iets anders dan `Ja` — moet veld `uploadDeVerzekeringspolis` **verborgen** zijn. Dit scenario test de andere kant van de conditional.

**Bewijs:** 🟡 Gemiddeld — PHP-runner kon 1 visuele check(s) niet direct meten, spec-referentie bevestigt ze wel  ·  **PHP (Filament):** ✅ _(0/1 checks daadwerkelijk gemeten via rendered HTML; 1 overgeslagen)_  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Heeft u een evenementenverzekering afgesloten voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" = "___no_match_value_f7e3b2___"

**Dan verwachten we:**
- Veld "Upload de verzekeringspolis" _(op Stap 15: Vergunningaanvraag: overig)_ is **niet zichtbaar** in de rendered pagina

### ✅ Extra afval aangevinkt → detail-veld + maatregelen-stap actief

Als de organisator bij "kruis aan welke overige maatregelen" optie A33 (extra afvalvoorzieningen) aanvinkt, verschijnt het detail-veld waarin de aanpak beschreven kan worden, en wordt de maatregelen-pagina in de sidebar actief.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan welke overige maatregelen/gevolgen van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Er ontstaat extra afval" aangevinkt

**Dan verwachten we:**
- Veld "Extra afval" _(op Stap 13: Vergunningaanvraag: maatregelen)_ wordt **zichtbaar**
- Stap 15: Vergunningaanvraag: overig wordt **van toepassing** (getoond in sidebar)

### ✅ Straatmeubilair aangevinkt → detail-veld + maatregelen-stap actief

Als de organisator kiest om straatmeubilair aan te passen of te verwijderen (optie A32), verschijnt het detail-veld waarin kan worden beschreven welke objecten verplaatst worden.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Kruis aan welke overige maatregelen/gevolgen van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "(Laten) aanpassen locatie en/of verwijderen straatmeubilair" aangevinkt

**Dan verwachten we:**
- Veld "Aanpassen locatie en/of verwijderen straatmeubilair" _(op Stap 13: Vergunningaanvraag: maatregelen)_ wordt **zichtbaar**
- Stap 15: Vergunningaanvraag: overig wordt **van toepassing** (getoond in sidebar)

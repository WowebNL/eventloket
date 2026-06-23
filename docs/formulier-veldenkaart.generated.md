---
form_uuid: 7ec8b8ed-0850-4342-a533-9d6c06bfb2c5
form_slug: evenementformulier-poc-kopie-a6efc0
form_name: PRE-PROD Evenementformulier
of_release: 3.3.9
of_git_sha: ba142a626149d438d331bce09d8034ce7cb6a413
generated_at: 2026-04-16T08:36:58+02:00
source: "local:/var/www/html/docker/local-data/open-formulier"
totals:
  steps: 17
  fields: 342
  logic_rules: 144
  logic_actions: 259
  form_variables: 28
  template_placeholders: 59
---

# PRE-PROD Evenementformulier — Veldenkaart

Automatisch gegenereerd door `php artisan forms:veldenkaart`. Niet handmatig bewerken — wijzigingen in Open Forms zijn leidend.


## Inhoud

- [Samenvatting](#samenvatting)
- [Form-variabelen](#form-variabelen) (28)
- [Template-variabelen](#template-variabelen) (59)
- [Stappen](#stappen) (17)
  - [Stap 0: Contactgegevens](#stap-0-contactgegevens)
  - [Stap 1: Het evenement](#stap-1-het-evenement)
  - [Stap 2: Locatie](#stap-2-locatie)
  - [Stap 3: Tijden](#stap-3-tijden)
  - [Stap 4: Vooraankondiging](#stap-4-vooraankondiging)
  - [Stap 5: Vergunningsplichtig scan](#stap-5-vergunningsplichtig-scan)
  - [Stap 6: Melding](#stap-6-melding)
  - [Stap 7: Risicoscan](#stap-7-risicoscan)
  - [Stap 8: Vergunningsaanvraag: soort](#stap-8-vergunningsaanvraag-soort)
  - [Stap 9: Vergunningaanvraag: kenmerken](#stap-9-vergunningaanvraag-kenmerken)
  - [Stap 10: Vergunningsaanvraag: voorzieningen](#stap-10-vergunningsaanvraag-voorzieningen)
  - [Stap 11: Vergunningsaanvraag: voorwerpen](#stap-11-vergunningsaanvraag-voorwerpen)
  - [Stap 12: Vergunningaanvraag: maatregelen](#stap-12-vergunningaanvraag-maatregelen)
  - [Stap 13: Vergunningsaanvraag: extra activiteiten](#stap-13-vergunningsaanvraag-extra-activiteiten)
  - [Stap 14: Vergunningaanvraag: overig](#stap-14-vergunningaanvraag-overig)
  - [Stap 15: Bijlagen](#stap-15-bijlagen)
  - [Stap 16: Type aanvraag](#stap-16-type-aanvraag)
- [Logica](#logica)
  - [fetch-from-service](#logica-fetch-from-service) (7)
  - [property](#logica-property) (98)
  - [set-registration-backend](#logica-set-registration-backend) (45)
  - [step-applicable](#logica-step-applicable) (43)
  - [step-not-applicable](#logica-step-not-applicable) (21)
  - [variable](#logica-variable) (45)


## Samenvatting

| Metric | Waarde |
|---|---|
| Formulier | PRE-PROD Evenementformulier |
| UUID | `7ec8b8ed-0850-4342-a533-9d6c06bfb2c5` |
| Slug | `evenementformulier-poc-kopie-a6efc0` |
| OF-versie | 3.3.9 |
| Bron | local:/var/www/html/docker/local-data/open-formulier |
| Gegenereerd | 2026-04-16T08:36:58+02:00 |
| Stappen | 17 |
| Velden (excl. content) | 342 |
| Logic rules | 144 |
| Logic actions | 259 |

### Logic-acties per type

| Type | Aantal |
|---|---|
| `fetch-from-service` | 7 |
| `property` | 98 |
| `set-registration-backend` | 45 |
| `step-applicable` | 43 |
| `step-not-applicable` | 21 |
| `variable` | 45 |


## Form-variabelen

| Key | Naam | Type | Source | Prefill-plugin | Initial value |
|---|---|---|---|---|---|
| `inGemeentenResponse` | in_gemeenten_response | object | user_defined | — | [] |
| `confirmationtext` | confirmationtext | string | user_defined | — |  |
| `voorwerpenGratis` | voorwerpenGratis | array | user_defined | — | [["A24","Verkooppunten  voor consumptiemunten of -bonnen"],["A25","Speeltoestellen Attractietoestellen"],["A26","Aggregaten,  brandstofopslag en andere brandgevaarlijke stoffen"],["A27","Geluidstorens"],["A28","Lichtmasten"],["A29","Marktkramen"],["A30","Andere voorwerpen"]] |
| `eventloketPrefill` | eventloket_prefill | object | user_defined | — | [] |
| `eventloketPrefillLoaded` | eventloket_prefill_loaded | boolean | user_defined | — | false |
| `evenementTypen` | evenementTypen | array | user_defined | — | ["Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales","Muziekevenement Cultuur- of kunstevenement of toneelvoorstellingen","Sportevenement","Markt of braderie","Circus","Kermis","Beurs of Congres","Auto- scooter- of motorshow","Vliegshow","Festival","Optocht, processie of corso","Culinair evenement","Dierenshow","Evenement op het water","Scoutingwedstrijden","Truck event","Verkeerseducatie","Halloweenfeesten","Anders"] |
| `evenementInGemeentenNamen` | evenement_in_gemeenten_namen | array | user_defined | — | [] |
| `evenementInGemeentenLijst` | evenement_in_gemeenten_lijst | array | user_defined | — | [] |
| `evenementInGemeente` | evenement_in_gemeente | string | user_defined | — |  |
| `alleGemeenteNamen` | alle_gemeente_namen | string | user_defined | — | Beek, Beekdaelen, Brunssum, Eijsden-Margraten, Gulpen-Wittem, Heerlen, Kerkrade, Landgraaf, Meerssen, Simpelveld, Sittard-Geleen, Stein, Vaals, Valkenburg aan de Geul en Voerendaal |
| `gemeenten` | gemeenten | object | user_defined | — | [] |
| `gemeenteVariabelen` | gemeente_variabelen | object | user_defined | — | [] |
| `testlink` | testlink | string | user_defined | — | dit is een <a href="https://woweb.nl" target="_blank">test link </a> |
| `prefill` | prefill | object | user_defined | `objects_api` | [] |
| `addressToCheck` | addressToCheck | string | user_defined | — |  |
| `isVergunningaanvraag` | is_vergunningaanvraag | boolean | user_defined | — | false |
| `voorwerpenLijst` | voorwerpenLijst | array | user_defined | — | [["A23","Verkooppunten  voor toegangskaarten"],["A24","Verkooppunten  voor consumptiemunten of -bonnen"],["A25","Speeltoestellen Attractietoestellen"],["A26","Aggregaten,  brandstofopslag en andere brandgevaarlijke stoffen"],["A27","Geluidstorens"],["A28","Lichtmasten"],["A29","Marktkramen"],["A30","Andere voorwerpen"]] |
| `alcoholvergunning` | alcoholvergunning | boolean | user_defined | — | false |
| `jaNeeLijst` | ja_nee_lijst | array | user_defined | — | ["Ja","Nee"] |
| `eventloketSession` | eventloket_session | object | user_defined | — | [] |
| `evenementenInDeGemeente` | evenementenInDeGemeente | string | user_defined | — |  |
| `risicoClassificatie` | risico_classificatie | string | user_defined | — |  |
| `routeDoorGemeentenNamen` | routeDoorGemeentenNamen | array | user_defined | — | [] |
| `binnenVeiligheidsregio` | binnen_veiligheidsregio | boolean | user_defined | — | true |
| `risicoscan` | risicoscan | object | user_defined | — | [] |
| `risicoscanCalculator` | risicoscan_calculator | object | user_defined | — | [] |
| `kvk` | kvk | string | user_defined | — |  |
| `addressesToCheck` | addressesToCheck | object | user_defined | — | [] |


## Template-variabelen

Alle `{{ ... }}` placeholders die in labels, descriptions, tooltips of logic-descriptions voorkomen.

| Placeholder | Voorkomens | Vindplaats (eerste 3) |
|---|---|---|
| `{{ AfbouwEind }}` | 1 | stap 3: Tijden → overzichtTijden (content.html) |
| `{{ AfbouwStart }}` | 1 | stap 3: Tijden → overzichtTijden (content.html) |
| `{{ EvenementEind }}` | 2 | stap 3: Tijden → overzichtTijden (content.html)<br>(geen stap) → logic:3fa0fbf5-9ee1-4c2a-9074-9993e208b010 (rule-description) |
| `{{ EvenementStart }}` | 2 | stap 3: Tijden → overzichtTijden (content.html)<br>(geen stap) → logic:3fa0fbf5-9ee1-4c2a-9074-9993e208b010 (rule-description) |
| `{{ OpbouwEind }}` | 1 | stap 3: Tijden → overzichtTijden (content.html) |
| `{{ OpbouwStart }}` | 1 | stap 3: Tijden → overzichtTijden (content.html) |
| `{{ accumulator }}` | 2 | (geen stap) → logic:e3992429-730a-4ed9-af3c-62ad897933fe (rule-description)<br>(geen stap) → logic:a6fcec40-74f6-4741-862f-22ebf2de7142 (rule-description) |
| `{{ addressToCheck }}` | 2 | (geen stap) → logic:99b8a502-9ef8-4be2-8142-2a25c69ba905 (rule-description)<br>(geen stap) → logic:99b8a502-9ef8-4be2-8142-2a25c69ba905 (rule-description) |
| `{{ addressesToCheck }}` | 2 | (geen stap) → logic:bd328413-a566-42a6-87ba-ec575ea94347 (rule-description)<br>(geen stap) → logic:bd328413-a566-42a6-87ba-ec575ea94347 (rule-description) |
| `{{ adresSenVanHetEvenement }}` | 2 | (geen stap) → logic:bb866a33-aa14-437f-a7bf-3303ad75a5d9 (rule-description)<br>(geen stap) → logic:bb866a33-aa14-437f-a7bf-3303ad75a5d9 (rule-description) |
| `{{ adresVanDeGebouwEn }}` | 1 | (geen stap) → logic:974b5945-c4cf-4d1a-a5f8-34985255406d (rule-description) |
| `{{ alleGemeenteNamen }}` | 1 | stap 2: Locatie → NotWithin (content.html) |
| `{{ evenementInGemeente.brk_identification }}` | 46 | (geen stap) → logic:0e056f5a-9303-4322-9a75-300187ab62c7 (rule-description)<br>(geen stap) → logic:4fb78bad-07fb-473d-bc18-bee1bad8503f (rule-description)<br>(geen stap) → logic:47620576-e866-4f7e-98fb-cad476f4ac3b (rule-description) …(+43 meer) |
| `{{ evenementInGemeentenNamen }}` | 3 | (geen stap) → logic:e3992429-730a-4ed9-af3c-62ad897933fe (rule-description)<br>(geen stap) → logic:a6fcec40-74f6-4741-862f-22ebf2de7142 (rule-description)<br>(geen stap) → logic:3247522b-8603-4c7c-ae8d-b92a75fb35d6 (rule-description) |
| `{{ evenementenInDeGemeente }}` | 2 | stap 3: Tijden → evenmentenInDeBuurtContent (content.html)<br>(geen stap) → logic:00876823-b3f3-44f6-a177-d355c84c0b12 (rule-description) |
| `{{ eventloketPrefill }}` | 1 | (geen stap) → logic:29ff6bf6-c3fb-42e6-b523-d5478d203b85 (rule-description) |
| `{{ eventloketPrefillLoaded }}` | 1 | (geen stap) → logic:29ff6bf6-c3fb-42e6-b523-d5478d203b85 (rule-description) |
| `{{ eventloketSession.kvk }}` | 2 | (geen stap) → logic:ce043762-6d77-44dc-8e8c-cb605e9acdfa (rule-description)<br>(geen stap) → logic:1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a (rule-description) |
| `{{ eventloketSession.organisation_address }}` | 2 | (geen stap) → logic:2f7b0e09-2730-4aab-89e5-8b0182ee68bb (rule-description)<br>(geen stap) → logic:2f7b0e09-2730-4aab-89e5-8b0182ee68bb (rule-description) |
| `{{ eventloketSession.organisation_email }}` | 2 | (geen stap) → logic:5905fff0-6bec-4c28-9064-55772fb25859 (rule-description)<br>(geen stap) → logic:5905fff0-6bec-4c28-9064-55772fb25859 (rule-description) |
| `{{ eventloketSession.organisation_name }}` | 2 | (geen stap) → logic:583c258c-fcbd-4f1c-b127-58d04b6ed050 (rule-description)<br>(geen stap) → logic:583c258c-fcbd-4f1c-b127-58d04b6ed050 (rule-description) |
| `{{ eventloketSession.organisation_phone }}` | 2 | (geen stap) → logic:0f284f5c-ffb1-4512-981d-5954e56c8b9e (rule-description)<br>(geen stap) → logic:0f284f5c-ffb1-4512-981d-5954e56c8b9e (rule-description) |
| `{{ eventloketSession.organiser_uuid }}` | 1 | stap 3: Tijden → evenmentenInDeBuurtContent (content.html) |
| `{{ eventloketSession.user_last_name }}` | 2 | (geen stap) → logic:8124340f-cce5-47da-8691-91ad37fd6af0 (rule-description)<br>(geen stap) → logic:8124340f-cce5-47da-8691-91ad37fd6af0 (rule-description) |
| `{{ gemeenteVariabelen.aantal_objecten }}` | 1 | stap 5: Vergunningsplichtig scan → wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst (label) |
| `{{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }}` | 1 | stap 5: Vergunningsplichtig scan → indienErObjectenGeplaatstWordenZijnDezeDanKleiner (label) |
| `{{ gemeenteVariabelen.melding_alcohol_ontheffing_tekst|urlize }}` | 1 | stap 6: Melding → content9 (content.html) |
| `{{ gemeenteVariabelen.melding_drone_ontheffing_tekst }}` | 1 | stap 6: Melding → content10 (content.html) |
| `{{ gemeenteVariabelen.melding_maximale_dba }}` | 1 | stap 5: Vergunningsplichtig scan → IsdeGeluidsproductieLagerDan (label) |
| `{{ gemeenteVariabelen.muziektijden.end }}` | 1 | stap 5: Vergunningsplichtig scan → WordtErAlleenMuziekGeluidGeproduceerdTussen (label) |
| `{{ gemeenteVariabelen.muziektijden.start }}` | 1 | stap 5: Vergunningsplichtig scan → WordtErAlleenMuziekGeluidGeproduceerdTussen (label) |
| `{{ gemeenteVariabelen.report_question_1 }}` | 1 | stap 5: Vergunningsplichtig scan → meldingvraag1 (label) |
| `{{ gemeenteVariabelen.report_question_2 }}` | 2 | stap 5: Vergunningsplichtig scan → meldingvraag2 (label)<br>(geen stap) → logic:172fe1ad-207f-429a-ace2-d2d07b4ea92a (rule-description) |
| `{{ gemeenteVariabelen.report_question_3 }}` | 2 | stap 5: Vergunningsplichtig scan → meldingvraag3 (label)<br>(geen stap) → logic:4e042329-a992-45ae-998b-521ea980c55a (rule-description) |
| `{{ gemeenteVariabelen.report_question_4 }}` | 2 | stap 5: Vergunningsplichtig scan → meldingvraag4 (label)<br>(geen stap) → logic:c7431a0c-f315-4768-8372-8703629228b8 (rule-description) |
| `{{ gemeenteVariabelen.report_question_5 }}` | 2 | stap 5: Vergunningsplichtig scan → meldingvraag5 (label)<br>(geen stap) → logic:63781392-9b7b-45e3-823d-5b039784882e (rule-description) |
| `{{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }}` | 1 | stap 5: Vergunningsplichtig scan → vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen (label) |
| `{{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }}` | 1 | stap 5: Vergunningsplichtig scan → vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen (label) |
| `{{ inGemeentenResponse.line.end.name }}` | 1 | stap 2: Locatie → routeStartEndContent2 (content.html) |
| `{{ inGemeentenResponse.line.start.name }}` | 2 | stap 2: Locatie → routeStartEndContent2 (content.html)<br>stap 2: Locatie → routeStartEndContent2 (content.html) |
| `{{ inGemeentenResponse.line.start_end_equal }}` | 1 | (geen stap) → logic:be547255-4a1b-4f37-96e8-919d5351e7a5 (rule-description) |
| `{{ indienErObjectenGeplaatstWordenZijnDezeDanKleiner }}` | 2 | (geen stap) → logic:454a40c6-43c8-42cd-9d2f-6d2ace4fec53 (rule-description)<br>(geen stap) → logic:a64ed84a-d0a3-4560-b782-a24be41b3e4a (rule-description) |
| `{{ isHetAantalAanwezigenBijUwEvenementMinderDanSdf }}` | 2 | (geen stap) → logic:87482f34-1e1f-4853-b2da-312c9b2cebf0 (rule-description)<br>(geen stap) → logic:8e022b2c-1742-4ff7-a5a0-50d02d05833e (rule-description) |
| `{{ isVergunningaanvraag }}` | 14 | (geen stap) → logic:6c661796-23ba-44ad-8ad0-1bcf4cabe17d (rule-description)<br>(geen stap) → logic:6142841d-ea97-4e22-8ffa-90c0b9b18cdb (rule-description)<br>(geen stap) → logic:91870e4d-e065-462b-8c3d-686409084cf8 (rule-description) …(+11 meer) |
| `{{ locatieSOpKaart }}` | 2 | (geen stap) → logic:a7211d0c-f8aa-479b-b9b9-8474dbe70b75 (rule-description)<br>(geen stap) → logic:a7211d0c-f8aa-479b-b9b9-8474dbe70b75 (rule-description) |
| `{{ meldingAdres }}` | 1 | (geen stap) → logic:91bf1bff-b1af-4da7-b310-e56854d48f61 (rule-description) |
| `{{ meldingsvraag5 }}` | 1 | (geen stap) → logic:a757ea1f-24ee-40b8-a839-4e9997a33959 (rule-description) |
| `{{ meldingvraag1 }}` | 2 | (geen stap) → logic:172fe1ad-207f-429a-ace2-d2d07b4ea92a (rule-description)<br>(geen stap) → logic:ea096e0f-e793-4df7-8292-df26ad862dc9 (rule-description) |
| `{{ meldingvraag2 }}` | 2 | (geen stap) → logic:981e2b88-49b3-4096-ae1d-07a4500e7ccc (rule-description)<br>(geen stap) → logic:4e042329-a992-45ae-998b-521ea980c55a (rule-description) |
| `{{ meldingvraag3 }}` | 2 | (geen stap) → logic:b741d925-75bf-4b8f-a0aa-47cdb0e5341d (rule-description)<br>(geen stap) → logic:c7431a0c-f315-4768-8372-8703629228b8 (rule-description) |
| `{{ meldingvraag4 }}` | 2 | (geen stap) → logic:ceac4877-e22f-4d59-afac-cf2f29cb93d9 (rule-description)<br>(geen stap) → logic:63781392-9b7b-45e3-823d-5b039784882e (rule-description) |
| `{{ risicoClassificatie }}` | 3 | stap 7: Risicoscan → risicoClassificatieContent (content.html)<br>(geen stap) → logic:f1202010-b8b7-45c0-8f31-756190313451 (rule-description)<br>(geen stap) → logic:f1202010-b8b7-45c0-8f31-756190313451 (rule-description) |
| `{{ routeDoorGemeentenNamen }}` | 1 | (geen stap) → logic:3247522b-8603-4c7c-ae8d-b92a75fb35d6 (rule-description) |
| `{{ routeDoorGemeentenNamen|join:", " }}` | 1 | stap 2: Locatie → contentRouteDoorkuistMeerdereGemeenteInfo (content.html) |
| `{{ routesOpKaart }}` | 2 | (geen stap) → logic:599a6cfd-7ea4-4c68-b011-c1f590286daf (rule-description)<br>(geen stap) → logic:599a6cfd-7ea4-4c68-b011-c1f590286daf (rule-description) |
| `{{ userSelectGemeente }}` | 2 | (geen stap) → logic:580a3ef8-9fa6-4f5a-8714-502d86d6cb55 (rule-description)<br>(geen stap) → logic:580a3ef8-9fa6-4f5a-8714-502d86d6cb55 (rule-description) |
| `{{ waarVindtHetEvenementPlaats }}` | 1 | (geen stap) → logic:d21486ca-b7b2-4a4c-9963-1f24ca7eeea4 (rule-description) |
| `{{ watIsDeAantrekkingskrachtVanHetEvenement }}` | 1 | (geen stap) → logic:55ce8acd-f972-417d-8920-64c8b0744e14 (rule-description) |
| `{{ watIsDeNaamVanHetEvenementVergunning }}` | 62 | stap 1: Het evenement → geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning (label)<br>stap 1: Het evenement → soortEvenement (label)<br>stap 2: Locatie → waarVindtHetEvenementPlaats (label) …(+59 meer) |


## Stappen


### Stap 0: Contactgegevens

> UUID: `48e9408a-3455-4d3c-b9ce-5f6f08f8f2b5` · slug: `contactgegevens` · velden: 38

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `loadUserInformation` | content | _content_ |  |  |  |  |  |  |  |  |
| `watIsUwVoornaam` | textfield | Wat is uw voornaam? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| `watIsUwAchternaam` | textfield | Wat is uw achternaam? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| `watIsUwEMailadres` | email | Wat is uw e-mailadres? | ✓ |  |  |  |  |  |  |  |
| `watIsUwTelefoonnummer` | textfield | Wat is uw telefoonnummer? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| `organisatieInformatie` | fieldset | Organisatie informatie <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `watIsHetKamerVanKoophandelNummerVanUwOrganisatie` | textfield | Wat is het Kamer van Koophandel nummer van uw organisatie? | ✓ |  |  | maxLength=1000, pattern=^[0-9]{8}$ |  |  |  |  |
| — `watIsDeNaamVanUwOrganisatie` | textfield | Wat is de naam van uw organisatie? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `kolommen1` | columns | Kolommen |  |  |  |  |  |  |  |  |
| —— `postcode1` | textfield | Postcode | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `huisletter1` | textfield | Huisletter |  |  |  | maxLength=1000 |  |  |  |  |
| —— `straatnaam1` | textfield | Straatnaam | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `huisnummer1` | textfield | Huisnummer | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `huisnummertoevoeging1` | textfield | Huisnummertoevoeging |  |  |  | maxLength=1000 |  |  |  |  |
| —— `plaatsnaam1` | textfield | Plaatsnaam | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `emailadresOrganisatie` | email | Wat is het e-mailadres van uw organisatie? |  |  |  |  |  |  |  |  |
| — `telefoonnummerOrganisatie` | textfield | Wat is het telefoonnummer van uw organisatie? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| `waarschuwingGeenKvk` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `adresgegevens` | fieldset | Adresgegevens <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `kolommen` | columns | Kolommen |  |  |  |  |  |  |  |  |
| —— `postcode` | textfield | Postcode | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `huisletter` | textfield | Huisletter |  |  |  | maxLength=1000 |  |  |  |  |
| —— `straatnaam` | textfield | Straatnaam | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `land` | textfield | Land |  |  |  | maxLength=1000 |  |  |  |  |
| —— `huisnummer` | textfield | Huisnummer | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `huisnummertoevoeging` | textfield | Huisnummertoevoeging |  |  |  | maxLength=1000 |  |  |  |  |
| —— `plaatsnaam` | textfield | Plaatsnaam | ✓ |  |  | maxLength=1000 |  |  |  |  |
| `extraContactpersonenToevoegen` | selectboxes | Extra contactpersonen toevoegen |  |  | `vooraf`=Contactpersoon voorafgaand aan het evenement<br>`tijdens`=Contactpersoon tijdens het evenement<br>`achteraf`=Contactpersoon na het evenement |  | `{"vooraf":false,"tijdens":fal…` |  |  |  |
| `contactpersoonVoorafgaandAanHetEvenement` | fieldset | Contactpersoon voorafgaand aan het evenement |  |  |  |  |  |  | toon als [extraContactpersonenToevoegen].[vooraf] = true |  |
| — `naam` | textfield | Naam | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `telefoonnummer` | phoneNumber | Telefoonnummer | ✓ |  |  |  |  |  |  |  |
| — `eMailadres` | email | E-mailadres | ✓ |  |  |  |  |  |  |  |
| `contactpersoonVoorafgaandAanHetEvenement1` | fieldset | Contactpersoon tijdens het evenement |  |  |  |  |  |  | toon als [extraContactpersonenToevoegen].[tijdens] = true |  |
| — `naam1` | textfield | Naam | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `telefoonnummer1` | phoneNumber | Telefoonnummer | ✓ |  |  |  |  |  |  |  |
| — `eMailadres1` | email | E-mailadres | ✓ |  |  |  |  |  |  |  |
| `contactpersoonVoorafgaandAanHetEvenement2` | fieldset | Contactpersoon na het evenement |  |  |  |  |  |  | toon als [extraContactpersonenToevoegen].[achteraf] = true |  |
| — `naam2` | textfield | Naam | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `telefoonnummer2` | phoneNumber | Telefoonnummer | ✓ |  |  |  |  |  |  |  |
| — `eMailadres2` | email | E-mailadres | ✓ |  |  |  |  |  |  |  |

### Stap 1: Het evenement

> UUID: `c3c17c65-0cf1-4a79-a348-75eab01f46ec` · slug: `naam-van-het-evenement` · velden: 5

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `watIsDeNaamVanHetEvenementVergunning` | textfield | Wat is de naam van het evenement? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| `geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning` | textarea | Geef een korte omschrijving van het evenement {{ watIsDeNaamVanHetEvenementVergunning }} | ✓ |  |  | maxLength=10000 |  |  | verberg als [watIsDeNaamVanHetEvenementVergunning] = |  |
| `soortEvenement` | select | Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales`=Buurt-, barbecue of straatfeest, buitenspeeldagen, (eindejaars-)feesten, garagesales<br>`Muziekevenement Cultuur- of kunstevenement of toneelvoorstellingen`=Muziekevenement Cultuur- of kunstevenement of toneelvoorstellingen<br>`Sportevenement`=Sportevenement<br>`Markt of braderie`=Markt of braderie<br>`Circus`=Circus<br>`Kermis`=Kermis<br>`Beurs of Congres`=Beurs of Congres<br>`Auto- scooter- of motorshow`=Auto- scooter- of motorshow<br>`Vliegshow`=Vliegshow<br>`Festival`=Festival<br>`Optocht, processie of corso`=Optocht, processie of corso<br>`Culinair evenement`=Culinair evenement<br>`Dierenshow`=Dierenshow<br>`Evenement op het water`=Evenement op het water<br>`Scoutingwedstrijden`=Scoutingwedstrijden<br>`Truck event`=Truck event<br>`Verkeerseducatie`=Verkeerseducatie<br>`Halloweenfeesten`=Halloweenfeesten<br>`Anders`=Anders <sub>(evenementTypen)</sub> |  |  |  | verberg als [watIsDeNaamVanHetEvenementVergunning] = |  |
| `omschrijfHetSoortEvenement` | textarea | Omschrijf het soort evenement | ✓ |  |  | maxLength=10000 |  |  | toon als [soortEvenement] = [Anders] |  |
| `gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen` | radio | Gaat het hier om een periodiek terugkerende markt (jaarmarkt of weekmarkt), waarvoor de gemeente een besluit heeft genomen met betrekking tot de marktdagen? |  |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [soortEvenement] = [Markt of braderie] |  |

### Stap 2: Locatie

> UUID: `2186344f-9821-45d1-bd52-9900ae15fcb6` · slug: `locatie-van-het-evenement-2` · velden: 18

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `waarVindtHetEvenementPlaats` | selectboxes | Waar vindt het evenement {{ watIsDeNaamVanHetEvenementVergunning }} plaats? | ✓ |  | `gebouw`=In een gebouw of meerdere gebouwen<br>`buiten`=Buiten op één of meerdere plaatsen<br>`route`=Op een route |  | `{"route":false,"buiten":false…` |  |  |  |
| `veldengroep` | fieldset | In een gebouw of meerdere gebouwen |  |  |  |  |  |  | toon als [waarVindtHetEvenementPlaats].[gebouw] = true |  |
| `adresVanDeGebouwEn` | editgrid | Adres van de gebouw(en) <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| — `naamVanDeLocatieGebouw` | textfield | Naam van de locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `adresVanHetGebouwWaarUwEvenementPlaatsvindt1` | addressNL | Adres van het gebouw waar uw evenement plaatsvindt. | ✓ |  |  |  | `{"postcode":"","houseLetter":…` |  |  |  |
| `buitenOpEenOfMeerderePlaatsen` | fieldset | Buiten op één of meerdere plaatsen |  |  |  |  |  |  | toon als [waarVindtHetEvenementPlaats].[buiten] = true |  |
| `locatieSOpKaart` | editgrid | Locatie(s) op kaart <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| — `naamVanDeLocatieKaart` | textfield | Naam van de locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `buitenLocatieVanHetEvenement` | map | Buiten locatie van het evenement | ✓ |  |  |  |  |  |  |  |
| `route` | fieldset | Route |  |  |  |  |  |  | toon als [waarVindtHetEvenementPlaats].[route] = true |  |
| — `infoGpx1` | content | _content_ |  |  |  |  |  |  |  |  |
| — `routesOpKaart` | editgrid | Route op kaart | ✓ |  |  | maxLength=1 |  |  |  |  |
| —— `routeVanHetEvenement` | map | Route van het evenement | ✓ |  |  |  |  |  |  |  |
| — `gpxBestandVanDeRoute` | file | GPX bestand van de route |  |  |  |  |  |  |  |  |
| — `naamVanDeRoute` | textfield | Naam van de route | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `watVoorEvenementGaatPlaatsvindenOpDeRoute1` | select | Wat voor evenement gaat plaatsvinden op de route? | ✓ |  | `fietstochtGeenWedstrijd`=Fietstocht - geen wedstrijd<br>`fietstochtWedstrijd`=Fietstocht - wedstrijd<br>`gemotoriseerdeToertochtGeenWedstrijd`=Gemotoriseerde toertocht - geen wedstrijd<br>`gemotoriseerdeToertochtWedstrijd`=Gemotoriseerde toertocht - wedstrijd<br>`wandeltochtGeenWedstrijd`=Wandeltocht - geen wedstrijd<br>`wandeltochtWedstrijd`=Wandeltocht - wedstrijd<br>`A112`=Carnavalsoptocht<br>`A113`=Hardloopwedstijd<br>`A114`=Overig |  |  |  |  |  |
| — `welkSoortRouteEvenementBetreftUwEvenementX` | textarea | Welk soort evenement vindt plaats op de route? | ✓ |  |  | maxLength=10000 |  |  | toon als [watVoorEvenementGaatPlaatsvindenOpDeRoute1] = [A114] |  |
| — `komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan` | selectboxes | Komt uw route over wegen van wegbeheerders, anders dan de betreffende gemeente? Zo ja, kruis deze dan aan. |  |  | `provincie`=Provincie<br>`waterschap`=Waterschap<br>`rijkswaterstaat`=Rijkswaterstaat<br>`staatsbosbeheer`=Staatsbosbeheer |  | `{"provincie":false,"waterscha…` |  |  |  |
| — `content1` | content | _content_ |  |  |  |  |  |  | toon als [komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan].[provincie] = true |  |
| — `content39` | content | _content_ |  |  |  |  |  |  | toon als [komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan].[waterschap] = true |  |
| — `content41` | content | _content_ |  |  |  |  |  |  | toon als [komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan].[rijkswaterstaat] = true |  |
| — `content40` | content | _content_ |  |  |  |  |  |  | toon als [komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan].[staatsbosbeheer] = true |  |
| — `routeStartEndContent2` | content | _content_ |  |  |  |  |  |  |  |  |
| `NotWithin` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `userSelectGemeente` | radio | De ingevoerde locatie(s) of route valt binnen of doorkruist meerdere gemeenten, wat is de gemeente waarbinnen u de aanvraag wilt doen? <sub>(hidden)</sub> | ✓ | ✓ | _(uit evenementInGemeentenLijst)_ |  |  |  |  |  |
| `contentRouteDoorkuistMeerdereGemeenteInfo` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `content200` | content | _content_ |  | ✓ |  |  |  |  |  |  |

### Stap 3: Tijden

> UUID: `00f09aee-fedd-44d6-b82c-3e3754d67b7a` · slug: `tijden` · velden: 13

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `content2` | content | _content_ |  |  |  |  |  |  |  |  |
| `kolommen3` | columns | Kolommen |  |  |  |  |  |  |  |  |
| — `EvenementStart` | datetime | Wat is de start datum en tijdstip van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  |  |  |
| — `EvenementEind` | datetime | Wat is de eind datum en tijdstip van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  |  |  |
| `evenmentenInDeBuurtContent` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten` | radio | Zijn er voorafgaand aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `opbouwperiode` | columns | Kolommen |  |  |  |  |  |  |  |  |
| — `OpbouwStart` | datetime | Wat is de start datum en tijd van de opbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  | toon als [zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten] = [Ja] |  |
| — `OpbouwEind` | datetime | Wat is de eind datum en tijd van de opbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  | toon als [zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten] = [Ja] |  |
| `zijnErTijdensHetEvenementXOpbouwactiviteiten` | radio | Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvenementVergunning }} opbouwactiviteiten? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `zijnErAansluitendAanHetEvenementAfbouwactiviteiten` | radio | Zijn er aansluitend aan het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `opbouwperiode1` | columns | Kolommen |  |  |  |  |  |  |  |  |
| — `AfbouwStart` | datetime | Wat is de start datum en tijdstip van de afbouw uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  | toon als [zijnErAansluitendAanHetEvenementAfbouwactiviteiten] = [Ja] |  |
| — `AfbouwEind` | datetime | Wat is de eind datum en tijdstip van de afbouw van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  | toon als [zijnErAansluitendAanHetEvenementAfbouwactiviteiten] = [Ja] |  |
| `zijnErTijdensHetEvenementXAfbouwactiviteiten3` | radio | Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvenementVergunning }} afbouwactiviteiten? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `overzichtTijden` | content | _content_ |  |  |  |  |  |  |  |  |

### Stap 4: Vooraankondiging

> UUID: `8facfe56-5548-44e7-93b9-1356bc266e00` · slug: `waarvoor-wilt-u-het-eventloket-gebruiken` · velden: 3

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `waarvoorWiltUEventloketGebruiken` | radio | Waarvoor wilt u Eventloket gebruiken? | ✓ |  | `evenement`=U wilt voor uw evementen een aanvraag indienen<br>`vooraankondiging`=U wilt voor uw evenement een vooraankondiging doen en dient later de volledige aanvraag in |  |  |  |  |  |
| `vooraankondiginggroep` | fieldset | Vooraankondiging <sub>(hidden)</sub> |  | ✓ |  |  |  |  | toon als [waarvoorWiltUEventloketGebruiken] = [vooraankondiging] |  |
| — `content3` | content | _content_ |  |  |  |  |  |  |  |  |
| — `aantalVerwachteAanwezigen` | number | Aantal verwachte aanwezigen | ✓ |  |  |  |  |  |  |  |

### Stap 5: Vergunningsplichtig scan

> UUID: `d87c01ce-8387-43b0-a8c8-e6cf5abb6da1` · slug: `aanvraag-of-melding` · velden: 14

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `content4` | content | _content_ |  |  |  |  |  |  |  |  |
| `contentGemeenteMelding` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `algemeneVragen` | fieldset | Algemene vragen <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `isHetAantalAanwezigenBijUwEvenementMinderDanSdf` | radio | Is het aantal aanwezigen bij uw evenement minder dan {% get_value gemeenteVariabelen 'aanwezigen' %} personen? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen` | radio | Vinden de activiteiten van uw evenement plaats tussen {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start }} uur en {{ gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.end }} uur? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [isHetAantalAanwezigenBijUwEvenementMinderDanSdf] = [Ja] |  |
| — `WordtErAlleenMuziekGeluidGeproduceerdTussen` | radio | Wordt er alleen muziek/geluid geproduceerd tussen {{ gemeenteVariabelen.muziektijden.start }} uur en {{ gemeenteVariabelen.muziektijden.end }} uur? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen] = [Ja] |  |
| — `IsdeGeluidsproductieLagerDan` | radio | Is de geluidsproductie lager dan {{ gemeenteVariabelen.melding_maximale_dba }} dB(A) bronvermogen, gemeten op 3 meter afstand van de bron? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [WordtErAlleenMuziekGeluidGeproduceerdTussen] = [Ja] |  |
| — `erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten` | radio | Er vinden GEEN activiteiten plaats op de rijbaan, (brom)fietspad of parkeerplaats of anderszins een belemmering vormen voor het verkeer en de hulpdiensten? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [IsdeGeluidsproductieLagerDan] = [Ja] |  |
| — `wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst` | radio | Worden er minder dan {{ gemeenteVariabelen.aantal_objecten }} objecten (bijv. tent, springkussen) geplaatst? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten] = [Ja] |  |
| — `indienErObjectenGeplaatstWordenZijnDezeDanKleiner` | radio | Indien er objecten geplaatst worden, zijn deze dan kleiner {{ gemeenteVariabelen.maximale_grootte_objecten_in_m2 }} m2? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst] = [Ja] |  |
| — `meldingvraag1` | radio | {{ gemeenteVariabelen.report_question_1 }} <sub>(hidden)</sub> | ✓ | ✓ | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `meldingvraag2` | radio | {{ gemeenteVariabelen.report_question_2 }} <sub>(hidden)</sub> | ✓ | ✓ | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `meldingvraag3` | radio | {{ gemeenteVariabelen.report_question_3 }} <sub>(hidden)</sub> | ✓ | ✓ | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `meldingvraag4` | radio | {{ gemeenteVariabelen.report_question_4 }} <sub>(hidden)</sub> | ✓ | ✓ | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `meldingvraag5` | radio | {{ gemeenteVariabelen.report_question_5 }} <sub>(hidden)</sub> | ✓ | ✓ | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` | radio | Worden er gebiedsontsluitingswegen en/of doorgaande wegen afgesloten voor het verkeer? <sub>(hidden)</sub> | ✓ | ✓ | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `contentGoNext` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| — `MeldingTekst` | content | _content_ |  | ✓ |  |  |  |  |  |  |

### Stap 6: Melding

> UUID: `5f986f16-6a3a-4066-9383-d71f09877f47` · slug: `melding` · velden: 3

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `content7` | content | _content_ |  |  |  |  |  |  |  |  |
| `wordtErAlcoholGeschonkenTijdensUwEvenement` | radio | Wordt er alcohol geschonken tijdens uw evenement? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `content9` | content | _content_ |  |  |  |  |  |  | toon als [wordtErAlcoholGeschonkenTijdensUwEvenement] = [Ja] |  |
| `wordenErFilmopnamesMetBehulpVanDronesGemaakt` | radio | Worden er filmopnames met behulp van drones gemaakt? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `content10` | content | _content_ |  |  |  |  |  |  | toon als [wordenErFilmopnamesMetBehulpVanDronesGemaakt] = [Ja] |  |
| `vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden` | radio | Vinden er activiteiten plaats, waarvoor mogelijk brandveiligheidseisen gelden? |  |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `content11` | content | _content_ |  |  |  |  |  |  | toon als [vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden] = [Ja] |  |

### Stap 7: Risicoscan

> UUID: `c75cc256-6729-4684-9f9b-ede6265b3e72` · slug: `risicoscan` · velden: 14

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `content` | content | _content_ |  |  |  |  |  |  |  |  |
| `watIsDeAantrekkingskrachtVanHetEvenement` | radio | Wat is de aantrekkingskracht van het evenement? | ✓ |  | `0.5`=Wijk of buurt<br>`1`=Dorp<br>`1.5`=Gemeentelijk<br>`2`=Regionaal<br>`2.5`=Nationaal<br>`3`=Internationaal |  |  |  |  |  |
| `watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep` | radio | Wat is de belangrijkste leeftijdscategorie van de doelgroep? | ✓ |  | `0.25`=0-15 jaar / met begeleiding<br>`0.5`=0-15 jaar / zonder begeleiding<br>`0.75`=15-18 jaar<br>`0.5`=18-30 jaar<br>`0.25`=30-70 jaar<br>`1`=70+ jaar<br>`0.75`=Alle leeftijden |  |  |  |  |  |
| `isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid` | radio | Is er sprake van aanwezigheid van politieke aandacht en/of mediageniekheid? | ✓ |  | `0`=Nee<br>`1`=Ja |  |  |  |  |  |
| `isEenDeelVanDeDoelgroepVerminderdZelfredzaam` | radio | Is een deel van de doelgroep verminderd zelfredzaam? | ✓ |  | `1`=Niet zelfredzaam<br>`0.5`=Beperkt zelfredzaam<br>`0.25`=Voldoende zelfredzaam<br>`0`=Volledig zelfredzaam |  |  |  |  |  |
| `isErSprakeVanAanwezigheidVanRisicovolleActiviteiten` | radio | Is er sprake van aanwezigheid van risicovolle activiteiten? | ✓ |  | `0`=Nee<br>`1`=Ja |  |  |  |  |  |
| `watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep` | radio | Wat is het grootste deel van de samenstelling van de doelgroep? | ✓ |  | `0.5`=Alleen toeschouwers<br>`0.75`=Combinatie toeschouwers en deelnemers<br>`1`=Alleen deelnemers |  |  |  |  |  |
| `isErSprakeVanOvernachten` | radio | Is er sprake van overnachten? | ✓ |  | `0`=Er wordt niet overnacht of er wordt overnacht op een daartoe bestemde locatie<br>`1`=Er wordt overnacht op een niet daartoe bestemde locatie |  |  |  |  |  |
| `isErGebruikVanAlcoholEnDrugs` | radio | Is er gebruik van alcohol en drugs? | ✓ |  | `0`=Niet aanwezig<br>`0.5`=Aanwezig, zonder risicoverwachting<br>`1`=Aanwezig, met risicoverwachting |  |  |  |  |  |
| `watIsHetAantalGelijktijdigAanwezigPersonen` | radio | Wat is het aantal gelijktijdig aanwezig personen? | ✓ |  | `0`=Minder dan 150<br>`0.25`=150 - 2.000<br>`0.5`=2.000 - 5.000<br>`0.75`=5.000 - 10.000<br>`1`=10.000 - 15.000<br>`1.25`=> 15.000 |  |  |  |  |  |
| `inWelkSeizoenVindtHetEvenementPlaats` | radio | In welk seizoen vindt het evenement plaats? | ✓ |  | `0.25`=Lente of herfst<br>`0.5`=Zomer of winter |  |  |  |  |  |
| `inWelkeLocatieVindtHetEvenementPlaats` | radio | In welke locatie vindt het evenement plaats? | ✓ |  | `0.25`=In een gebouw, als een daartoe ingerichte evenementenlocatie<br>`0.75`=In een gebouw, als een niet daartoe ingerichte evenementenlocatie<br>`0.75`=In een bouwsel<br>`0.5`=In de open lucht, op een daartoe ingericht evenemententerrein<br>`0.75`=In de open lucht, op een niet daartoe ingericht evenemententerrein<br>`1`=Op, aan of in het water |  |  |  |  |  |
| `opWelkSoortOndergrondVindtHetEvenementPlaats` | radio | Op welk soort ondergrond vindt het evenement plaats? | ✓ |  | `0.25`=Verharde ondergrond<br>`0.5`=Onverharde ondergrond, vochtdoorlatend<br>`0.75`=Onverharde ondergrond, drassig |  |  |  |  |  |
| `watIsDeTijdsduurVanHetEvenement` | radio | Wat is de tijdsduur van het evenement? | ✓ |  | `0`=Minder dan 3 uur tijdens daguren<br>`0.25`=Minder dan 3 uur tijdens avond- en nachturen<br>`0.5`=Tijdsduur van 3-12 uren tijdens de daguren<br>`0.75`=Tijdsduur van 3 - 12 uren tijdens de avond- en nachturen<br>`1`=Hele dag (tijdsduur tussen 12 en 24 uur)<br>`1.25`=Meerdere aaneengesloten dagen |  |  |  |  |  |
| `welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing` | radio | Welke beschikbaarheid van aan- en afvoerwegen is van toepassing? | ✓ |  | `1`=Geen aan- en afvoerwegen<br>`0.75`=Matige aan- en afvoerwegen<br>`0.5`=Redelijke aan- en afvoerwegen<br>`0`=Goede aan- en afvoerwegen |  |  |  |  |  |
| `risicoClassificatieContent` | content | _content_ |  | ✓ |  |  |  |  |  |  |

### Stap 8: Vergunningsaanvraag: soort

> UUID: `ae44ab5b-c068-4ceb-b121-6e6907f78ef9` · slug: `vragenboom-2` · velden: 17

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `voordatUVerderGaatMetHetBeantwoordenVanDeVragenVoorUwEvenementWillenWeGraagWetenOfUEerderEenVooraankondigingHeeftIngevuldVoorDitEvenement` | radio | Voordat u verder gaat met het beantwoorden van de vragen voor uw evenement willen we graag weten of u eerder een vooraankondiging heeft ingevuld voor dit evenement? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `watIsTijdensDeHeleDuurVanUwEvenementWatIsDeNaamVanHetEvenementVergunningHetTotaalAantalAanwezigePersonenVanAlleDagenBijElkaarOpgeteld` | number | Wat is tijdens de hele duur van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} het totaal aantal aanwezige personen van alle dagen bij elkaar opgeteld? | ✓ |  |  |  |  |  |  |  |
| `watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX` | number | Wat is het maximaal aanwezige aantal personen dat op enig moment aanwezig kan zijn bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  |  |  |
| `watZijnDeBelangrijksteLeeftijdscategorieenVanHetPubliekTijdensUwEvenement` | radio | Wat zijn de belangrijkste leeftijdscategorieen van het publiek tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `018Jaar`=0 - 18 jaar<br>`1830Jaar`=18 - 30 jaar<br>`3045Jaar`=30 - 45 jaar<br>`45JaarEnOuder`=45 jaar en ouder |  |  |  |  |  |
| `isUwEvenementXGratisToegankelijkVoorHetPubliek` | radio | Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} gratis toegankelijk voor het publiek? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `kruisAanWatVanToepassingIsVoorUwEvenementX` | selectboxes | Kruis aan wat van toepassing is voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? |  |  | `A1`=(Versterkte) muziek<br>`A2`=Versterkte spraak<br>`A3`=Bouwsels plaatsen groter dan 10m2, zoals tenten of podia<br>`A4`= Een kansspel organiseren, zoals een bingo of loterij <br>`A5`=Alcoholhoudende dranken verkopen<br>`A6`=Niet-alcoholische dranken verkopen <br>`A7`=Eten bereiden of verkopen<br>`A8`=Het evenement belemmert het doorgaand verkeer (omleiden, vertragen)<br>`A9`=Een deel van een doorgaande weg gebruiken voor het evenement<br>`A10`=Een (een deel van) de weg of vaarweg afsluiten voor doorgaand verkeer<br>`A11`=Toegang voor hulpdiensten  tot de evenementlocatie(s) (en de omliggende percelen en gebouwen) is beperkt. |  | `{"A1":false,"A2":false,"A3":f…` |  |  |  |
| `welkeVoorzieningenZijnAanwezigBijUwEvenement` | selectboxes | Welke voorzieningen zijn aanwezig bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? |  |  | `A12`=WC's plaatsen (of bestaande gebruiken) <br>`A13`=Douches plaatsen (of bestaande gebruiken) <br>`A53`=Beveiligers inhuren<br>`A14`=Medische voorzieningen  treffen (Veldnorm Evenementenzorg - EHBO)<br>`A15`=Verzorging van kinderen jonger dan 12 jaar<br>`A16`=Verzorging mensen met een lichamelijke of geestelijke beperking<br>`A17`=Overnachtingen<br>`A18`=Tenten of Podia<br>`A19`=Tribunes<br>`A20`=Overkappingen<br>`A21`=Omheining van de evenementenlocatie(s)<br>`A22`=Overige bouwwerken |  | `{"A12":false,"A13":false,"A14…` |  |  |  |
| `welkeOverigeBouwwerkenGaatUPlaatsen` | textarea | Welke overige bouwwerken gaat u plaatsen? | ✓ |  |  | maxLength=10000 |  |  | toon als [welkeVoorzieningenZijnAanwezigBijUwEvenement].[A22] = true |  |
| `welkeVoorwerpenGaatUPlaatsenBijUwEvenementX` | selectboxes | Welke voorwerpen gaat u plaatsen bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? |  |  | `A23`=Verkooppunten  voor toegangskaarten<br>`A24`=Verkooppunten  voor consumptiemunten of -bonnen<br>`A25`=Speeltoestellen Attractietoestellen<br>`A26`=Aggregaten,  brandstofopslag en andere brandgevaarlijke stoffen<br>`A27`=Geluidstorens<br>`A28`=Lichtmasten<br>`A29`=Marktkramen<br>`A30`=Andere voorwerpen |  | `{"A23":false,"A24":false,"A25…` |  |  |  |
| `welkeAnderVoorwerpenGaatUPlaatsenBijEvenementX` | textarea | welke ander voorwerpen gaat u plaatsen bij evenement {{ watIsDeNaamVanHetEvenementVergunning }}? |  |  |  | maxLength=10000 |  |  | toon als [welkeVoorwerpenGaatUPlaatsenBijUwEvenementX].[A30] = true |  |
| `kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX` | selectboxes | Kruis aan welke overige maatregelen/gevolgen van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? |  |  | `A31`=Toegangscontrole<br>`A32`=(Laten) aanpassen locatie en/of verwijderen straatmeubilair<br>`A33`=Er ontstaat extra afval<br>`A34`=Gebruik van eco-glazen of statiegeld op (plastic)glazen<br>`A35`=Er zijn vrij toegankelijke drinkwatervoorzieningen beschikbaar<br>`A36`=Waterverneveling, bijvoorbeeld door fonteinen, douches of andere waterbronnen (Legionellapreventie) |  | `{"A31":false,"A32":false,"A33…` |  |  |  |
| `welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX` | selectboxes | Welke van de onderstaande activiteiten vinden verder nog plaats tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? |  |  | `A37`=Ballonnen oplaten<br>`A38`=Lasershow<br>`A39`=(Reclame)zeppelin oplaten<br>`A40`=Activiteiten met dieren<br>`A41`=Vuurwerk afsteken<br>`A42`=Tatoeages,  piercings, of permanente make-up aanbrengen<br>`A43`=Open vuur (vuurkorven, feestvuren etc.)<br>`A44`=Kanon-, carbid- of kamerschieten<br>`A45`=Showeffecten<br>`A106`=Gebruik van drones<br>`A46`=Overig |  | `{"A37":false,"A38":false,"A39…` |  |  |  |
| `welkActiviteitBetreftUwEvenementX` | textarea | Welk activiteit betreft uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  | maxLength=10000 |  |  | toon als [welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX].[A46] = true |  |
| `kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX` | selectboxes | Kruis aan wat voor overige kenmerken van toepassing zijn voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} |  |  | `A48`=Voertuigen parkeren die langer zijn dan 6 meter en/of hoger dan 2,40 meter<br>`A49`=Voorwerpen op de weg plaatsen<br>`A50`=Bewegwijzering aanbrengen<br>`A51`=Verkeersregelaars inzetten<br>`A52`=Vervoersmaatregelen nemen (parkeren, openbaar vervoer, pendelbussen) |  | `{"A48":false,"A49":false,"A50…` |  |  |  |
| `isUwEvenementToegankelijkVoorMensenMetEenBeperking` | radio | Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} toegankelijk voor mensen met een beperking? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [welkeVoorzieningenZijnAanwezigBijUwEvenement].[A16] = true |  |
| `voorHoeveelMensenMetEenLichamelijkeOfGeestelijkeBeperkingVerzorgtUOpvangTijdensUwEvenementX` | number | Voor hoeveel mensen met een lichamelijke of geestelijke beperking verzorgt u opvang tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  | toon als [welkeVoorzieningenZijnAanwezigBijUwEvenement].[A16] = true |  |
| `welkeMaatregelenHeeftUGenomenOmMensenMetEenBeperkingOngehinderdDeelTeLatenNemenAanUwEvenement` | textarea | Welke maatregelen heeft u genomen om mensen met een beperking ongehinderd deel te laten nemen aan uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  | maxLength=10000 |  |  | toon als [isUwEvenementToegankelijkVoorMensenMetEenBeperking] = [Ja] |  |

### Stap 9: Vergunningaanvraag: kenmerken

> UUID: `661aabb7-e927-4a75-8d95-0a665c5d83fe` · slug: `vergunningaanvraag-vervolgvragen` · velden: 68

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `versterkteMuziek` | fieldset | Versterkte muziek <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content5` | content | _content_ |  |  |  |  |  |  |  |  |
| — `wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning` | selectboxes | Wie maakt de muziek op locatie bij uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? <sub>(hidden)</sub> | ✓ | ✓ | `dj`=DJ<br>`band`=Band<br>`orkest`=Orkest<br>`tapeArtiest`=(Tape-)artiest<br>`anders`=Anders |  | `{"dj":false,"band":false,"and…` |  |  |  |
| — `opWelkeAndereManierWordtErMuziekGemaakt` | textarea | Op welke andere manier wordt er muziek gemaakt? | ✓ |  |  | maxLength=10000 |  |  | toon als [wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning].[anders] = true |  |
| — `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` | selectboxes | Welke soorten muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}? <sub>(hidden)</sub> | ✓ | ✓ | `A69`=Klassiek<br>`A70`=Jazz<br>`A71`=Dance<br>`A72`=Pop (en overige) |  | `{"A69":false,"A70":false,"A71…` |  |  |  |
| — `welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX` | selectboxes | Welke soorten Dance muziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `acid`=Acid<br>`ambient`=Ambient<br>`club`=Club<br>`disco`=Disco<br>`drumNBass`=Drum 'n Bass<br>`electro`=Electro<br>`garage`=Garage<br>`hardcore`=Hardcore<br>`house`=House<br>`hardstyle`=Hardstyle<br>`jungle`=Jungle<br>`lounge`=Lounge<br>`techno`=Techno<br>`trance`=Trance<br>`edm`=EDM |  | `{"edm":false,"acid":false,"cl…` |  | toon als [welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX].[A71] = true |  |
| — `welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement` | selectboxes | Welke soorten popmuziek zijn er te horen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `blues`=Blues<br>`country`=Country<br>`disco`=Disco<br>`funk`=Funk<br>`hiphop`=Hiphop<br>`hardrock`=Hardrock<br>`kindermuziek`=Kindermuziek<br>`metal`=Metal<br>`nederlandstaligeVolksmuziek`=Nederlandstalige volksmuziek<br>`carnavalsmuziek`=Carnavalsmuziek<br>`punk`=Punk<br>`rB`=R&B<br>`rap`=Rap<br>`reggae`=Reggae<br>`rock`=Rock<br>`rockNRollSchlager`=Rock 'n Roll Schlager<br>`soul`=Soul<br>`anders`=Anders |  | `{"rB":false,"rap":false,"funk…` |  | toon als [welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX].[A72] = true |  |
| — `welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX` | textarea | Welke ander soort popmuziek is er te horen op evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  | maxLength=10000 |  |  | toon als [welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement].[anders] = true |  |
| — `watIsDeGeluidsbelastingInDecibelDBANorm0103DBVanUwEvenementX` | number | Wat is de geluidsbelasting in decibel (dB(A) norm - (0–103 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  | max=103 |  |  |  |  |
| — `watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement` | number | Wat is de geluidsbelasting in decibel Db(C) norm - (0–113 dB)) van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  | max=103 |  |  |  |  |
| `bouwsels10MSup2Sup` | fieldset | Bouwsels &gt; 10m<sup>2</sup> <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content15` | content | _content_ |  |  |  |  |  |  |  |  |
| — `watVoorBouwselsPlaatsUOpDeLocaties` | selectboxes | Wat voor bouwsels plaats u op de locaties? <sub>(hidden)</sub> | ✓ | ✓ | `A54`=Tent(en)<br>`A55`=Podia<br>`A56`=Overkappingen<br>`A57`=Omheiningen |  | `{"A54":false,"A55":false,"A56…` |  |  |  |
| — `tenten` | editgrid | Welke tenten plaatst u? <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `tentnummer` | textfield | Tentnummer | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `lengteTent` | number | Lengte in meter | ✓ |  |  |  |  |  |  |  |
| —— `BreedteTent` | number | Breedte in meter | ✓ |  |  |  |  |  |  |  |
| —— `HoogteTent` | number | Hoogte in meter | ✓ |  |  |  |  |  |  |  |
| —— `wijzeVanVerankering` | radio | Wijze van verankering | ✓ |  | `palenInDeGrond`=Palen in de grond<br>`betonblokken`=Betonblokken |  |  |  |  |  |
| — `podia` | editgrid | Welke podia plaatst u? <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `podiumnummer` | textfield | Podium nummer | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `lengtePodium` | number | Lengte in meter | ✓ |  |  |  |  |  |  |  |
| —— `BreedtePodium` | number | Breedte in meter | ✓ |  |  |  |  |  |  |  |
| —— `HoogtePodium` | number | Hoogte in meter | ✓ |  |  |  |  |  |  |  |
| — `overkappingen` | editgrid | Welke overkappingen plaatst u? <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `overkappingnummer` | textfield | Overkapping nummer | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `lengteOverkapping` | number | Lengte in meter | ✓ |  |  |  |  |  |  |  |
| —— `BreedteOverkapping` | number | Breedte in meter | ✓ |  |  |  |  |  |  |  |
| —— `HoogteOverkapping` | number | Hoogte in meter | ✓ |  |  |  |  |  |  |  |
| —— `wijzeVanVerankering1` | radio | Wijze van verankering | ✓ |  | `palenInDeGrond`=Palen in de grond<br>`betonblokken`=Betonblokken |  |  |  |  |  |
| — `geefEenOmschrijvingVanSoortOmheining` | textarea | Geef een omschrijving van soort omheining | ✓ |  |  | maxLength=10000 |  |  | toon als [watVoorBouwselsPlaatsUOpDeLocaties].[A57] = true |  |
| — `plaatstUTijdelijkeConstructiesTentenPodiaEtcDanDientUNaastHetVeiligheidsplanTevensEenDeelplanTijdelijkeConstructiesTeMakenEnTeUploadenAlsBijlage` | file | Plaatst u tijdelijke constructies (tenten, podia etc.) dan dient u naast het veiligheidsplan tevens een 'Deelplan Tijdelijke constructies' te maken en te uploaden als bijlage. |  |  |  |  |  |  |  |  |
| `kansspelen` | fieldset | Kansspelen <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content16` | content | _content_ |  |  |  |  |  |  |  |  |
| — `welkSoortKansspelBetreftHet` | textarea | Welk soort kansspel betreft het? | ✓ |  |  | maxLength=10000 |  |  |  |  |
| — `isDeOrganisatieVanHetKansspelInHandenVanEenVereniging` | radio | Is de organisatie van het kansspel in handen van een vereniging? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar` | radio | Bestaat de vereninging, die het kansspel organiseert langer dan 3 jaar? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  | toon als [isDeOrganisatieVanHetKansspelInHandenVanEenVereniging] = [Ja] |  |
| — `watBentUVanPlanMetDeOpbrengstVanHetKansspelTeGaanDoen` | textarea | Wat bent u van plan met de opbrengst van het kansspel te gaan doen? | ✓ |  |  | maxLength=10000 |  |  |  |  |
| — `geefEenIndicatieVanDeHoogteVanHetPrijzengeld` | currency | Geef een indicatie van de hoogte van het prijzengeld | ✓ |  |  |  |  |  |  |  |
| `alcoholischeDranken` | fieldset | Alcoholische dranken <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content17` | content | _content_ |  |  |  |  |  |  |  |  |
| — `isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop` | radio | Is een persoon of organisatie verantwoordelijk voor de alcoholverkoop? | ✓ |  | `persoon`=Persoon<br>`organisatie`=Organisatie |  |  |  |  |  |
| — `persoongroep` | fieldset | Persoongroep |  |  |  |  |  |  | toon als [isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop] = [persoon] |  |
| —— `voornaamVanDePersoonAlcohol` | textfield | Voornaam van de persoon | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `achternaamVanDePersoon1Alcohol` | textfield | Achternaam van de persoon | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `geboortedatumPersoonAlcohol` | date | Geboortedatum persoon | ✓ |  |  |  |  |  |  |  |
| —— `geboorteplaatsPersoonAlcohol` | textfield | Geboorteplaats persoon | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `organisatiegroep` | fieldset | Organisatiegroep |  |  |  |  |  |  | toon als [isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop] = [organisatie] |  |
| —— `watIsDeNaamVanDeOrganisatie` | textfield | Wat is de naam van de organisatie? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `watIsHetTelefoonnummerVanDeOrganisatie` | phoneNumber | Wat is het telefoonnummer van de organisatie? | ✓ |  |  |  |  |  |  |  |
| — `watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken` | editgrid | Op hoeveel punten en op welke locaties gaat u dranken en voedsel verstrekken? | ✓ |  |  |  |  |  |  |  |
| —— `naamVanDeLocatie` | textfield | Naam van de locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `uitgiftepuntenVoedsel` | number | Uitgiftepunten voedsel | ✓ |  |  |  |  |  |  |  |
| —— `uitgiftepuntenDrank` | number | Uitgiftepunten drank | ✓ |  |  |  |  |  |  |  |
| —— `waarvanMetAlcohol` | number | Waarvan met alcohol | ✓ |  |  |  |  |  | verberg als [watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken.uitgiftepuntenDrank] = [0] |  |
| `etenBereidenOfVerkopen` | fieldset | Eten bereiden of verkopen <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content18` | content | _content_ |  |  |  |  |  |  |  |  |
| — `welkSoortBereidingVanEtenswarenIsVanToepassingOpLocatieEvenementX` | radio | Welk soort bereiding van etenswaren is van toepassing op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `beperkteBereiding`=Beperkte bereiding<br>`eenvoudigeBereiding`=Eenvoudige bereiding<br>`uitgebreideBereiding`=Uitgebreide bereiding |  |  |  |  |  |
| — `maaktUGebruikVanEenCateraarSOpLocatieEvenementX` | radio | Maakt u gebruik van een cateraar(s) op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX` | selectboxes | Met welke warmtebron wordt het eten ter plaatse klaargemaakt  op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}? <sub>(hidden)</sub> | ✓ | ✓ | `gas`=Gas<br>`houtskoolbarbecueOfHoutoven`=Houtskoolbarbecue of houtoven<br>`elektrisch`=Elektrisch<br>`frituur`=Frituur<br>`anders`=Anders |  | `{"gas":false,"anders":false,"…` |  |  |  |
| — `welkeAndereWarmtebronWordtGebruikt` | textarea | Welke andere warmtebron wordt gebruikt? | ✓ |  |  | maxLength=10000 |  |  | toon als [metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX].[anders] = true |  |
| `belemmeringVanVerkeer` | fieldset | Belemmering van verkeer <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content19` | content | _content_ |  |  |  |  |  |  |  |  |
| — `beschrijfOpWelkeWijzeErSprakeIsVanBelemmeringVanVerkeer` | textarea | Beschrijf op welke wijze er sprake is van belemmering van verkeer | ✓ |  |  | maxLength=10000 |  |  |  |  |
| `wegOfVaarwegAfsluiten` | fieldset | Weg of vaarweg afsluiten <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content20` | content | _content_ |  |  |  |  |  |  |  |  |
| — `welkeDoorgangenWiltUAfsluiten` | editgrid | Welke doorgangen wilt u afsluiten? | ✓ |  |  |  |  |  |  |  |
| —— `positieVanDeDoorgang` | map | Positie van de doorgang | ✓ |  |  |  |  |  |  |  |
| —— `naamVanDeDoorgang` | textfield | Naam van de doorgang | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `startVanDeAfsluiting` | datetime | Start van de afsluiting | ✓ |  |  |  |  |  |  |  |
| —— `eindVanDeAfsluiting` | datetime | Eind van de afsluiting | ✓ |  |  |  |  |  |  |  |
| `toegangVoorHulpdienstenIsBeperkt` | fieldset | Toegang voor hulpdiensten is beperkt <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content21` | content | _content_ |  |  |  |  |  |  |  |  |
| — `watIsDeNaamVanDeFunctionarisOfPersoonDieDeTaakHeeftOmInGevalVanEenCalamiteitDeHulpdienstenOpTeVangen` | textfield | Wat is de naam van de functionaris of persoon die de taak heeft om in geval van een calamiteit de hulpdiensten op te vangen? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `watIsHetTelefoonnummerVanDeFunctionarisOfPersoonDieDeTaakHeeftOmInGevalVanEenCalamiteitDeHulpdienstenOpTeVangen` | phoneNumber | Wat is het telefoonnummer van de functionaris of persoon die de taak heeft om in geval van een calamiteit de hulpdiensten op te vangen? | ✓ |  |  |  |  |  |  |  |
| — `vermeldWaarBinnenOfBijHetEvenemententerreinDeHulpdienstenWordenOpgevangenInGevalVanEenCalamiteit` | textarea | Vermeld waar binnen of bij het evenemententerrein de hulpdiensten worden opgevangen in geval van een calamiteit. | ✓ |  |  | maxLength=10000 |  |  |  |  |

### Stap 10: Vergunningsaanvraag: voorzieningen

> UUID: `f4e91db5-fd74-4eba-b818-96ed2cc07d84` · slug: `vergunningsaanvraag-voorzieningen` · velden: 46

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `wCs` | fieldset | WC's <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content23` | content | _content_ |  |  |  |  |  |  |  |  |
| — `hoeveelVasteToilettenZijnBeschikbaar` | number | Hoeveel vaste toiletten zijn beschikbaar? | ✓ |  |  |  |  |  |  |  |
| — `hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar` | number | Hoeveel tijdelijke chemische toiletten / Dixies zijn er beschikbaar? | ✓ |  |  |  |  |  |  |  |
| — `hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar` | number | Hoeveel tijdelijke gespoelde toiletten zijn er beschikbaar? | ✓ |  |  |  | `0` |  | verberg als [hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar] = [0] |  |
| — `welkPercentageVanDeToilettenIsVoorHeren` | number | Hoeveel toiletten zijn voor heren? | ✓ |  |  | min=0 |  |  |  |  |
| — `aantalToilettenDamen` | number | Hoeveel toiletten zijn voor dames? | ✓ |  |  | min=0 |  |  |  |  |
| — `aantalToilettenMiva` | number | Hoeveel toiletten zijn voor MIVA/rolstoelgebruikers? | ✓ |  |  | min=0 |  |  |  |  |
| — `handenwaspunten` | number | Hoeveel handenwaspunten worden er bij de toiletten ingericht op locatie Evenement | ✓ |  |  | min=0 |  |  |  |  |
| — `reinigtUDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunning` | radio | Reinigt u de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `gebruikenDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunningVoorHetSpoelenOppervlaktewater` | radio | Gebruiken de tijdelijke toiletten op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor het spoelen oppervlaktewater? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `douches` | fieldset | Douche's <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content24` | content | _content_ |  |  |  |  |  |  |  |  |
| — `hoeveelVasteDouchevoorzieningenZijnBeschikbaar` | number | Hoeveel vaste douchevoorzieningen zijn beschikbaar? | ✓ |  |  |  |  |  |  |  |
| — `hoeveelTijdelijkeDouchevoorzieningenZijnBeschikbaar` | number | Hoeveel tijdelijke douchevoorzieningen zijn beschikbaar? | ✓ |  |  |  |  |  |  |  |
| — `wordenDeDouchesTussentijdsSchoonGemaakt` | radio | Worden de douches tussentijds schoon gemaakt? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| `ehbo` | fieldset | EHBO <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content25` | content | _content_ |  |  |  |  |  |  |  |  |
| — `aantalVasteEersteHulpposten` | number | Aantal vaste eerste hulpposten | ✓ |  |  |  |  |  |  |  |
| — `aantalMobieleEersteHulpteams` | number | Aantal mobiele eerste hulpteams | ✓ |  |  |  |  |  |  |  |
| — `aantalEersteHulpverlenersMetNiveauBasisEersteHulp` | number | Aantal Eerste hulpverleners met niveau 'Basis eerste hulp' | ✓ |  |  |  |  |  |  |  |
| — `aantalEersteHulpverlenersMetNiveauEvenementenEersteHulp` | number | Aantal Eerste hulpverleners met niveau 'Evenementen eerste hulp' | ✓ |  |  |  |  |  |  |  |
| — `aantalZorgprofessionalsMetNiveauBasisZorg` | number | Aantal Zorgprofessionals met niveau 'Basis Zorg' | ✓ |  |  |  |  |  |  |  |
| — `aantalZorgprofessionalsMetNiveauSpoedZorg` | number | Aantal Zorgprofessionals met niveau 'Spoed Zorg' | ✓ |  |  |  |  |  |  |  |
| — `aantalZorgprofessionalsMetNiveauMedischeZorg` | number | Aantal Zorgprofessionals met niveau 'Medische Zorg' | ✓ |  |  |  |  |  |  |  |
| — `aantalZorgprofessionalsMetNiveauSpecialistischeSpoedzorg` | number | Aantal Zorgprofessionals met niveau 'Specialistische Spoedzorg' | ✓ |  |  |  |  |  |  |  |
| — `aantalZorgprofessionalsMetNiveauArtsenSpecialistischeSpoedzorg` | number | Aantal Zorgprofessionals met niveau 'Artsen specialistische Spoedzorg' | ✓ |  |  |  |  |  |  |  |
| — `welkeOrganisatieVerzorgtDeEersteHulp` | textfield | Welke organisatie verzorgt de eerste hulp? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| `verzorgingVanKinderenJongerDan12Jaar` | fieldset | Verzorging van kinderen jonger dan 12 jaar <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `voorHoeveelKinderenInTotaalJongerDan12JaarIsVerzorgingNodig` | number | Voor hoeveel kinderen in totaal jonger dan 12 jaar is verzorging nodig? | ✓ |  |  |  |  |  |  |  |
| — `hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan04Jaar` | number | Hoeveel van het totaal aantal kinderen onder 12 jaar valt in de leeftijdscategorie van 0-4 jaar? | ✓ |  |  |  |  |  |  |  |
| — `hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan512Jaar` | number | Hoeveel van het totaal aantal kinderen onder 12 jaar valt in de leeftijdscategorie van 5-12 jaar? | ✓ |  |  |  |  |  |  |  |
| — `opWelkeLocatieOfLocatiesVindErOpvangVanDeKinderenOnder12JaarPlaats` | editgrid | Op welke locatie of locaties vind er opvang van de kinderen onder 12 jaar plaats? | ✓ |  |  |  |  |  |  |  |
| —— `locatieVanOpvangVanDeKinderenOnder12Jaar` | map | Locatie van opvang van de kinderen onder 12 jaar | ✓ |  |  |  |  |  |  |  |
| `overnachtingen` | fieldset | Overnachtingen <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `voorHoeveelMensenVerzorgtUOvernachtingenTijdensUwEvenement1` | number | Voor hoeveel mensen verzorgt u overnachtingen tijdens uw Evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  |  |  |  |  |  |  |
| — `isErSprakeVanOvernachtenDoorPubliekDeelnemers` | radio | Is er sprake van overnachten door publiek/deelnemers? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1` | editgrid | Op welke locatie of locaties is er sprake van overnachten door publiek/deelnemers? <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieVanOvernachtenDoorPubliekDeelnemers` | map | Locatie van overnachten door publiek/deelnemers | ✓ |  |  |  |  |  |  |  |
| — `isErSprakeVanOvernachtenDoorPubliekDeelnemers1` | radio | Is er sprake van overnachten door personeel/organisatie? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2` | editgrid | Op welke locatie of locaties is er sprake van overnachten door personeel/organisatie? <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieVanOvernachtenDoorPersoneelOrganisatie1` | map | Locatie van overnachten door personeel/organisatie | ✓ |  |  |  |  |  |  |  |
| `bouwsels` | fieldset | Bouwsels <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content26` | content | _content_ |  |  |  |  |  |  |  |  |
| — `watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc` | number | Wat is het maximale aantal personen dat tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} aanwezig is in een tent of andere besloten ruimte (podium, bouwwerk etc)? <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| `beveiligers1` | fieldset | Beveiligers <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content36` | content | _content_ |  |  |  |  |  |  |  |  |
| — `gegevensBeveiligingsorganisatieOpLocatieEvenementX1` | textarea | Gegevens beveiligingsorganisatie op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} | ✓ |  |  | maxLength=10000 |  |  |  |  |
| — `vergunningnummerBeveiligingsorganisatie1` | number | Vergunningnummer beveiligingsorganisatie | ✓ |  |  |  |  |  |  |  |
| — `vestigingsplaatsBeveiligingsorganisatie1` | textfield | Vestigingsplaats beveiligingsorganisatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| — `aantalBeveiligersOpLocatieEvenementX1` | number | Aantal beveiligers op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} | ✓ |  |  |  |  |  |  |  |

### Stap 11: Vergunningsaanvraag: voorwerpen

> UUID: `d790edb5-712a-4f83-87a8-1a86e4831455` · slug: `vergunningsaanvraag-voorwerpen` · velden: 34

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `voorwerpen` | fieldset | Voorwerpen <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content27` | content | _content_ |  |  |  |  |  |  |  |  |
| — `verkooppuntenToegangsKaarten` | editgrid | Verkooppunten toegangs-kaarten <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieVerkooppuntToegangskaart` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantapVerkoopuntenToegangskaarten` | number | Aantal verkoopunten | ✓ |  |  |  |  |  |  |  |
| — `verkooppuntenMuntenEnBonnen` | editgrid | Verkooppunten munten en bonnen <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieVerkooppuntMuntenBonnen` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantapVerkoopuntenMuntenBonnen` | number | Aantal verkoopunten | ✓ |  |  |  |  |  |  |  |
| — `verkooppuntenCashless` | editgrid | Verkooppunten cashless <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieVerkooppuntCashless` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantapVerkoopuntenCashless` | number | Aantal verkoopunten | ✓ |  |  |  |  |  |  |  |
| — `Speeltoestellen` | editgrid | Speeltoestellen <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatiespeeltoestellen` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantalSpeeltoestellen` | number | Aantal speeltoestellen | ✓ |  |  |  |  |  |  |  |
| — `brandstofopslag` | editgrid | Brandstofopslag <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatiebrandstofopslag` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantalbrandstofopslag` | number | Aantal brandstofopslag | ✓ |  |  |  |  |  |  |  |
| — `geluidstorens` | editgrid | Geluidstorens <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieGeluidstoren` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantalGeluidstoren` | number | Aantal geluidstorens | ✓ |  |  |  |  |  |  |  |
| — `Lichtmasten` | editgrid | Lichtmasten <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieLichtmast` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantalLichtmast` | number | Aantal lichtmasten | ✓ |  |  |  |  |  |  |  |
| — `marktkramen` | editgrid | Marktkramen <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieMarktkraam` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantalMarktkraam` | number | Aantal marktkramen | ✓ |  |  |  |  |  |  |  |
| — `andersGroup` | editgrid | Anders <sub>(hidden)</sub> | ✓ | ✓ |  |  |  |  |  |  |
| —— `locatieAnders` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `aantalAnders` | number | Aantal anders | ✓ |  |  |  |  |  |  |  |
| `brandgevaarlijkeStoffen` | fieldset | Brandgevaarlijke stoffen <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content28` | content | _content_ |  |  |  |  |  |  |  |  |
| — `welkeStoffenGebruiktU` | editgrid | Welke stoffen gebruikt u? | ✓ |  |  |  |  |  |  |  |
| —— `typeStof` | textfield | Type stof | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `plaatsStof` | textfield | Plaats | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `opslagwijzeStof` | textfield | Opslagwijze | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `toelichtingStof` | textfield | Toelichting | ✓ |  |  | maxLength=1000 |  |  |  |  |

### Stap 12: Vergunningaanvraag: maatregelen

> UUID: `8a5fb30f-287e-41a2-a9bc-e7340bdaaa99` · slug: `vergunningaanvraag-maatregelen` · velden: 24

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `aanpassenLocatieEnOfVerwijderenStraatmeubilair` | fieldset | Aanpassen locatie en/of verwijderen straatmeubilair <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content29` | content | _content_ |  |  |  |  |  |  |  |  |
| — `geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen` | textarea | Geef een omschrijving welke aanpassingen op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }} waar nodig zijn of welk straatmeubilair u wilt verwijderen of aanpassen. | ✓ |  |  | maxLength=10000 |  |  |  |  |
| `extraAfval` | fieldset | Extra afval <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content30` | content | _content_ |  |  |  |  |  |  |  |  |
| — `wieMaaktDeLocatiesEnDeOmgevingDaarvanSchoonEnWanneerGebeurtDat` | editgrid | Wie maakt de locaties en de omgeving daarvan schoon, en wanneer gebeurt dat? | ✓ |  |  |  |  |  |  |  |
| —— `locatieAfval` | textfield | Locatie | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `doorWieAfval` | textfield | Door wie? | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `starttijdSchoonmaak` | datetime | Starttijd schoonmaak | ✓ |  |  |  |  |  |  |  |
| —— `eindtijdSchoonmaak` | datetime | Eindtijd schoonmaak | ✓ |  |  |  |  |  |  |  |
| — `hoeveelExtraAfvalinzamelpuntenGaatUOpLocatieEvenementXPlaatsen` | number | Hoeveel extra afvalinzamelpunten gaat u op locatie Evenement {{ watIsDeNaamVanHetEvenementVergunning }}. plaatsen? | ✓ |  |  |  |  |  |  |  |
| — `doetUAanAfvalscheidingOpLocatieEvenementX` | radio | Doet u aan afvalscheiding op locatie evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `voertUDeSchoonmaakZelfUit` | radio | Voert u de schoonmaak zelf uit? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen` | file | U kunt het afvalplan hier uploaden of later als bijlage toevoegen. |  |  |  |  |  |  | toon als [voertUDeSchoonmaakZelfUit] = [Ja] |  |
| `gemeentelijkeHulpmiddelen` | fieldset | Gemeentelijke hulpmiddelen |  |  |  |  |  |  |  |  |
| — `wilUGebruikMakenVanGemeentelijkeHulpmiddelen` | radio | Wil U gebruik maken van gemeentelijke hulpmiddelen? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `veldengroep2` | fieldset | Veldengroep |  |  |  |  |  |  | toon als [wilUGebruikMakenVanGemeentelijkeHulpmiddelen] = [Ja] |  |
| —— `content37` | content | _content_ |  |  |  |  |  |  |  |  |
| —— `dranghekken1` | number | Dranghekken |  |  |  |  |  |  |  |  |
| —— `wegafzettingen1` | number | Wegafzettingen |  |  |  |  |  |  |  |  |
| —— `vlaggen1` | number | Vlaggen |  |  |  |  |  |  |  |  |
| —— `vlaggenmasten1` | number | Vlaggenmasten |  |  |  |  |  |  |  |  |
| —— `parkeerverbodsborden1` | number | Parkeerverbodsborden |  |  |  |  |  |  |  |  |
| —— `bordenGeslotenVerklaring1` | number | Borden gesloten verklaring |  |  |  |  |  |  |  |  |
| —— `bordenEenrichtingsweg1` | number | Borden eenrichtingsweg |  |  |  |  |  |  |  |  |
| —— `wenstUTegenBetalingStroomAfTeNemenVanDeGemeente1` | radio | Wenst u tegen betaling stroom af te nemen van de gemeente? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| —— `geefAanOpWelkeLocatieUStroomWilt1` | textarea | Geef aan op welke locatie u stroom wilt afnemen | ✓ |  |  | maxLength=10000 |  |  | toon als [wenstUTegenBetalingStroomAfTeNemenVanDeGemeente] = [Ja] |  |

### Stap 13: Vergunningsaanvraag: extra activiteiten

> UUID: `6e285ace-f891-4324-b54e-639c1cfff9fa` · slug: `vergunningsaanvraag-extra-activiteiten` · velden: 1

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `contentBalon` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `contentLasershow` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `contentZeppelin` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `contentDieren` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `contentVuurwerk` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `contentTattoo` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `contentVuurkorf` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `contentWapen` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement` | textarea | Welke showeffecten bent u van plan te organiseren voor uw evenement?' <sub>(hidden)</sub> | ✓ | ✓ |  | maxLength=10000 |  |  |  |  |

### Stap 14: Vergunningaanvraag: overig

> UUID: `e8f00982-ee47-4bec-bf31-a5c8d1b05e5e` · slug: `vergunningaanvraag-overig` · velden: 41

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `groteVoertuigen` | fieldset | Voorwerpen op de weg <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content32` | content | _content_ |  |  |  |  |  |  |  |  |
| — `geefAanOpWelkeDataEnTijdenUDeVoorwerpenWiltPlaatsenOpDeOpenbareWegOfGroteVoertuigenWiltParkerenInDeBuurtVanHetEvenement` | editgrid | Geef aan op welke data en tijden u de voorwerpen wilt plaatsen op de openbare weg of grote voertuigen wilt parkeren in de buurt van het evenement |  |  |  |  |  |  |  |  |
| —— `voorwerp` | textfield | Voorwerp | ✓ |  |  | maxLength=1000 |  |  |  |  |
| —— `positieVanHetVoorwerp` | map | Positie van het voorwerp | ✓ |  |  |  |  |  |  |  |
| —— `startTijdstipVoorwerp` | datetime | Start tijdstip | ✓ |  |  |  |  |  |  |  |
| —— `eindTijdstipVoorwerp` | datetime | Eind tijdstip | ✓ |  |  |  |  |  |  |  |
| — `vulHierEventueelInformatieInOverHetPlaatsenVanVoorwerpenOpDeOpenbareWegOfHetParkerenVanGroteVoertuigen` | textarea | Vul hier eventueel informatie in over het plaatsen van voorwerpen op de openbare weg of het parkeren van grote voertuigen. |  |  |  | maxLength=10000 |  |  |  |  |
| `verkeersregelaars` | fieldset | Verkeersregelaars <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `content33` | content | _content_ |  |  |  |  |  |  |  |  |
| — `huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie` | radio | Huurt u de verkeersregelaars in bij een daarin gespecialiseerd bedrijf/organisatie? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars` | textarea | Zijn de in te zetten personen beroepsmatige verkeersregelaars of is er sprake van evenementenverkeersregelaars? | ✓ |  |  | maxLength=10000 |  |  | toon als [huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie] = [Ja] |  |
| — `content34` | content | _content_ |  |  |  |  |  |  | toon als [huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie] = [Nee] |  |
| — `hoeveelVerkeersregelaarsWiltUInzetten` | number | Hoeveel verkeersregelaars wilt u inzetten? | ✓ |  |  |  |  |  |  |  |
| `vervoersmaatregelen` | fieldset | Vervoersmaatregelen <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| — `uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs` | selectboxes | U heeft aangegeven, dat u extra vervoersmaatregelen wilt nemen voor bezoekers van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}. Kruis hier aan, wat van toepassing is | ✓ |  | `extraParkeerplekkenInrichten`=Extra parkeerplekken inrichten<br>`extraFietsenstallingenPlaatsen`=Extra fietsenstallingen plaatsen<br>`inzettenPendelbussen`=Inzetten pendelbussen<br>`extraOpenbaarVervoerRegelen`=Extra openbaar vervoer regelen<br>`bezoekersStimulerenMetHetOpenbaarVervoerTeKomen`=Bezoekers stimuleren met het openbaar vervoer te komen<br>`bezoekersStimulerenMetDeFietsTeKomen`=Bezoekers stimuleren met de fiets te komen<br>`anders`=Anders |  | `{"anders":false,"inzettenPend…` |  |  |  |
| — `welkeAndereMaatregelenUWiltNemen` | textarea | Welke andere maatregelen u wilt nemen | ✓ |  |  | maxLength=10000 |  |  | toon als [uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs].[anders] = true |  |
| — `metWelkeOpenbaarVervoermaatschappijenHeeftUExtraAfsprakenGemaaktOverHetOpenbaarVervoer` | textarea | Met welke openbaar vervoermaatschappijen heeft u extra afspraken gemaakt over het openbaar vervoer? | ✓ |  |  | maxLength=10000 |  |  |  |  |
| `promotieEnCommunicatie` | fieldset | Promotie en communicatie |  |  |  |  |  |  |  |  |
| — `wiltUPromotieMakenVoorUwEvenement` | radio | Wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `opWelkNiveauWiltUPromotieMaken` | radio | Op welk niveau wilt u promotie maken? | ✓ |  | `lokaal`=Lokaal<br>`regionaal`=Regionaal<br>`landelijk`=Landelijk<br>`lnternationaal`=lnternationaal |  |  |  | toon als [wiltUPromotieMakenVoorUwEvenement] = [Ja] |  |
| — `hoeWiltUPromotieMakenVoorUwEvenement` | selectboxes | Hoe wilt u promotie maken voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `driehoeksBorden`=(Driehoeks)borden<br>`posters`=Posters<br>`flyers`=Flyers<br>`spandoeken`=Spandoeken<br>`vlaggen`=Vlaggen<br>`anders`=Anders |  | `{"anders":false,"flyers":fals…` |  | toon als [wiltUPromotieMakenVoorUwEvenement] = [Ja] |  |
| — `opWelkeAndereManierWiltUPromotieMaken` | textarea | Op welke andere manier wilt u promotie maken? | ✓ |  |  | maxLength=10000 |  |  | toon als [hoeWiltUPromotieMakenVoorUwEvenement].[anders] = true |  |
| — `websiteVanUwEvenement` | textfield | Website van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} |  |  |  | maxLength=1000 |  |  |  |  |
| — `facebookVanUwEvenement1` | textfield | Facebookpagina van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} |  |  |  | maxLength=1000 |  |  |  |  |
| — `xPaginaVanUwEvenementWatIsDeNaamVanHetEvenementVergunning` | textfield | X-pagina van uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} |  |  |  | maxLength=1000 |  |  |  |  |
| `omwonendenCommunicatie` | fieldset | Omwonenden communicatie |  |  |  |  |  |  |  |  |
| — `geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX` | radio | Geeft u omwonenden en nabijgelegen bedrijven vooraf informatie over uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `opWelkeWijzeInformeertUHen` | textarea | Op welke wijze informeert u hen? | ✓ |  |  | maxLength=10000 |  |  | toon als [geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX] = [Ja] |  |
| — `wiltUDeInformatieTekstAanDeOmwonendeAlsBijlageToevoegen` | file | Wilt u de informatie-tekst aan de omwonende als bijlage toevoegen? |  |  |  |  |  |  |  |  |
| `organisatorischeAchtergrond` | fieldset | Organisatorische achtergrond |  |  |  |  |  |  |  |  |
| — `organiseertUUwEvenementXVoorDeEersteKeer` | radio | Organiseert u uw evenement {{ watIsDeNaamVanHetEvenementVergunning }} voor de eerste keer? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen` | textarea | Welke ervaring heeft de organisator met het organiseren van evenementen? |  |  |  | maxLength=10000 |  |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| — `welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs` | textarea | Welke relevante ervaring heeft het personeel dat de organisator inhuurt via intermediairs? |  |  |  | maxLength=10000 |  |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| — `welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt` | textarea | Welke relevante ervaring heeft het personeel van (onder)aannemers aan wie de organisator werk uitbesteedt? |  |  |  | maxLength=10000 |  |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| — `welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet` | textarea | Welke relevante ervaring hebben de vrijwilligers die de organisator  inzet? |  |  |  | maxLength=10000 |  |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| `huisregelsEnFlankerendeEvenementen` | fieldset | Huisregels en flankerende evenementen |  |  |  |  |  |  |  |  |
| — `hanteertUHuisregelsVoorUwEvenementX` | radio | Hanteert u huisregels voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `uKuntHierHetHuisregelementUploaden` | file | U kunt hier het huisregelement uploaden |  |  |  |  |  |  | toon als [hanteertUHuisregelsVoorUwEvenementX] = [Ja] |  |
| — `organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024` | radio | Organiseert u ook flankerende evenementen (side events) tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `lichtDeSideEventsToe` | textarea | Licht de side events toe | ✓ |  |  | maxLength=10000 |  |  | toon als [organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024] = [Ja] |  |
| `verzekering` | fieldset | Verzekering |  |  |  |  |  |  |  |  |
| — `heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement` | radio | Heeft u een evenementenverzekering afgesloten voor uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}? | ✓ |  | `Ja`=Ja<br>`Nee`=Nee <sub>(jaNeeLijst)</sub> |  |  |  |  |  |
| — `uploadDeVerzekeringspolis` | file | Upload de verzekeringspolis |  |  |  |  |  |  | toon als [heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement] = [Ja] |  |

### Stap 15: Bijlagen

> UUID: `7982e106-bce0-49cf-bdaa-ada9eac8b6ba` · slug: `bijlagen` · velden: 3

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `infoTekstVeiligheidsplan` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `veiligheidsplan` | file | Veiligheidsplan <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| `bebordingsEnBewegwijzeringsplan` | file | U heeft aangegeven, dat u gebruik gaat maken van bewegwijzering. Hiervoor dient u een bebordings- en bewegwijzeringsplan toe te voegen, als onderdeel van het verkeersplan, dat als bijlage toegevoegd wordt. <sub>(hidden)</sub> |  | ✓ |  |  |  |  |  |  |
| `ContentOverigeBijlage` | content | _content_ |  | ✓ |  |  |  |  |  |  |
| `bijlagen1` | file | Overige bijlagen |  |  |  |  |  |  |  |  |

### Stap 16: Type aanvraag

> UUID: `119481f2-02f1-4882-974a-6578d3f80d59` · slug: `type-aanvraag` · velden: 0

| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |
|---|---|---|---|---|---|---|---|---|---|---|
| `content35` | content | _content_ |  |  |  |  |  |  |  |  |


## Logica

Alle 144 logic rules uit `/api/v2/forms/7ec8b8ed-0850-4342-a533-9d6c06bfb2c5/logic-rules`. Uit deze rules zijn in totaal 259 acties gegroepeerd per type.


### `fetch-from-service` (7) {#logica-fetch-from-service}

| # | Rule | Trigger stap | Target | Extra | Trigger (JsonLogic) |
|---|---|---|---|---|---|
| 1 | Als bool({{locatieSOpKaart}})en ({{locatieSOpKaart}} is nie… | — | var: `inGemeentenResponse` |  | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;locatieSOpKaart&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;locatieSOpKaart&quot;},&quot;None&quot;]}]}</code> |
| 2 | Als bool({{routesOpKaart}})en ({{routesOpKaart}} is niet ge… | — | var: `inGemeentenResponse` |  | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;routesOpKaart&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;routesOpKaart&quot;},&quot;None&quot;]}]}</code> |
| 3 | Als bool({{addressesToCheck}})en ({{addressesToCheck}} is n… | — | var: `inGemeentenResponse` |  | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;addressesToCheck&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;addressesToCheck&quot;},&quot;None&quot;]}]}</code> |
| 4 | Als bool({{addressToCheck}})en ({{addressToCheck}} is niet … | — | var: `inGemeentenResponse` |  | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;addressToCheck&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;addressToCheck&quot;},&quot;None&quot;]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;waarVindtHetEvenement…</code> |
| 5 | `2057ca5a…` | — | var: `eventloketSession` |  | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;submission_id&quot;},&quot;&quot;]}</code> |
| 6 | Als bool({{evenementInGemeente.brk_identification}}) | — | var: `gemeenteVariabelen` |  | <code>{&quot;!!&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;}]}</code> |
| 7 | Als bool({{EvenementStart}})en bool({{EvenementEind}})en bo… | — | var: `evenementenInDeGemeente` |  | <code>{&quot;and&quot;:[{&quot;!!&quot;:{&quot;var&quot;:&quot;EvenementStart&quot;}},{&quot;!!&quot;:{&quot;var&quot;:&quot;EvenementEind&quot;}},{&quot;!!&quot;:{&quot;var&quot;:&quot;evenementInGemeente.brk_identifica…</code> |

### `property` (98) {#logica-property}

| # | Rule | Trigger stap | Target | Extra | Trigger (JsonLogic) |
|---|---|---|---|---|---|
| 1 | `faa5fae6…` | — | `locatieSOpKaart` (Locatie(s) op kaart) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarVindtHetEvenementPlaats.buiten&quot;},true]}</code> |
| 2 | `5e689e7d…` | — | `adresVanDeGebouwEn` (Adres van de gebouw(en)) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarVindtHetEvenementPlaats.gebouw&quot;},true]}</code> |
| 3 | `9ac0b4c7…` | — | `NotWithin` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;binnenVeiligheidsregio&quot;},false]}</code> |
| 4 | Als (reductie van {{evenementInGemeentenNamen}} (1 + {{accu… | — | `userSelectGemeente` (De ingevoerde locatie(s) of route valt …) | `hidden` = `false` | <code>{&quot;&gt;=&quot;:[{&quot;reduce&quot;:[{&quot;var&quot;:&quot;evenementInGemeentenNamen&quot;},{&quot;+&quot;:[1,{&quot;var&quot;:&quot;accumulator&quot;}]},0]},2]}</code> |
| 5 | `b0b1b8ed…` | — | `content200` (Content) | `hidden` = `false` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;evenementInGemeente&quot;},&quot;&quot;]}</code> |
| 6 | `b0b1b8ed…` | — | `algemeneVragen` (Algemene vragen) | `hidden` = `false` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;evenementInGemeente&quot;},&quot;&quot;]}</code> |
| 7 | `b0b1b8ed…` | — | `contentGemeenteMelding` (Content) | `hidden` = `false` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;evenementInGemeente&quot;},&quot;&quot;]}</code> |
| 8 | `f56a54dd…` | — | `loadUserInformation` (Content) | `hidden` = `true` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession&quot;},&quot;{}&quot;]}</code> |
| 9 | Als bool({{eventloketSession.kvk}}) | — | `organisatieInformatie` (Organisatie informatie) | `hidden` = `false` | <code>{&quot;!!&quot;:[{&quot;var&quot;:&quot;eventloketSession.kvk&quot;}]}</code> |
| 10 | Als bool({{eventloketSession.kvk}}) | — | `adresgegevens` (Adresgegevens) | `hidden` = `true` | <code>{&quot;!!&quot;:[{&quot;var&quot;:&quot;eventloketSession.kvk&quot;}]}</code> |
| 11 | Als {{eventloketSession.kvk}} is gelijk aan '' | — | `organisatieInformatie` (Organisatie informatie) | `hidden` = `true` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;eventloketSession.kvk&quot;},&quot;&quot;]}</code> |
| 12 | Als {{eventloketSession.kvk}} is gelijk aan '' | — | `adresgegevens` (Adresgegevens) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;eventloketSession.kvk&quot;},&quot;&quot;]}</code> |
| 13 | Als {{eventloketSession.kvk}} is gelijk aan '' | — | `waarschuwingGeenKvk` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;eventloketSession.kvk&quot;},&quot;&quot;]}</code> |
| 14 | `b4fefcd8…` | — | `risicoClassificatieContent` (Content) | `hidden` = `false` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;risicoClassificatie&quot;},&quot;&quot;]}</code> |
| 15 | `6b2aeed1…` | — | `contentGemeenteMelding` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;evenement&quot;]}</code> |
| 16 | `6b2aeed1…` | — | `algemeneVragen` (Algemene vragen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;evenement&quot;]}</code> |
| 17 | Als bool({{evenementInGemeente.brk_identification}}) | — | `algemeneVragen` (Algemene vragen) | `hidden` = `false` | <code>{&quot;!!&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;}]}</code> |
| 18 | Als {{meldingsvraag5}} is gelijk aan 'Ja' | — | `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` (Worden er gebiedsontsluitingswegen en/o…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingsvraag5&quot;},&quot;Ja&quot;]}</code> |
| 19 | Als ({{meldingvraag4}} is gelijk aan 'Ja')en (true als ontb… | — | `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` (Worden er gebiedsontsluitingswegen en/o…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag4&quot;},&quot;Ja&quot;]},{&quot;if&quot;:[{&quot;missing&quot;:[&quot;gemeenteVariabelen.report_question_5&quot;]},true,false]}]}</code> |
| 20 | Als ({{meldingvraag4}} is gelijk aan 'Ja')en bool({{gemeent… | — | `meldingvraag5` ({{ gemeenteVariabelen.report_question_5…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag4&quot;},&quot;Ja&quot;]},{&quot;!!&quot;:[{&quot;var&quot;:&quot;gemeenteVariabelen.report_question_5&quot;}]}]}</code> |
| 21 | Als ({{meldingvraag3}} is gelijk aan 'Ja')en (true als ontb… | — | `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` (Worden er gebiedsontsluitingswegen en/o…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag3&quot;},&quot;Ja&quot;]},{&quot;if&quot;:[{&quot;missing&quot;:[&quot;gemeenteVariabelen.report_question_4&quot;]},true,false]}]}</code> |
| 22 | Als ({{meldingvraag3}} is gelijk aan 'Ja')en bool({{gemeent… | — | `meldingvraag4` ({{ gemeenteVariabelen.report_question_4…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag3&quot;},&quot;Ja&quot;]},{&quot;!!&quot;:[{&quot;var&quot;:&quot;gemeenteVariabelen.report_question_4&quot;}]}]}</code> |
| 23 | Als ({{meldingvraag2}} is gelijk aan 'Ja')en (true als ontb… | — | `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` (Worden er gebiedsontsluitingswegen en/o…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag2&quot;},&quot;Ja&quot;]},{&quot;if&quot;:[{&quot;missing&quot;:[&quot;gemeenteVariabelen.report_question_3&quot;]},true,false]}]}</code> |
| 24 | Als ({{meldingvraag2}} is gelijk aan 'Ja')en bool({{gemeent… | — | `meldingvraag3` ({{ gemeenteVariabelen.report_question_3…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag2&quot;},&quot;Ja&quot;]},{&quot;!!&quot;:[{&quot;var&quot;:&quot;gemeenteVariabelen.report_question_3&quot;}]}]}</code> |
| 25 | Als ({{meldingvraag1}} is gelijk aan 'Ja')en bool({{gemeent… | — | `meldingvraag2` ({{ gemeenteVariabelen.report_question_2…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag1&quot;},&quot;Ja&quot;]},{&quot;!!&quot;:[{&quot;var&quot;:&quot;gemeenteVariabelen.report_question_2&quot;}]}]}</code> |
| 26 | Als ({{meldingvraag1}} is gelijk aan 'Ja')en (true als ontb… | — | `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` (Worden er gebiedsontsluitingswegen en/o…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;meldingvraag1&quot;},&quot;Ja&quot;]},{&quot;if&quot;:[{&quot;missing&quot;:[&quot;gemeenteVariabelen.report_question_2&quot;]},true,false]}]}</code> |
| 27 | Als ({{indienErObjectenGeplaatstWordenZijnDezeDanKleiner}} … | — | `meldingvraag1` ({{ gemeenteVariabelen.report_question_1…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;indienErObjectenGeplaatstWordenZijnDezeDanKleiner&quot;},&quot;Ja&quot;]},{&quot;!!&quot;:[{&quot;var&quot;:&quot;gemeenteVariabelen.rep…</code> |
| 28 | Als ({{indienErObjectenGeplaatstWordenZijnDezeDanKleiner}} … | — | `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` (Worden er gebiedsontsluitingswegen en/o…) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;indienErObjectenGeplaatstWordenZijnDezeDanKleiner&quot;},&quot;Ja&quot;]},{&quot;if&quot;:[{&quot;missing&quot;:[&quot;gemeenteVariabele…</code> |
| 29 | Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is… | — | `contentGoNext` (Content) | `hidden` = `false` | <code>{&quot;or&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;isHetAantalAanwezigenBijUwEvenementMinderDanSdf&quot;},&quot;Nee&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;vindenDeActiviteitenVanU…</code> |
| 30 | Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is… | — | `MeldingTekst` (Content) | `hidden` = `true` | <code>{&quot;or&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;isHetAantalAanwezigenBijUwEvenementMinderDanSdf&quot;},&quot;Nee&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;vindenDeActiviteitenVanU…</code> |
| 31 | Als bool({{routeDoorGemeentenNamen}})en ((reductie van {{ev… | — | `contentRouteDoorkuistMeerdereGemeenteInfo` (Content) | `hidden` = `false` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;routeDoorGemeentenNamen&quot;}]},{&quot;&gt;=&quot;:[{&quot;reduce&quot;:[{&quot;var&quot;:&quot;routeDoorGemeentenNamen&quot;},{&quot;+&quot;:[1,{&quot;var&quot;:&quot;…</code> |
| 32 | Als bool({{evenementenInDeGemeente}}) | — | `evenmentenInDeBuurtContent` (Content) | `hidden` = `false` | <code>{&quot;!!&quot;:{&quot;var&quot;:&quot;evenementenInDeGemeente&quot;}}</code> |
| 33 | `7b285070…` | — | `versterkteMuziek` (Versterkte muziek) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A1&quot;},true]}</code> |
| 34 | `7b285070…` | — | `wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning` (Wie maakt de muziek op locatie bij uw e…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A1&quot;},true]}</code> |
| 35 | `7b285070…` | — | `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` (Welke soorten muziek zijn er te horen o…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A1&quot;},true]}</code> |
| 36 | `8e1a11b9…` | — | `bouwsels10MSup2Sup` (Bouwsels &gt; 10m<sup>2</sup> ) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A3&quot;},true]}</code> |
| 37 | `8e1a11b9…` | — | `watVoorBouwselsPlaatsUOpDeLocaties` (Wat voor bouwsels plaats u op de locati…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A3&quot;},true]}</code> |
| 38 | `8aa421de…` | — | `tenten` (Welke tenten plaatst u?) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;watVoorBouwselsPlaatsUOpDeLocaties.A54&quot;},true]}</code> |
| 39 | `0c026fb1…` | — | `podia` (Welke podia plaatst u?) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;watVoorBouwselsPlaatsUOpDeLocaties.A55&quot;},true]}</code> |
| 40 | `bf2ee2f8…` | — | `overkappingen` (Welke overkappingen plaatst u?) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;watVoorBouwselsPlaatsUOpDeLocaties.A56&quot;},true]}</code> |
| 41 | `9b066ee5…` | — | `kansspelen` (Kansspelen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A4&quot;},true]}</code> |
| 42 | `b92d2e5a…` | — | `alcoholischeDranken` (Alcoholische dranken) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A5&quot;},true]}</code> |
| 43 | `e8e0f322…` | — | `etenBereidenOfVerkopen` (Eten bereiden of verkopen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A7&quot;},true]}</code> |
| 44 | `e8e0f322…` | — | `metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX` (Met welke warmtebron wordt het eten ter…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A7&quot;},true]}</code> |
| 45 | `8893efa1…` | — | `belemmeringVanVerkeer` (Belemmering van verkeer) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A8&quot;},true]}</code> |
| 46 | `2e67feb4…` | — | `wegOfVaarwegAfsluiten` (Weg of vaarweg afsluiten) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A10&quot;},true]}</code> |
| 47 | `2a01382c…` | — | `toegangVoorHulpdienstenIsBeperkt` (Toegang voor hulpdiensten is beperkt) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A11&quot;},true]}</code> |
| 48 | `935dc38c…` | — | `wCs` (WC's) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A12&quot;},true]}</code> |
| 49 | `3d9f1e6c…` | — | `douches` (Douche's) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A13&quot;},true]}</code> |
| 50 | `dcd1e4b3…` | — | `ehbo` (EHBO) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A14&quot;},true]}</code> |
| 51 | `79be7168…` | — | `verzorgingVanKinderenJongerDan12Jaar` (Verzorging van kinderen jonger dan 12 j…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A15&quot;},true]}</code> |
| 52 | `b782fae6…` | — | `overnachtingen` (Overnachtingen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A17&quot;},true]}</code> |
| 53 | `21e363f3…` | — | `watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc` (Wat is het maximale aantal personen dat…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A18&quot;},true]}</code> |
| 54 | `21e363f3…` | — | `bouwsels` (Bouwsels) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A18&quot;},true]}</code> |
| 55 | `d8d28395…` | — | `bouwsels` (Bouwsels) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A19&quot;},true]}</code> |
| 56 | `145ceec2…` | — | `bouwsels` (Bouwsels) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A20&quot;},true]}</code> |
| 57 | `889aed1d…` | — | `bouwsels` (Bouwsels) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A21&quot;},true]}</code> |
| 58 | `c1117aff…` | — | `voorwerpen` (Voorwerpen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23&quot;},true]}</code> |
| 59 | `c1117aff…` | — | `verkooppuntenToegangsKaarten` (Verkooppunten toegangs-kaarten) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23&quot;},true]}</code> |
| 60 | `e21a3eae…` | — | `voorwerpen` (Voorwerpen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24&quot;},true]}</code> |
| 61 | `e21a3eae…` | — | `verkooppuntenMuntenEnBonnen` (Verkooppunten munten en bonnen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24&quot;},true]}</code> |
| 62 | `e21a3eae…` | — | `verkooppuntenCashless` (Verkooppunten cashless) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24&quot;},true]}</code> |
| 63 | `acc04d68…` | — | `voorwerpen` (Voorwerpen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25&quot;},true]}</code> |
| 64 | `acc04d68…` | — | `Speeltoestellen` (Speeltoestellen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25&quot;},true]}</code> |
| 65 | `2d10885d…` | — | `brandstofopslag` (Brandstofopslag) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26&quot;},true]}</code> |
| 66 | `2d10885d…` | — | `brandgevaarlijkeStoffen` (Brandgevaarlijke stoffen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26&quot;},true]}</code> |
| 67 | `615d524a…` | — | `voorwerpen` (Voorwerpen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27&quot;},true]}</code> |
| 68 | `615d524a…` | — | `geluidstorens` (Geluidstorens) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27&quot;},true]}</code> |
| 69 | `e9cf76d6…` | — | `voorwerpen` (Voorwerpen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28&quot;},true]}</code> |
| 70 | `e9cf76d6…` | — | `Lichtmasten` (Lichtmasten) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28&quot;},true]}</code> |
| 71 | `6cda93b8…` | — | `voorwerpen` (Voorwerpen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29&quot;},true]}</code> |
| 72 | `6cda93b8…` | — | `marktkramen` (Marktkramen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29&quot;},true]}</code> |
| 73 | `e0d010cd…` | — | `voorwerpen` (Voorwerpen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30&quot;},true]}</code> |
| 74 | `e0d010cd…` | — | `andersGroup` (Anders) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30&quot;},true]}</code> |
| 75 | `0ab47106…` | — | `aanpassenLocatieEnOfVerwijderenStraatmeubilair` (Aanpassen locatie en/of verwijderen str…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32&quot;},true]}</code> |
| 76 | `03a87183…` | — | `extraAfval` (Extra afval) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33&quot;},true]}</code> |
| 77 | `35501489…` | — | `contentBalon` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37&quot;},true]}</code> |
| 78 | `199313af…` | — | `contentLasershow` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38&quot;},true]}</code> |
| 79 | `d138e53e…` | — | `contentZeppelin` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39&quot;},true]}</code> |
| 80 | `72e81725…` | — | `contentDieren` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40&quot;},true]}</code> |
| 81 | `ad564ba5…` | — | `contentVuurwerk` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41&quot;},true]}</code> |
| 82 | `945f1606…` | — | `contentTattoo` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42&quot;},true]}</code> |
| 83 | `ad8eb74d…` | — | `contentVuurkorf` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43&quot;},true]}</code> |
| 84 | `f5363d0b…` | — | `contentWapen` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44&quot;},true]}</code> |
| 85 | Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is… | — | `contentGoNext` (Content) | `hidden` = `true` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 86 | Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is… | — | `MeldingTekst` (Content) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 87 | `0a5531ff…` | — | `welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement` (Welke showeffecten bent u van plan te o…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45&quot;},true]}</code> |
| 88 | `d5681327…` | — | `beveiligers1` (Beveiligers) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A53&quot;},true]}</code> |
| 89 | `565bccec…` | — | `groteVoertuigen` (Voorwerpen op de weg) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48&quot;},true]}</code> |
| 90 | `4a05099f…` | — | `groteVoertuigen` (Voorwerpen op de weg) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49&quot;},true]}</code> |
| 91 | `2bbecc17…` | — | `verkeersregelaars` (Verkeersregelaars) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51&quot;},true]}</code> |
| 92 | `f494443a…` | — | `vervoersmaatregelen` (Vervoersmaatregelen) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52&quot;},true]}</code> |
| 93 | `32f9bd89…` | — | `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1` (Op welke locatie of locaties is er spra…) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;isErSprakeVanOvernachtenDoorPubliekDeelnemers&quot;},&quot;Ja&quot;]}</code> |
| 94 | `7b13e485…` | — | `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2.locatieVanOvernachtenDoorPersoneelOrganisatie1` | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;isErSprakeVanOvernachtenDoorPubliekDeelnemers1&quot;},&quot;Ja&quot;]}</code> |
| 95 | Als ({{risicoClassificatie}} is gelijk aan 'B')of ({{risico… | — | `veiligheidsplan` (Veiligheidsplan) | `hidden` = `false` | <code>{&quot;or&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;risicoClassificatie&quot;},&quot;B&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;risicoClassificatie&quot;},&quot;C&quot;]}]}</code> |
| 96 | Als ({{risicoClassificatie}} is gelijk aan 'B')of ({{risico… | — | `infoTekstVeiligheidsplan` (Content) | `hidden` = `false` | <code>{&quot;or&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;risicoClassificatie&quot;},&quot;B&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;risicoClassificatie&quot;},&quot;C&quot;]}]}</code> |
| 97 | Als ({{risicoClassificatie}} is gelijk aan 'B')of ({{risico… | — | `ContentOverigeBijlage` (Content) | `hidden` = `false` | <code>{&quot;or&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;risicoClassificatie&quot;},&quot;B&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;risicoClassificatie&quot;},&quot;C&quot;]}]}</code> |
| 98 | `457c34ac…` | — | `bebordingsEnBewegwijzeringsplan` (U heeft aangegeven, dat u gebruik gaat …) | `hidden` = `false` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A50&quot;},true]}</code> |

### `set-registration-backend` (45) {#logica-set-registration-backend}

| # | Rule | Trigger stap | Target | Extra | Trigger (JsonLogic) |
|---|---|---|---|---|---|
| 1 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend4` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0917&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 2 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend5` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1729&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 3 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend6` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0917&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 4 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend7` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1729&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 5 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend1` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0917&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 6 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend2` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1729&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 7 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend8` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0888&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 8 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend11` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1954&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 9 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend13` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0899&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 10 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend16` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1903&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 11 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend19` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0928&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 12 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend24` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0882&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 13 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend27` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0938&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 14 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend30` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0965&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 15 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend33` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1883&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 16 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend36` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0971&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 17 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend39` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0981&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 18 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend42` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0994&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 19 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend45` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0986&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswe…</code> |
| 20 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend9` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0888&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 21 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend12` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1954&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 22 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend14` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0899&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 23 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend17` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1903&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 24 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend20` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0928&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 25 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend22` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0882&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 26 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend25` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0938&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 27 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend28` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0965&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 28 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend31` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1883&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 29 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend34` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0971&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 30 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend37` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0981&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 31 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend40` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0994&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 32 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend43` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0986&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebrui…</code> |
| 33 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend3` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0888&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 34 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend10` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1954&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 35 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend15` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0899&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 36 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend18` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1903&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 37 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend21` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0928&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 38 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend23` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0882&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 39 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend26` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0938&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 40 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend29` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0965&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 41 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend32` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM1883&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 42 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend35` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0971&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 43 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend38` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0981&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 44 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend41` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0994&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |
| 45 | Als ({{evenementInGemeente.brk_identification}} is gelijk a… | — | — | `backend44` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;evenementInGemeente.brk_identification&quot;},&quot;GM0986&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;isVergunningaanvraag&quot;},true]}…</code> |

### `step-applicable` (43) {#logica-step-applicable}

| # | Rule | Trigger stap | Target | Extra | Trigger (JsonLogic) |
|---|---|---|---|---|---|
| 1 | `7b285070…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A1&quot;},true]}</code> |
| 2 | `8e1a11b9…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A3&quot;},true]}</code> |
| 3 | `8aa421de…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;watVoorBouwselsPlaatsUOpDeLocaties.A54&quot;},true]}</code> |
| 4 | `0c026fb1…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;watVoorBouwselsPlaatsUOpDeLocaties.A55&quot;},true]}</code> |
| 5 | `bf2ee2f8…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;watVoorBouwselsPlaatsUOpDeLocaties.A56&quot;},true]}</code> |
| 6 | `9b066ee5…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A4&quot;},true]}</code> |
| 7 | `b92d2e5a…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A5&quot;},true]}</code> |
| 8 | `e8e0f322…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A7&quot;},true]}</code> |
| 9 | `8893efa1…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A8&quot;},true]}</code> |
| 10 | `2e67feb4…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A10&quot;},true]}</code> |
| 11 | `2a01382c…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A11&quot;},true]}</code> |
| 12 | `935dc38c…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A12&quot;},true]}</code> |
| 13 | `3d9f1e6c…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A13&quot;},true]}</code> |
| 14 | `dcd1e4b3…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A14&quot;},true]}</code> |
| 15 | `79be7168…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A15&quot;},true]}</code> |
| 16 | `b782fae6…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A17&quot;},true]}</code> |
| 17 | `21e363f3…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A18&quot;},true]}</code> |
| 18 | `d8d28395…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A19&quot;},true]}</code> |
| 19 | `145ceec2…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A20&quot;},true]}</code> |
| 20 | `889aed1d…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A21&quot;},true]}</code> |
| 21 | `c1117aff…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23&quot;},true]}</code> |
| 22 | `e21a3eae…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24&quot;},true]}</code> |
| 23 | `acc04d68…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25&quot;},true]}</code> |
| 24 | `2d10885d…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26&quot;},true]}</code> |
| 25 | `615d524a…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27&quot;},true]}</code> |
| 26 | `e9cf76d6…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28&quot;},true]}</code> |
| 27 | `6cda93b8…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29&quot;},true]}</code> |
| 28 | `e0d010cd…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30&quot;},true]}</code> |
| 29 | `0ab47106…` | — | stap: Vergunningaanvraag: maatregelen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32&quot;},true]}</code> |
| 30 | `03a87183…` | — | stap: Vergunningaanvraag: maatregelen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33&quot;},true]}</code> |
| 31 | `35501489…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37&quot;},true]}</code> |
| 32 | `199313af…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38&quot;},true]}</code> |
| 33 | `d138e53e…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39&quot;},true]}</code> |
| 34 | `72e81725…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40&quot;},true]}</code> |
| 35 | `ad564ba5…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41&quot;},true]}</code> |
| 36 | `945f1606…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42&quot;},true]}</code> |
| 37 | `ad8eb74d…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43&quot;},true]}</code> |
| 38 | `f5363d0b…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44&quot;},true]}</code> |
| 39 | `d5681327…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVoorzieningenZijnAanwezigBijUwEvenement.A53&quot;},true]}</code> |
| 40 | `565bccec…` | — | stap: Vergunningaanvraag: overig |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48&quot;},true]}</code> |
| 41 | `4a05099f…` | — | stap: Vergunningaanvraag: overig |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49&quot;},true]}</code> |
| 42 | `2bbecc17…` | — | stap: Vergunningaanvraag: overig |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51&quot;},true]}</code> |
| 43 | `f494443a…` | — | stap: Vergunningaanvraag: overig |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52&quot;},true]}</code> |

### `step-not-applicable` (21) {#logica-step-not-applicable}

| # | Rule | Trigger stap | Target | Extra | Trigger (JsonLogic) |
|---|---|---|---|---|---|
| 1 | `8f418d89…` | — | stap: Vergunningsaanvraag: soort |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 2 | `8f418d89…` | — | stap: Risicoscan |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 3 | `8f418d89…` | — | stap: Melding |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 4 | `8f418d89…` | — | stap: Vergunningsplichtig scan |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 5 | `8f418d89…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 6 | `8f418d89…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 7 | `8f418d89…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 8 | `8f418d89…` | — | stap: Vergunningaanvraag: maatregelen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 9 | `8f418d89…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 10 | `8f418d89…` | — | stap: Vergunningaanvraag: overig |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 11 | `3a1ac5f3…` | — | stap: Vergunningsaanvraag: soort |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 12 | `3a1ac5f3…` | — | stap: Risicoscan |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 13 | `3a1ac5f3…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 14 | `3a1ac5f3…` | — | stap: Vergunningsaanvraag: voorzieningen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 15 | `3a1ac5f3…` | — | stap: Vergunningsaanvraag: voorwerpen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 16 | `3a1ac5f3…` | — | stap: Vergunningaanvraag: maatregelen |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 17 | `3a1ac5f3…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 18 | `3a1ac5f3…` | — | stap: Vergunningaanvraag: overig |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 19 | Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is… | — | stap: Melding |  | <code>{&quot;or&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;isHetAantalAanwezigenBijUwEvenementMinderDanSdf&quot;},&quot;Nee&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;vindenDeActiviteitenVanU…</code> |
| 20 | `0a5531ff…` | — | stap: Vergunningsaanvraag: extra activiteiten |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45&quot;},true]}</code> |
| 21 | `d566bba6…` | — | stap: Vergunningaanvraag: kenmerken |  | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.&quot;},true]}</code> |

### `variable` (45) {#logica-variable}

| # | Rule | Trigger stap | Target | Extra | Trigger (JsonLogic) |
|---|---|---|---|---|---|
| 1 | Als {{adresVanDeGebouwEn}} is niet gelijk aan None | — | var: `addressesToCheck` | `{"var":"adresVanDeGebouwEn"}` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;adresVanDeGebouwEn&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;adresVanDeGebouwEn&quot;},&quot;None&quot;]}]}</code> |
| 2 | Als ({{adresSenVanHetEvenement}} is niet gelijk aan '{}')en… | — | var: `addressesToCheck` | `{"var":"adresSenVanHetEvenement"}` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;adresSenVanHetEvenement&quot;},&quot;{}&quot;]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;adresSenVanHetEvenement&quot;},&quot;[]&quot;]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;…</code> |
| 3 | Als {{meldingAdres}} is niet gelijk aan "{'postcode': '', '… | — | var: `addressToCheck` | `{"var":"meldingAdres"}` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;meldingAdres&quot;},&quot;{&#039;postcode&#039;: &#039;&#039;, &#039;houseLetter&#039;: &#039;&#039;, &#039;houseNumber&#039;: &#039;&#039;, &#039;houseNumberAddition&#039;: &#039;&#039;…</code> |
| 4 | Als {{waarVindtHetEvenementPlaats}} is gelijk aan 'None' | — | var: `addressToCheck` | `None` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;waarVindtHetEvenementPlaats11&quot;},&quot;{&#039;route&#039;: False, &#039;buiten&#039;: False, &#039;gebouw&#039;: False}&quot;]},{&quot;!=&quot;:[{&quot;…</code> |
| 5 | `6f1046a6…` | — | var: `evenementInGemeentenNamen` | `{"map":[{"var":"inGemeentenResponse.all.items"},{"var":"nam…` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;inGemeentenResponse&quot;},&quot;{}&quot;]}</code> |
| 6 | `6f1046a6…` | — | var: `evenementInGemeentenLijst` | `{"map":[{"var":"inGemeentenResponse.all.items"},{"merge":[{…` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;inGemeentenResponse&quot;},&quot;{}&quot;]}</code> |
| 7 | `6f1046a6…` | — | var: `binnenVeiligheidsregio` | `{"var":"inGemeentenResponse.all.within"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;inGemeentenResponse&quot;},&quot;{}&quot;]}</code> |
| 8 | `6f1046a6…` | — | var: `gemeenten` | `{"var":"inGemeentenResponse.all.object"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;inGemeentenResponse&quot;},&quot;{}&quot;]}</code> |
| 9 | `6f1046a6…` | — | var: `routeDoorGemeentenNamen` | `{"map":[{"var":"inGemeentenResponse.line.items"},{"var":"na…` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;inGemeentenResponse&quot;},&quot;{}&quot;]}</code> |
| 10 | Als (reductie van {{evenementInGemeentenNamen}} (1 + {{accu… | — | var: `evenementInGemeente` | `{"var":"inGemeentenResponse.all.items.0"}` | <code>{&quot;==&quot;:[{&quot;reduce&quot;:[{&quot;var&quot;:&quot;evenementInGemeentenNamen&quot;},{&quot;+&quot;:[1,{&quot;var&quot;:&quot;accumulator&quot;}]},0]},1]}</code> |
| 11 | Als bool({{userSelectGemeente}})en ({{userSelectGemeente}} … | — | var: `evenementInGemeente` | `{"var":{"cat":["gemeenten.",{"var":"userSelectGemeente"}]}}` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;userSelectGemeente&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;userSelectGemeente&quot;},&quot;&quot;]}]}</code> |
| 12 | `f56a54dd…` | — | var: `watIsUwVoornaam` | `{"var":"eventloketSession.user_first_name"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession&quot;},&quot;{}&quot;]}</code> |
| 13 | `f56a54dd…` | — | var: `watIsUwEMailadres` | `{"var":"eventloketSession.user_email"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession&quot;},&quot;{}&quot;]}</code> |
| 14 | `f56a54dd…` | — | var: `watIsUwTelefoonnummer` | `{"var":"eventloketSession.user_phone"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession&quot;},&quot;{}&quot;]}</code> |
| 15 | `f56a54dd…` | — | var: `watIsHetKamerVanKoophandelNummerVanUwOrganisatie` | `{"var":"eventloketSession.kvk"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession&quot;},&quot;{}&quot;]}</code> |
| 16 | `f56a54dd…` | — | var: `eventloketPrefill` | `{"if":[{"!!":[{"var":"eventloketSession.prefill_data"}]},{"…` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession&quot;},&quot;{}&quot;]}</code> |
| 17 | Als bool({{eventloketSession.user_last_name}})en ({{eventlo… | — | var: `watIsUwAchternaam` | `{"var":"eventloketSession.user_last_name"}` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;eventloketSession.user_last_name&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.user_last_name&quot;},&quot;None&quot;]},…</code> |
| 18 | Als bool({{eventloketSession.organisation_email}})en ({{eve… | — | var: `emailadresOrganisatie` | `{"var":"eventloketSession.organisation_email"}` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_email&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_email&quot;},&quot;…</code> |
| 19 | Als bool({{eventloketSession.organisation_phone}})en ({{eve… | — | var: `telefoonnummerOrganisatie` | `{"var":"eventloketSession.organisation_phone"}` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_phone&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_phone&quot;},&quot;…</code> |
| 20 | Als bool({{eventloketSession.organisation_name}})en ({{even… | — | var: `watIsDeNaamVanUwOrganisatie` | `{"var":"eventloketSession.organisation_name"}` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_name&quot;}]},{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_name&quot;},&quot;No…</code> |
| 21 | Als bool({{eventloketSession.organisation_address}})en ({{e… | — | var: `postcode1` | `{"var":"eventloketSession.organisation_address.postcode"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_address&quot;},&quot;&quot;]}</code> |
| 22 | Als bool({{eventloketSession.organisation_address}})en ({{e… | — | var: `huisnummer1` | `{"var":"eventloketSession.organisation_address.houseNumber"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_address&quot;},&quot;&quot;]}</code> |
| 23 | Als bool({{eventloketSession.organisation_address}})en ({{e… | — | var: `huisletter1` | `{"var":"eventloketSession.organisation_address.houseLetter"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_address&quot;},&quot;&quot;]}</code> |
| 24 | Als bool({{eventloketSession.organisation_address}})en ({{e… | — | var: `huisnummertoevoeging1` | `{"var":"eventloketSession.organisation_address.houseNumberA…` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_address&quot;},&quot;&quot;]}</code> |
| 25 | Als bool({{eventloketSession.organisation_address}})en ({{e… | — | var: `straatnaam1` | `{"var":"eventloketSession.organisation_address.streetName"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_address&quot;},&quot;&quot;]}</code> |
| 26 | Als bool({{eventloketSession.organisation_address}})en ({{e… | — | var: `plaatsnaam1` | `{"var":"eventloketSession.organisation_address.city"}` | <code>{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketSession.organisation_address&quot;},&quot;&quot;]}</code> |
| 27 | Als bool({{watIsDeAantrekkingskrachtVanHetEvenement}})en bo… | — | var: `risicoClassificatie` | `{"if":[{"<=":[{"+":[{"var":"watIsDeAantrekkingskrachtVanHet…` | <code>{&quot;and&quot;:[{&quot;!!&quot;:[{&quot;var&quot;:&quot;watIsDeAantrekkingskrachtVanHetEvenement&quot;}]},{&quot;!!&quot;:[{&quot;var&quot;:&quot;watIsDeBelangrijksteLeeftijdscategor…</code> |
| 28 | `3a1ac5f3…` | — | var: `confirmationtext` | `Bedankt voor het invullen van de details voor de melding va…` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer&quot;},&quot;Nee&quot;]}</code> |
| 29 | Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is… | — | var: `isVergunningaanvraag` | `true` | <code>{&quot;or&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;isHetAantalAanwezigenBijUwEvenementMinderDanSdf&quot;},&quot;Nee&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;vindenDeActiviteitenVanU…</code> |
| 30 | Als ({{inGemeentenResponse.line.start_end_equal}} is gelijk… | — | var: `userSelectGemeente` | `` | <code>{&quot;and&quot;:[{&quot;==&quot;:[{&quot;var&quot;:&quot;inGemeentenResponse.line.start_end_equal&quot;},&quot;True&quot;]},{&quot;&gt;=&quot;:[{&quot;reduce&quot;:[{&quot;var&quot;:&quot;evenementInGemeent…</code> |
| 31 | `b92d2e5a…` | — | var: `alcoholvergunning` | `Ja` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;kruisAanWatVanToepassingIsVoorUwEvenementX.A5&quot;},true]}</code> |
| 32 | `4e724924…` | — | var: `confirmationtext` | `` | <code>{&quot;==&quot;:[{&quot;var&quot;:&quot;waarvoorWiltUEventloketGebruiken&quot;},&quot;vooraankondiging&quot;]}</code> |
| 33 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `watIsDeNaamVanHetEvenementVergunning` | `{"var":"eventloketPrefill.naam-van-het-evenement.watIsDeNaa…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 34 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning` | `{"var":"eventloketPrefill.naam-van-het-evenement.geefEenKor…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 35 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `soortEvenement` | `{"var":"eventloketPrefill.naam-van-het-evenement.soortEvene…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 36 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen` | `{"var":"eventloketPrefill.naam-van-het-evenement.gaatHetHie…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 37 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `routesOpKaart` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.route…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 38 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `naamVanDeRoute` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.route…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 39 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `gpxBestandVanDeRoute` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.route…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 40 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `watVoorEvenementGaatPlaatsvindenOpDeRoute1` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.route…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 41 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.route…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 42 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `locatieSOpKaart` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.locat…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 43 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `adresVanDeGebouwEn` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.adres…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 44 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `waarVindtHetEvenementPlaats` | `{"var":"eventloketPrefill.locatie-van-het-evenement-2.waarV…` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |
| 45 | Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{ev… | — | var: `eventloketPrefillLoaded` | `true` | <code>{&quot;and&quot;:[{&quot;!=&quot;:[{&quot;var&quot;:&quot;eventloketPrefill&quot;},&quot;{}&quot;]},{&quot;==&quot;:[{&quot;var&quot;:&quot;watIsDeNaamVanHetEvenementVergunning&quot;},&quot;&quot;]}]}</code> |


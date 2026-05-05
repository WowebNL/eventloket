# Rules-inventaris

Gegenereerd op 2026-04-29 15:04
Totaal rules: 146

Per rule: class-naam, uuid, condition (`applies()`), en de actions die op `apply()` gedaan worden.

---

## `AlsBool`

- **uuid**: `ce043762-6d77-44dc-8e8c-cb605e9acdfa`
- **description**: Als bool({{eventloketSession.kvk}})
- **condition**:
  ```php
  JsTruthy::of($s->get('eventloketSession.kvk'))
  ```
- **actions**:
  - `$s->setFieldHidden('organisatieInformatie', false);`
  - `$s->setFieldHidden('adresgegevens', true);`

---

## `AlsBool00876823`

- **uuid**: `00876823-b3f3-44f6-a177-d355c84c0b12`
- **description**: Als bool({{evenementenInDeGemeente}})
- **condition**:
  ```php
  JsTruthy::of($s->get('evenementenInDeGemeente'))
  ```
- **actions**:
  - `$s->setFieldHidden('evenmentenInDeBuurtContent', false);`

---

## `AlsBool47620576`

- **uuid**: `47620576-e866-4f7e-98fb-cad476f4ac3b`
- **description**: Als bool({{evenementInGemeente.brk_identification}})
- **condition**:
  ```php
  JsTruthy::of($s->get('evenementInGemeente.brk_identification'))
  ```
- **actions**:
  - `app(ServiceFetcher::class)->fetch('gemeenteVariabelen', $s);`
  - `$s->setFieldHidden('algemeneVragen', false);`

---

## `AlsBoolEn`

- **uuid**: `2f7b0e09-2730-4aab-89e5-8b0182ee68bb`
- **description**: Als bool({{eventloketSession.organisation_address}})en ({{eventloketSession.organisation_address}} …
- **condition**:
  ```php
  $s->get('eventloketSession.organisation_address') !== ''
  ```
- **actions**:
  - `$s->setVariable('postcode1', $s->get('eventloketSession.organisation_address.postcode'));`
  - `$s->setVariable('huisnummer1', $s->get('eventloketSession.organisation_address.houseNumber'));`
  - `$s->setVariable('huisletter1', $s->get('eventloketSession.organisation_address.houseLetter'));`
  - `$s->setVariable('huisnummertoevoeging1', $s->get('eventloketSession.organisation_address.houseNumberAddition'));`
  - `$s->setVariable('straatnaam1', $s->get('eventloketSession.organisation_address.streetName'));`
  - `$s->setVariable('plaatsnaam1', $s->get('eventloketSession.organisation_address.city'));`

---

## `AlsBoolEnBoolEnBoolEvenementingemeenteBrkIdentificat`

- **uuid**: `3fa0fbf5-9ee1-4c2a-9074-9993e208b010`
- **description**: Als bool({{EvenementStart}})en bool({{EvenementEind}})en bool({{evenementInGemeente.brk_identificat…
- **condition**:
  ```php
  JsTruthy::of($s->get('EvenementStart')) && JsTruthy::of($s->get('EvenementEind')) && JsTruthy::of($s->get('evenementInGemeente.brk_identification'))
  ```
- **actions**:
  - `app(ServiceFetcher::class)->fetch('evenementenInDeGemeente', $s);`

---

## `AlsBoolEnBoolWatisdebelangrijksteleeftijdscatego`

- **uuid**: `55ce8acd-f972-417d-8920-64c8b0744e14`
- **description**: Als bool({{watIsDeAantrekkingskrachtVanHetEvenement}})en bool({{watIsDeBelangrijksteLeeftijdscatego…
- **condition**:
  ```php
  JsTruthy::of($s->get('watIsDeAantrekkingskrachtVanHetEvenement')) && JsTruthy::of($s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) && JsTruthy::of($s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) && JsTruthy::of($s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) && JsTruthy::of($s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) && JsTruthy::of($s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) && JsTruthy::of($s->get('isErSprakeVanOvernachten')) && JsTruthy::of($s->get('isErGebruikVanAlcoholEnDrugs')) && JsTruthy::of($s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) && JsTruthy::of($s->get('inWelkSeizoenVindtHetEvenementPlaats')) && JsTruthy::of($s->get('inWelkeLocatieVindtHetEvenementPlaats')) && JsTruthy::of($s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) && JsTruthy::of($s->get('watIsDeTijdsduurVanHetEvenement')) && JsTruthy::of($s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))
  ```
- **actions**:
  - `$s->setVariable('risicoClassificatie', (((((float) $s->get('watIsDeAantrekkingskrachtVanHetEvenement')) + ((float) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) + ((float) $s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) + ((float) $s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) + ((float) $s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanOvernachten')) + ((float) $s->get('isErGebruikVanAlcoholEnDrugs')) + ((float) $s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) + ((float) $s->get('inWelkSeizoenVindtHetEvenementPlaats')) + ((float) $s->get('inWelkeLocatieVindtHetEvenementPlaats')) + ((float) $s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) + ((float) $s->get('watIsDeTijdsduurVanHetEvenement')) + ((float) $s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))) <= 6) ? 'A' : (((((float) $s->get('watIsDeAantrekkingskrachtVanHetEvenement')) + ((float) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) + ((float) $s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) + ((float) $s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) + ((float) $s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanOvernachten')) + ((float) $s->get('isErGebruikVanAlcoholEnDrugs')) + ((float) $s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) + ((float) $s->get('inWelkSeizoenVindtHetEvenementPlaats')) + ((float) $s->get('inWelkeLocatieVindtHetEvenementPlaats')) + ((float) $s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) + ((float) $s->get('watIsDeTijdsduurVanHetEvenement')) + ((float) $s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))) <= 9) ? 'B' : 'C')));`

---

## `AlsBoolEnIsN`

- **uuid**: `5905fff0-6bec-4c28-9064-55772fb25859`
- **description**: Als bool({{eventloketSession.organisation_email}})en ({{eventloketSession.organisation_email}} is n…
- **condition**:
  ```php
  JsTruthy::of($s->get('eventloketSession.organisation_email')) && ($s->get('eventloketSession.organisation_email') !== 'None') && ($s->get('eventloketSession.organisation_address') !== 'NULL')
  ```
- **actions**:
  - `$s->setVariable('emailadresOrganisatie', $s->get('eventloketSession.organisation_email'));`

---

## `AlsBoolEnIsN0f284f5c`

- **uuid**: `0f284f5c-ffb1-4512-981d-5954e56c8b9e`
- **description**: Als bool({{eventloketSession.organisation_phone}})en ({{eventloketSession.organisation_phone}} is n…
- **condition**:
  ```php
  JsTruthy::of($s->get('eventloketSession.organisation_phone')) && ($s->get('eventloketSession.organisation_phone') !== 'None') && ($s->get('eventloketSession.organisation_phone') !== 'NULL')
  ```
- **actions**:
  - `$s->setVariable('telefoonnummerOrganisatie', $s->get('eventloketSession.organisation_phone'));`

---

## `AlsBoolEnIsNie`

- **uuid**: `583c258c-fcbd-4f1c-b127-58d04b6ed050`
- **description**: Als bool({{eventloketSession.organisation_name}})en ({{eventloketSession.organisation_name}} is nie…
- **condition**:
  ```php
  JsTruthy::of($s->get('eventloketSession.organisation_name')) && ($s->get('eventloketSession.organisation_name') !== 'None') && ($s->get('eventloketSession.organisation_name') !== 'NULL')
  ```
- **actions**:
  - `$s->setVariable('watIsDeNaamVanUwOrganisatie', $s->get('eventloketSession.organisation_name'));`

---

## `AlsBoolEnIsNietGeli`

- **uuid**: `8124340f-cce5-47da-8691-91ad37fd6af0`
- **description**: Als bool({{eventloketSession.user_last_name}})en ({{eventloketSession.user_last_name}} is niet geli…
- **condition**:
  ```php
  JsTruthy::of($s->get('eventloketSession.user_last_name')) && ($s->get('eventloketSession.user_last_name') !== 'None') && ($s->get('eventloketSession.user_last_name') !== 'NULL')
  ```
- **actions**:
  - `$s->setVariable('watIsUwAchternaam', $s->get('eventloketSession.user_last_name'));`

---

## `AlsBoolEnIsNietGelijkAanNone`

- **uuid**: `a7211d0c-f8aa-479b-b9b9-8474dbe70b75`
- **description**: Als bool({{locatieSOpKaart}})en ({{locatieSOpKaart}} is niet gelijk aan 'None')
- **condition**:
  ```php
  JsTruthy::of($s->get('locatieSOpKaart')) && ($s->get('locatieSOpKaart') !== 'None')
  ```
- **actions**:
  - `app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);`

---

## `AlsBoolEnIsNietGelijkAanNone580a3ef8`

- **uuid**: `580a3ef8-9fa6-4f5a-8714-502d86d6cb55`
- **description**: Als bool({{userSelectGemeente}})en ({{userSelectGemeente}} is niet gelijk aan 'None')
- **condition**:
  ```php
  JsTruthy::of($s->get('userSelectGemeente')) && ($s->get('userSelectGemeente') !== '')
  ```
- **actions**:
  - `$s->setVariable('evenementInGemeente', $s->get((string) (((string) 'gemeenten.').((string) $s->get('userSelectGemeente')))));`

---

## `AlsBoolEnIsNietGelijkAanNone599a6cfd`

- **uuid**: `599a6cfd-7ea4-4c68-b011-c1f590286daf`
- **description**: Als bool({{routesOpKaart}})en ({{routesOpKaart}} is niet gelijk aan 'None')
- **condition**:
  ```php
  JsTruthy::of($s->get('routesOpKaart')) && ($s->get('routesOpKaart') !== 'None')
  ```
- **actions**:
  - `app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);`

---

## `AlsBoolEnIsNietGelijkAanNone99b8a502`

- **uuid**: `99b8a502-9ef8-4be2-8142-2a25c69ba905`
- **description**: Als bool({{addressToCheck}})en ({{addressToCheck}} is niet gelijk aan 'None')
- **condition**:
  ```php
  JsTruthy::of($s->get('addressToCheck')) && ($s->get('addressToCheck') !== 'None') && ($s->get('waarVindtHetEvenementPlaats11') !== '{\'gebouw\': False, \'buiten\': False, \'route\': True}')
  ```
- **actions**:
  - `app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);`

---

## `AlsBoolEnIsNietGelijkAanNoneBd328413`

- **uuid**: `bd328413-a566-42a6-87ba-ec575ea94347`
- **description**: Als bool({{addressesToCheck}})en ({{addressesToCheck}} is niet gelijk aan 'None')
- **condition**:
  ```php
  JsTruthy::of($s->get('addressesToCheck')) && ($s->get('addressesToCheck') !== 'None')
  ```
- **actions**:
  - `app(ServiceFetcher::class)->fetch('inGemeentenResponse', $s);`

---

## `AlsBoolEnReductieVan1Accumul`

- **uuid**: `3247522b-8603-4c7c-ae8d-b92a75fb35d6`
- **description**: Als bool({{routeDoorGemeentenNamen}})en ((reductie van {{evenementInGemeentenNamen}} (1 + {{accumul…
- **condition**:
  ```php
  JsTruthy::of($s->get('routeDoorGemeentenNamen')) && ((is_array($s->get('routeDoorGemeentenNamen')) ? count($s->get('routeDoorGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11'))
  ```
- **actions**:
  - `$s->setFieldHidden('contentRouteDoorkuistMeerdereGemeenteInfo', false);`

---

## `AlsIsGelijkAan`

- **uuid**: `1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a`
- **description**: Als {{eventloketSession.kvk}} is gelijk aan ''
- **condition**:
  ```php
  $s->get('eventloketSession.kvk') === ''
  ```
- **actions**:
  - `$s->setFieldHidden('organisatieInformatie', true);`
  - `$s->setFieldHidden('adresgegevens', false);`
  - `$s->setFieldHidden('waarschuwingGeenKvk', false);`

---

## `AlsIsGelijkAanBOfIsGelijkAanC`

- **uuid**: `f1202010-b8b7-45c0-8f31-756190313451`
- **description**: Als ({{risicoClassificatie}} is gelijk aan 'B')of ({{risicoClassificatie}} is gelijk aan 'C')
- **condition**:
  ```php
  ($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')
  ```
- **actions**:
  - `$s->setFieldHidden('veiligheidsplan', false);`
  - `$s->setFieldHidden('infoTekstVeiligheidsplan', false);`
  - `$s->setFieldHidden('ContentOverigeBijlage', false);`

---

## `AlsIsGelijkAanGm0882En`

- **uuid**: `4d1f5398-9485-4a7d-8aac-66b3ad453184`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0882')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0882') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend23');`

---

## `AlsIsGelijkAanGm0882EnWaarvoorwiltueventloke`

- **uuid**: `37d78597-b439-44be-8e85-49a9a6bdb047`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0882')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0882') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend22');`

---

## `AlsIsGelijkAanGm0882EnWordenergebiedsontslui`

- **uuid**: `d315e853-7945-434c-8124-99fdf289a207`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0882')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0882') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend24');`

---

## `AlsIsGelijkAanGm0888En`

- **uuid**: `6142841d-ea97-4e22-8ffa-90c0b9b18cdb`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0888')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0888') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend3');`

---

## `AlsIsGelijkAanGm0888EnWaarvoorwiltueventloke`

- **uuid**: `32426416-9787-42d5-8eb2-4634a214e0ea`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0888')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0888') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend9');`

---

## `AlsIsGelijkAanGm0888EnWordenergebiedsontslui`

- **uuid**: `fdbb12fb-57d0-40a6-b262-6e06dc6f903c`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0888')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0888') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend8');`

---

## `AlsIsGelijkAanGm0899En`

- **uuid**: `d88a64d4-9e6e-43d8-86f4-305d774ffd07`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0899')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0899') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend15');`

---

## `AlsIsGelijkAanGm0899EnWaarvoorwiltueventloke`

- **uuid**: `63e3968d-ef2b-44c8-9410-748098a86e7e`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0899')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0899') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend14');`

---

## `AlsIsGelijkAanGm0899EnWordenergebiedsontslui`

- **uuid**: `61dba87a-5c99-457c-87a7-934dd43bc8b9`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0899')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0899') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend13');`

---

## `AlsIsGelijkAanGm0917EnWaarvoorwiltueventloke`

- **uuid**: `0e056f5a-9303-4322-9a75-300187ab62c7`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0917')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0917') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend4');`

---

## `AlsIsGelijkAanGm0917EnWaarvoorwiltueventloke396c72d1`

- **uuid**: `396c72d1-d354-4508-b370-5096131b4f1c`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0917')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0917') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend1');`

---

## `AlsIsGelijkAanGm0917EnWordenergebiedsontslui`

- **uuid**: `479ac7b1-e701-4bd1-97ce-dbe2e8aea919`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0917')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0917') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend6');`

---

## `AlsIsGelijkAanGm0928En`

- **uuid**: `df33eaaf-ae05-4e09-902b-a572603a746c`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0928')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0928') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend21');`

---

## `AlsIsGelijkAanGm0928EnWaarvoorwiltueventloke`

- **uuid**: `32203ae3-1b0d-4293-85e3-69ec4fdbc712`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0928')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0928') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend20');`

---

## `AlsIsGelijkAanGm0928EnWordenergebiedsontslui`

- **uuid**: `335471e6-3df8-41ea-955b-dc35b69e947d`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0928')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0928') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend19');`

---

## `AlsIsGelijkAanGm0938En`

- **uuid**: `4787de8e-7323-46ae-abf8-ff3f365ab262`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0938')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0938') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend26');`

---

## `AlsIsGelijkAanGm0938EnWaarvoorwiltueventloke`

- **uuid**: `49389fc0-4da8-4449-acaf-674a2e2fb0e2`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0938')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0938') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend25');`

---

## `AlsIsGelijkAanGm0938EnWordenergebiedsontslui`

- **uuid**: `553d3dce-5469-46d9-a804-5a168e60d7bd`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0938')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0938') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend27');`

---

## `AlsIsGelijkAanGm0965En`

- **uuid**: `789875f2-c16c-4136-ab2c-02a990496a67`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0965')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0965') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend29');`

---

## `AlsIsGelijkAanGm0965EnWaarvoorwiltueventloke`

- **uuid**: `1ee86630-18dc-48dc-aef6-eb1756a94647`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0965')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0965') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend28');`

---

## `AlsIsGelijkAanGm0965EnWordenergebiedsontslui`

- **uuid**: `759dab8e-8717-4920-b027-79d1ca081ccf`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0965')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0965') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend30');`

---

## `AlsIsGelijkAanGm0971En`

- **uuid**: `58f8be55-1cee-404b-b5f2-db14c22127ab`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0971')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0971') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend35');`

---

## `AlsIsGelijkAanGm0971EnWaarvoorwiltueventloke`

- **uuid**: `d442d0f7-b6d4-488a-9a4a-37e814e93769`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0971')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0971') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend34');`

---

## `AlsIsGelijkAanGm0971EnWordenergebiedsontslui`

- **uuid**: `6dcf345f-b3c5-4ec0-88af-969d124df26e`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0971')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0971') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend36');`

---

## `AlsIsGelijkAanGm0981En`

- **uuid**: `32ef4927-9551-46b6-9eee-a8f0650c97b9`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0981')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0981') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend38');`

---

## `AlsIsGelijkAanGm0981EnWaarvoorwiltueventloke`

- **uuid**: `c24eee33-106a-4de4-b411-aee078eed5fe`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0981')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0981') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend37');`

---

## `AlsIsGelijkAanGm0981EnWordenergebiedsontslui`

- **uuid**: `6b7d79c6-f543-40f0-9f76-eefd940f9794`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0981')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0981') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend39');`

---

## `AlsIsGelijkAanGm0986En`

- **uuid**: `5bbbf229-62eb-4e9a-89fc-b67ab1610385`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0986')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0986') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend44');`

---

## `AlsIsGelijkAanGm0986EnWaarvoorwiltueventloke`

- **uuid**: `78ef160d-4aa3-4fe9-941c-848501f3bc60`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0986')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0986') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend43');`

---

## `AlsIsGelijkAanGm0986EnWordenergebiedsontslui`

- **uuid**: `e0974420-8ac8-4c94-9f69-6b5c1f326d33`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0986')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0986') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend45');`

---

## `AlsIsGelijkAanGm0994En`

- **uuid**: `6a6642d7-c35c-4bd8-b32e-5e05ac85da71`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0994')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0994') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend41');`

---

## `AlsIsGelijkAanGm0994EnWaarvoorwiltueventloke`

- **uuid**: `5080bdcd-0bea-4552-8075-8605bd8cc453`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0994')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0994') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend40');`

---

## `AlsIsGelijkAanGm0994EnWordenergebiedsontslui`

- **uuid**: `75d3584d-6746-4946-9ad3-6672c8dd11b6`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM0994')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM0994') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend42');`

---

## `AlsIsGelijkAanGm1729En`

- **uuid**: `6c661796-23ba-44ad-8ad0-1bcf4cabe17d`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1729')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1729') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend2');`

---

## `AlsIsGelijkAanGm1729EnWaarvoorwiltueventloke`

- **uuid**: `4fb78bad-07fb-473d-bc18-bee1bad8503f`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1729')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1729') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend5');`

---

## `AlsIsGelijkAanGm1729EnWordenergebiedsontslui`

- **uuid**: `e0746436-6115-4ad9-9c76-aa7adcaba646`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1729')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1729') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend7');`

---

## `AlsIsGelijkAanGm1883En`

- **uuid**: `c737ca21-e621-449a-97e1-0c45d5cbbffe`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1883')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1883') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend32');`

---

## `AlsIsGelijkAanGm1883EnWaarvoorwiltueventloke`

- **uuid**: `1e756a8a-4a68-4bd0-bfc0-59f2283bffde`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1883')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1883') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend31');`

---

## `AlsIsGelijkAanGm1883EnWordenergebiedsontslui`

- **uuid**: `e86be725-f23e-42b7-b3a4-98683c59d03d`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1883')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1883') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend33');`

---

## `AlsIsGelijkAanGm1903En`

- **uuid**: `cf1c0126-2fcf-4944-a72b-d9b2eab070cf`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1903')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1903') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend18');`

---

## `AlsIsGelijkAanGm1903EnWaarvoorwiltueventloke`

- **uuid**: `a46b5971-673b-415a-a7b4-fa4dde2e0c4f`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1903')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1903') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend17');`

---

## `AlsIsGelijkAanGm1903EnWordenergebiedsontslui`

- **uuid**: `c214f586-8c85-4acc-b31a-955bbcbfb029`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1903')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1903') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend16');`

---

## `AlsIsGelijkAanGm1954En`

- **uuid**: `91870e4d-e065-462b-8c3d-686409084cf8`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1954')en ({{isVergunningaanvraag}}…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1954') && ($s->get('isVergunningaanvraag') === true)
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend10');`

---

## `AlsIsGelijkAanGm1954EnWaarvoorwiltueventloke`

- **uuid**: `669dd594-c81b-41d7-8c12-fcc7234588c0`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1954')en ({{waarvoorWiltUEventloke…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1954') && ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend12');`

---

## `AlsIsGelijkAanGm1954EnWordenergebiedsontslui`

- **uuid**: `1ceeb0f8-0e80-42b6-82a5-1c8001312d64`
- **description**: Als ({{evenementInGemeente.brk_identification}} is gelijk aan 'GM1954')en ({{wordenErGebiedsontslui…
- **condition**:
  ```php
  ($s->get('evenementInGemeente.brk_identification') === 'GM1954') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee')
  ```
- **actions**:
  - `$s->setSystem('registration_backend', 'backend11');`

---

## `AlsIsGelijkAanJa`

- **uuid**: `a757ea1f-24ee-40b8-a839-4e9997a33959`
- **description**: Als {{meldingsvraag5}} is gelijk aan 'Ja'
- **condition**:
  ```php
  $s->get('meldingsvraag5') === 'Ja'
  ```
- **actions**:
  - `$s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);`

---

## `AlsIsGelijkAanJaEnBool`

- **uuid**: `63781392-9b7b-45e3-823d-5b039784882e`
- **description**: Als ({{meldingvraag4}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_5}})
- **condition**:
  ```php
  ($s->get('meldingvraag4') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_5'))
  ```
- **actions**:
  - `$s->setFieldHidden('meldingvraag5', false);`

---

## `AlsIsGelijkAanJaEnBool172fe1ad`

- **uuid**: `172fe1ad-207f-429a-ace2-d2d07b4ea92a`
- **description**: Als ({{meldingvraag1}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_2}})
- **condition**:
  ```php
  ($s->get('meldingvraag1') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_2'))
  ```
- **actions**:
  - `$s->setFieldHidden('meldingvraag2', false);`

---

## `AlsIsGelijkAanJaEnBool4e042329`

- **uuid**: `4e042329-a992-45ae-998b-521ea980c55a`
- **description**: Als ({{meldingvraag2}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_3}})
- **condition**:
  ```php
  ($s->get('meldingvraag2') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_3'))
  ```
- **actions**:
  - `$s->setFieldHidden('meldingvraag3', false);`

---

## `AlsIsGelijkAanJaEnBoolC7431a0c`

- **uuid**: `c7431a0c-f315-4768-8372-8703629228b8`
- **description**: Als ({{meldingvraag3}} is gelijk aan 'Ja')en bool({{gemeenteVariabelen.report_question_4}})
- **condition**:
  ```php
  ($s->get('meldingvraag3') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_4'))
  ```
- **actions**:
  - `$s->setFieldHidden('meldingvraag4', false);`

---

## `AlsIsGelijkAanJaEnBoolGemeentevar`

- **uuid**: `454a40c6-43c8-42cd-9d2f-6d2ace4fec53`
- **description**: Als ({{indienErObjectenGeplaatstWordenZijnDezeDanKleiner}} is gelijk aan 'Ja')en bool({{gemeenteVar…
- **condition**:
  ```php
  ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_1'))
  ```
- **actions**:
  - `$s->setFieldHidden('meldingvraag1', false);`

---

## `AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti`

- **uuid**: `ceac4877-e22f-4d59-afac-cf2f29cb93d9`
- **description**: Als ({{meldingvraag4}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
- **condition**:
  ```php
  ($s->get('meldingvraag4') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_5',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
  ```
- **actions**:
  - `$s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);`

---

## `AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti981e2b88`

- **uuid**: `981e2b88-49b3-4096-ae1d-07a4500e7ccc`
- **description**: Als ({{meldingvraag2}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
- **condition**:
  ```php
  ($s->get('meldingvraag2') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_3',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
  ```
- **actions**:
  - `$s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);`

---

## `AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiB741d925`

- **uuid**: `b741d925-75bf-4b8f-a0aa-47cdb0e5341d`
- **description**: Als ({{meldingvraag3}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
- **condition**:
  ```php
  ($s->get('meldingvraag3') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_4',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
  ```
- **actions**:
  - `$s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);`

---

## `AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiEa096e0f`

- **uuid**: `ea096e0f-e793-4df7-8292-df26ad862dc9`
- **description**: Als ({{meldingvraag1}} is gelijk aan 'Ja')en (true als ontbrekend('gemeenteVariabelen.report_questi…
- **condition**:
  ```php
  ($s->get('meldingvraag1') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_2',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
  ```
- **actions**:
  - `$s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);`

---

## `AlsIsGelijkAanJaOfVindendeactivitei`

- **uuid**: `8e022b2c-1742-4ff7-a5a0-50d02d05833e`
- **description**: Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is gelijk aan 'Ja')of ({{vindenDeActivitei…
- **condition**:
  ```php
  $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
  ```
- **actions**:
  - `$s->setFieldHidden('contentGoNext', true);`
  - `$s->setFieldHidden('MeldingTekst', false);`

---

## `AlsIsGelijkAanNeeEnTrueAlsOntbrek`

- **uuid**: `a64ed84a-d0a3-4560-b782-a24be41b3e4a`
- **description**: Als ({{indienErObjectenGeplaatstWordenZijnDezeDanKleiner}} is gelijk aan 'Nee')en (true als ontbrek…
- **condition**:
  ```php
  ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && ((array_values(array_filter([
            0 => 'gemeenteVariabelen.report_question_1',
        ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)
  ```
- **actions**:
  - `$s->setFieldHidden('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer', false);`

---

## `AlsIsGelijkAanNeeOfVindendeactivite`

- **uuid**: `87482f34-1e1f-4853-b2da-312c9b2cebf0`
- **description**: Als ({{isHetAantalAanwezigenBijUwEvenementMinderDanSdf}} is gelijk aan 'Nee')of ({{vindenDeActivite…
- **condition**:
  ```php
  ($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')
  ```
- **actions**:
  - `$s->setFieldHidden('contentGoNext', false);`
  - `$s->setStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47', false);`
  - `$s->setVariable('isVergunningaanvraag', true);`
  - `$s->setFieldHidden('MeldingTekst', true);`

---

## `AlsIsGelijkAanNone`

- **uuid**: `d21486ca-b7b2-4a4c-9963-1f24ca7eeea4`
- **description**: Als {{waarVindtHetEvenementPlaats}} is gelijk aan 'None'
- **condition**:
  ```php
  ($s->get('waarVindtHetEvenementPlaats11') === '{\'route\': False, \'buiten\': False, \'gebouw\': False}') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') !== 'Nee')
  ```
- **actions**:
  - `$s->setVariable('addressToCheck', 'None');`

---

## `AlsIsGelijkAanTrueEnReductieVanEvenemen`

- **uuid**: `be547255-4a1b-4f37-96e8-919d5351e7a5`
- **description**: Als ({{inGemeentenResponse.line.start_end_equal}} is gelijk aan 'True')en ((reductie van {{evenemen…
- **condition**:
  ```php
  ($s->get('inGemeentenResponse.line.start_end_equal') === 'True') && ((is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11'))
  ```
- **actions**:
  - `$s->setVariable('userSelectGemeente', '');`

---

## `AlsIsNietGelijkAanEnIsGelijkAanFa`

- **uuid**: `29ff6bf6-c3fb-42e6-b523-d5478d203b85`
- **description**: Als ({{eventloketPrefill}} is niet gelijk aan '{}')en ({{eventloketPrefillLoaded}} is gelijk aan fa…
- **condition**:
  ```php
  ($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')
  ```
- **actions**:
  - `$s->setVariable('watIsDeNaamVanHetEvenementVergunning', $s->get('eventloketPrefill.naam-van-het-evenement.watIsDeNaamVanHetEvenementVergunning'));`
  - `$s->setVariable('geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning', $s->get('eventloketPrefill.naam-van-het-evenement.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning'));`
  - `$s->setVariable('soortEvenement', $s->get('eventloketPrefill.naam-van-het-evenement.soortEvenement'));`
  - `$s->setVariable('gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen', $s->get('eventloketPrefill.naam-van-het-evenement.gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen'));`
  - `$s->setVariable('routesOpKaart', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.routesOpKaart'));`
  - `$s->setVariable('naamVanDeRoute', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.naamVanDeRoute'));`
  - `$s->setVariable('gpxBestandVanDeRoute', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.gpxBestandVanDeRoute'));`
  - `$s->setVariable('watVoorEvenementGaatPlaatsvindenOpDeRoute1', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.watVoorEvenementGaatPlaatsvindenOpDeRoute1'));`
  - `$s->setVariable('komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan', $s->get('eventloketPrefill.locatie-van-het-evenement-2.route.komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan'));`
  - `$s->setVariable('locatieSOpKaart', $s->get('eventloketPrefill.locatie-van-het-evenement-2.locatieSOpKaart'));`
  - `$s->setVariable('adresVanDeGebouwEn', $s->get('eventloketPrefill.locatie-van-het-evenement-2.adresVanDeGebouwEn'));`
  - `$s->setVariable('waarVindtHetEvenementPlaats', $s->get('eventloketPrefill.locatie-van-het-evenement-2.waarVindtHetEvenementPlaats'));`
  - `$s->setVariable('eventloketPrefillLoaded', true);`

---

## `AlsIsNietGelijkAanEnIsNietGe`

- **uuid**: `bb866a33-aa14-437f-a7bf-3303ad75a5d9`
- **description**: Als ({{adresSenVanHetEvenement}} is niet gelijk aan '{}')en ({{adresSenVanHetEvenement}} is niet ge…
- **condition**:
  ```php
  ($s->get('adresSenVanHetEvenement') !== '{}') && ($s->get('adresSenVanHetEvenement') !== '[]') && ($s->get('adresSenVanHetEvenement') !== 'None')
  ```
- **actions**:
  - `$s->setVariable('addressesToCheck', $s->get('adresSenVanHetEvenement'));`

---

## `AlsIsNietGelijkAanNone`

- **uuid**: `974b5945-c4cf-4d1a-a5f8-34985255406d`
- **description**: Als {{adresVanDeGebouwEn}} is niet gelijk aan None
- **condition**:
  ```php
  JsTruthy::of($s->get('adresVanDeGebouwEn')) && ($s->get('adresVanDeGebouwEn') !== 'None')
  ```
- **actions**:
  - `$s->setVariable('addressesToCheck', $s->get('adresVanDeGebouwEn'));`

---

## `AlsIsNietGelijkAanPostcodeHouseletterHousenumberHo`

- **uuid**: `91bf1bff-b1af-4da7-b310-e56854d48f61`
- **description**: Als {{meldingAdres}} is niet gelijk aan "{'postcode': '', 'houseLetter': '', 'houseNumber': '', 'ho…
- **condition**:
  ```php
  ($s->get('meldingAdres') !== '{\'postcode\': \'\', \'houseLetter\': \'\', \'houseNumber\': \'\', \'houseNumberAddition\': \'\'}') && ($s->get('meldingAdres') !== '{\'postcode\': \'\', \'houseLetter\': \'\', \'houseNumber\': \'\', \'houseNumberAddition\': \'\', \'city\': \'\', \'streetName\': \'\', \'secretStreetCity\': \'\'}') && ($s->get('meldingAdres') !== 'None') && ($s->get('waarVindtHetEvenementPlaats11') === '{\'route\': False, \'buiten\': False, \'gebouw\': False}')
  ```
- **actions**:
  - `$s->setVariable('addressToCheck', $s->get('meldingAdres'));`

---

## `AlsReductieVan1BeginnendBij0IsGelijkA`

- **uuid**: `a6fcec40-74f6-4741-862f-22ebf2de7142`
- **description**: Als (reductie van {{evenementInGemeentenNamen}} (1 + {{accumulator}}, beginnend bij 0)) is gelijk a…
- **condition**:
  ```php
  (is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) === 1
  ```
- **actions**:
  - `$s->setVariable('evenementInGemeente', $s->get('inGemeentenResponse.all.items.0'));`

---

## `AlsReductieVan1BeginnendBij0IsGroterD`

- **uuid**: `e3992429-730a-4ed9-af3c-62ad897933fe`
- **description**: Als (reductie van {{evenementInGemeentenNamen}} (1 + {{accumulator}}, beginnend bij 0)) is groter d…
- **condition**:
  ```php
  (is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2
  ```
- **actions**:
  - `$s->setFieldHidden('userSelectGemeente', false);`

---

## `MeldingSchakeltVergunningstappenUit`

- **uuid**: `melding-schakelt-vergunningstappen-uit`
- **condition**:
  ```php
  if ($state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') {
            return false;
  ```
- **actions**:
  - `foreach (self::VERGUNNING_STAP_UUIDS as $uuid) {`
  - `$state->setStepApplicable($uuid, false);`
  - `}`

---

## `Rule03a87183`

- **uuid**: `03a87183-48c3-4e5b-b6ec-287c4f3daf97`
- **condition**:
  ```php
  $s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true
  ```
- **actions**:
  - `$s->setFieldHidden('extraAfval', false);`
  - `$s->setStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99', true);`

---

## `Rule0a5531ff`

- **uuid**: `0a5531ff-5f95-42e3-b911-53affa4c88d6`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true
  ```
- **actions**:
  - `$s->setFieldHidden('welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', false);`

---

## `Rule0ab47106`

- **uuid**: `0ab47106-f334-492a-b676-a98ca88c2a64`
- **condition**:
  ```php
  $s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true
  ```
- **actions**:
  - `$s->setFieldHidden('aanpassenLocatieEnOfVerwijderenStraatmeubilair', false);`
  - `$s->setStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99', true);`

---

## `Rule0c026fb1`

- **uuid**: `0c026fb1-e43c-4fa7-a33f-615efd68d3bb`
- **condition**:
  ```php
  $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true
  ```
- **actions**:
  - `$s->setFieldHidden('podia', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`

---

## `Rule145ceec2`

- **uuid**: `145ceec2-91c7-4e67-8195-2444d734ddfc`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true
  ```
- **actions**:
  - `$s->setFieldHidden('bouwsels', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `Rule199313af`

- **uuid**: `199313af-cc35-4409-8398-294c658ae03f`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentLasershow', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `Rule2057ca5a`

- **uuid**: `2057ca5a-9750-474e-961a-ebb7aff07f57`
- **condition**:
  ```php
  $s->get('submission_id') !== ''
  ```
- **actions**:
  - `app(ServiceFetcher::class)->fetch('eventloketSession', $s);`

---

## `Rule21e363f3`

- **uuid**: `21e363f3-9ca8-42d4-b52e-bddfab43ddd6`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true
  ```
- **actions**:
  - `$s->setFieldHidden('watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc', false);`
  - `$s->setFieldHidden('bouwsels', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `Rule2a01382c`

- **uuid**: `2a01382c-1fd2-4aac-82c7-c5fc22a5a4bf`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true
  ```
- **actions**:
  - `$s->setFieldHidden('toegangVoorHulpdienstenIsBeperkt', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`

---

## `Rule2bbecc17`

- **uuid**: `2bbecc17-8f88-474d-9399-acb4cd509541`
- **condition**:
  ```php
  $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true
  ```
- **actions**:
  - `$s->setFieldHidden('verkeersregelaars', false);`
  - `$s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);`

---

## `Rule2d10885d`

- **uuid**: `2d10885d-3e3a-4df1-a17b-d979668d2581`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true
  ```
- **actions**:
  - `$s->setFieldHidden('brandstofopslag', false);`
  - `$s->setFieldHidden('brandgevaarlijkeStoffen', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`

---

## `Rule2e67feb4`

- **uuid**: `2e67feb4-08d6-46f8-ab24-3ee91a387cb7`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true
  ```
- **actions**:
  - `$s->setFieldHidden('wegOfVaarwegAfsluiten', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`

---

## `Rule32f9bd89`

- **uuid**: `32f9bd89-ac3d-4fa4-b89f-1b9a48b13efb`
- **condition**:
  ```php
  $s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers') === 'Ja'
  ```
- **actions**:
  - `$s->setFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1', false);`

---

## `Rule35501489`

- **uuid**: `35501489-2e07-4d62-b5df-da1b4795d5e7`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentBalon', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `Rule3a1ac5f3`

- **uuid**: `3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6`
- **condition**:
  ```php
  $s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'
  ```
- **actions**:
  - `$s->setStepApplicable('ae44ab5b-c068-4ceb-b121-6e6907f78ef9', false);`
  - `$s->setStepApplicable('c75cc256-6729-4684-9f9b-ede6265b3e72', false);`
  - `$s->setVariable('confirmationtext', 'Bedankt voor het invullen van de details voor de melding van uw evenement.');`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', false);`
  - `$s->setStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', false);`
  - `$s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', false);`

---

## `Rule3d9f1e6c`

- **uuid**: `3d9f1e6c-85a9-449d-91c5-ebef408dd538`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true
  ```
- **actions**:
  - `$s->setFieldHidden('douches', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `Rule457c34ac`

- **uuid**: `457c34ac-d4ac-4037-83b2-eaea58d24ccb`
- **condition**:
  ```php
  $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A50') === true
  ```
- **actions**:
  - `$s->setFieldHidden('bebordingsEnBewegwijzeringsplan', false);`

---

## `Rule4a05099f`

- **uuid**: `4a05099f-5ded-49b6-a0a6-fc1544b55c25`
- **condition**:
  ```php
  $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true
  ```
- **actions**:
  - `$s->setFieldHidden('groteVoertuigen', false);`
  - `$s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);`

---

## `Rule4e724924`

- **uuid**: `4e724924-c5a7-451b-a2c5-282cf9a245ed`
- **condition**:
  ```php
  $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
  ```
- **actions**:
  - `$s->setVariable('confirmationtext', '');`

---

## `Rule565bccec`

- **uuid**: `565bccec-1a7b-40f3-975f-0edf8402b461`
- **condition**:
  ```php
  $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true
  ```
- **actions**:
  - `$s->setFieldHidden('groteVoertuigen', false);`
  - `$s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);`

---

## `Rule5e689e7d`

- **uuid**: `5e689e7d-0a06-4301-ada5-d36132b285cb`
- **condition**:
  ```php
  $s->get('waarVindtHetEvenementPlaats.gebouw') === true
  ```
- **actions**:
  - `$s->setFieldHidden('adresVanDeGebouwEn', false);`

---

## `Rule615d524a`

- **uuid**: `615d524a-498d-4e30-8279-2dc41ec7d6ac`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true
  ```
- **actions**:
  - `$s->setFieldHidden('voorwerpen', false);`
  - `$s->setFieldHidden('geluidstorens', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`

---

## `Rule6b2aeed1`

- **uuid**: `6b2aeed1-8226-4a7c-9801-bbe61d576dca`
- **condition**:
  ```php
  $s->get('waarvoorWiltUEventloketGebruiken') === 'evenement'
  ```
- **actions**:
  - `$s->setFieldHidden('contentGemeenteMelding', false);`
  - `$s->setFieldHidden('algemeneVragen', false);`

---

## `Rule6cda93b8`

- **uuid**: `6cda93b8-4b85-4e9b-bc0e-89c45329ddac`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true
  ```
- **actions**:
  - `$s->setFieldHidden('voorwerpen', false);`
  - `$s->setFieldHidden('marktkramen', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`

---

## `Rule6f1046a6`

- **uuid**: `6f1046a6-7866-491b-b87d-65bd67aade6f`
- **condition**:
  ```php
  $s->get('inGemeentenResponse') !== '{}'
  ```
- **actions**:
  - `$s->setVariable('evenementInGemeentenNamen', ((function () use ($s) {`
  - `$__items = $s->get('inGemeentenResponse.all.items');`
  - `if (! is_array($__items)) {`
  - `return [];`
  - `} $__result = [];`
  - `foreach ($__items as $__item) {`
  - `$__result[] = (function ($s) {`
  - `return $s->get('name');`
  - `})(MapContext::from($s, $__item));`
  - `}`
  - `return $__result;`
  - `})()));`
  - `$s->setVariable('evenementInGemeentenLijst', ((function () use ($s) {`
  - `$__items = $s->get('inGemeentenResponse.all.items');`
  - `if (! is_array($__items)) {`
  - `return [];`
  - `} $__result = [];`
  - `foreach ($__items as $__item) {`
  - `$__result[] = (function ($s) {`
  - `return array_merge(((array) ($s->get('brk_identification') ?? [])), ((array) ($s->get('name') ?? [])));`
  - `})(MapContext::from($s, $__item));`
  - `}`
  - `return $__result;`
  - `})()));`
  - `$s->setVariable('binnenVeiligheidsregio', $s->get('inGemeentenResponse.all.within'));`
  - `$s->setVariable('gemeenten', $s->get('inGemeentenResponse.all.object'));`
  - `$s->setVariable('routeDoorGemeentenNamen', ((function () use ($s) {`
  - `$__items = $s->get('inGemeentenResponse.line.items');`
  - `if (! is_array($__items)) {`
  - `return [];`
  - `} $__result = [];`
  - `foreach ($__items as $__item) {`
  - `$__result[] = (function ($s) {`
  - `return $s->get('name');`
  - `})(MapContext::from($s, $__item));`
  - `}`
  - `return $__result;`
  - `})()));`

---

## `Rule72e81725`

- **uuid**: `72e81725-03fc-4c6e-8218-603bc7f07ef8`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentDieren', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `Rule79be7168`

- **uuid**: `79be7168-edd7-48db-af66-525fa6a5815a`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true
  ```
- **actions**:
  - `$s->setFieldHidden('verzorgingVanKinderenJongerDan12Jaar', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `Rule7b13e485`

- **uuid**: `7b13e485-188e-4b37-8a31-c310ed165109`
- **condition**:
  ```php
  $s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers1') === 'Ja'
  ```
- **actions**:
  - `$s->setFieldHidden('opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2.locatieVanOvernachtenDoorPersoneelOrganisatie1', false);`

---

## `Rule7b285070`

- **uuid**: `7b285070-2c40-4d8f-9b18-d20dd745bbd4`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true
  ```
- **actions**:
  - `$s->setFieldHidden('versterkteMuziek', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`
  - `$s->setFieldHidden('wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning', false);`
  - `$s->setFieldHidden('welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX', false);`

---

## `Rule8893efa1`

- **uuid**: `8893efa1-663a-4ad6-9184-46ae7cb2ebf7`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true
  ```
- **actions**:
  - `$s->setFieldHidden('belemmeringVanVerkeer', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`

---

## `Rule889aed1d`

- **uuid**: `889aed1d-d7bc-4a93-b5b6-00c01f812724`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true
  ```
- **actions**:
  - `$s->setFieldHidden('bouwsels', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `Rule8aa421de`

- **uuid**: `8aa421de-5ac8-4451-a646-ef94e82e0d00`
- **condition**:
  ```php
  $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true
  ```
- **actions**:
  - `$s->setFieldHidden('tenten', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`

---

## `Rule8e1a11b9`

- **uuid**: `8e1a11b9-59f2-407b-8fb1-0fbee9712c08`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true
  ```
- **actions**:
  - `$s->setFieldHidden('bouwsels10MSup2Sup', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`
  - `$s->setFieldHidden('watVoorBouwselsPlaatsUOpDeLocaties', false);`

---

## `Rule8f418d89`

- **uuid**: `8f418d89-637a-45a6-8092-c2242201a009`
- **condition**:
  ```php
  $s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'
  ```
- **actions**:
  - `$s->setStepApplicable('ae44ab5b-c068-4ceb-b121-6e6907f78ef9', false);`
  - `$s->setStepApplicable('c75cc256-6729-4684-9f9b-ede6265b3e72', false);`
  - `$s->setStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47', false);`
  - `$s->setStepApplicable('d87c01ce-8387-43b0-a8c8-e6cf5abb6da1', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', false);`
  - `$s->setStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', false);`
  - `$s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', false);`

---

## `Rule935dc38c`

- **uuid**: `935dc38c-383c-4c3d-abe1-a741bfba4a32`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true
  ```
- **actions**:
  - `$s->setFieldHidden('wCs', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `Rule945f1606`

- **uuid**: `945f1606-e086-4999-983b-8b9c83dab421`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentTattoo', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `Rule9ac0b4c7`

- **uuid**: `9ac0b4c7-ea17-48c4-9bd0-b760ed0570ba`
- **condition**:
  ```php
  $s->get('binnenVeiligheidsregio') === false
  ```
- **actions**:
  - `$s->setFieldHidden('NotWithin', false);`

---

## `Rule9b066ee5`

- **uuid**: `9b066ee5-3e95-45a1-9864-c444f1508300`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true
  ```
- **actions**:
  - `$s->setFieldHidden('kansspelen', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`

---

## `RuleAcc04d68`

- **uuid**: `acc04d68-e446-4c59-b8a5-d40ef246ee74`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true
  ```
- **actions**:
  - `$s->setFieldHidden('voorwerpen', false);`
  - `$s->setFieldHidden('Speeltoestellen', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`

---

## `RuleAd564ba5`

- **uuid**: `ad564ba5-b144-438a-9449-dda1800ecbd3`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentVuurwerk', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `RuleAd8eb74d`

- **uuid**: `ad8eb74d-08d5-4813-9c00-a914f6618300`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentVuurkorf', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `RuleB0b1b8ed`

- **uuid**: `b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08`
- **condition**:
  ```php
  $s->get('evenementInGemeente') !== ''
  ```
- **actions**:
  - `$s->setFieldHidden('content200', false);`
  - `$s->setFieldHidden('algemeneVragen', false);`
  - `$s->setFieldHidden('contentGemeenteMelding', false);`

---

## `RuleB4fefcd8`

- **uuid**: `b4fefcd8-faae-4139-93e1-e4b8108d6376`
- **condition**:
  ```php
  $s->get('risicoClassificatie') !== ''
  ```
- **actions**:
  - `$s->setFieldHidden('risicoClassificatieContent', false);`

---

## `RuleB782fae6`

- **uuid**: `b782fae6-2270-4f90-930a-af073989e0f9`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true
  ```
- **actions**:
  - `$s->setFieldHidden('overnachtingen', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `RuleB92d2e5a`

- **uuid**: `b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true
  ```
- **actions**:
  - `$s->setFieldHidden('alcoholischeDranken', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`
  - `$s->setVariable('alcoholvergunning', 'Ja');`

---

## `RuleBf2ee2f8`

- **uuid**: `bf2ee2f8-9ea4-49a2-b1ab-2295c3b7052b`
- **condition**:
  ```php
  $s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true
  ```
- **actions**:
  - `$s->setFieldHidden('overkappingen', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`

---

## `RuleC1117aff`

- **uuid**: `c1117aff-045d-4bf9-80c3-0ad446282328`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true
  ```
- **actions**:
  - `$s->setFieldHidden('voorwerpen', false);`
  - `$s->setFieldHidden('verkooppuntenToegangsKaarten', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`

---

## `RuleD138e53e`

- **uuid**: `d138e53e-eb22-4c93-9ec5-daba437208c3`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentZeppelin', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `RuleD566bba6`

- **uuid**: `d566bba6-452c-480c-9a12-fcee922d0002`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.') === true
  ```
- **actions**:
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', false);`

---

## `RuleD5681327`

- **uuid**: `d5681327-869c-4a3a-be73-88c973668af1`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true
  ```
- **actions**:
  - `$s->setFieldHidden('beveiligers1', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `RuleD8d28395`

- **uuid**: `d8d28395-9e5e-4570-a4f3-129ad988ae8f`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true
  ```
- **actions**:
  - `$s->setFieldHidden('bouwsels', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `RuleDcd1e4b3`

- **uuid**: `dcd1e4b3-7706-48df-a08f-3ad84369d580`
- **condition**:
  ```php
  $s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true
  ```
- **actions**:
  - `$s->setFieldHidden('ehbo', false);`
  - `$s->setStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84', true);`

---

## `RuleE0d010cd`

- **uuid**: `e0d010cd-193d-4a26-8a01-89b185d5709e`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true
  ```
- **actions**:
  - `$s->setFieldHidden('voorwerpen', false);`
  - `$s->setFieldHidden('andersGroup', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`

---

## `RuleE21a3eae`

- **uuid**: `e21a3eae-6e0f-479e-84e7-122e3401aac4`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true
  ```
- **actions**:
  - `$s->setFieldHidden('voorwerpen', false);`
  - `$s->setFieldHidden('verkooppuntenMuntenEnBonnen', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`
  - `$s->setFieldHidden('verkooppuntenCashless', false);`

---

## `RuleE8e0f322`

- **uuid**: `e8e0f322-bd43-4e79-9a3b-be489189920b`
- **condition**:
  ```php
  $s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true
  ```
- **actions**:
  - `$s->setFieldHidden('etenBereidenOfVerkopen', false);`
  - `$s->setStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe', true);`
  - `$s->setFieldHidden('metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX', false);`

---

## `RuleE9cf76d6`

- **uuid**: `e9cf76d6-9eca-4d23-b546-f6f4a9c4d471`
- **condition**:
  ```php
  $s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true
  ```
- **actions**:
  - `$s->setFieldHidden('voorwerpen', false);`
  - `$s->setFieldHidden('Lichtmasten', false);`
  - `$s->setStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455', true);`

---

## `RuleF494443a`

- **uuid**: `f494443a-ef9a-4cb0-a0ff-9f422e2c3d2c`
- **condition**:
  ```php
  $s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true
  ```
- **actions**:
  - `$s->setFieldHidden('vervoersmaatregelen', false);`
  - `$s->setStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e', true);`

---

## `RuleF5363d0b`

- **uuid**: `f5363d0b-b344-4350-86c4-063b2ea97516`
- **condition**:
  ```php
  $s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true
  ```
- **actions**:
  - `$s->setFieldHidden('contentWapen', false);`
  - `$s->setStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa', true);`

---

## `RuleF56a54dd`

- **uuid**: `f56a54dd-4af9-452f-8bbd-cee5fba3c79b`
- **condition**:
  ```php
  $s->get('eventloketSession') !== '{}'
  ```
- **actions**:
  - `$s->setVariable('watIsUwVoornaam', $s->get('eventloketSession.user_first_name'));`
  - `$s->setVariable('watIsUwEMailadres', $s->get('eventloketSession.user_email'));`
  - `$s->setVariable('watIsUwTelefoonnummer', $s->get('eventloketSession.user_phone'));`
  - `$s->setVariable('watIsHetKamerVanKoophandelNummerVanUwOrganisatie', $s->get('eventloketSession.kvk'));`
  - `$s->setFieldHidden('loadUserInformation', true);`
  - `$s->setVariable('eventloketPrefill', (JsTruthy::of($s->get('eventloketSession.prefill_data')) ? $s->get('eventloketSession.prefill_data') : '{}'));`

---

## `RuleFaa5fae6`

- **uuid**: `faa5fae6-c19f-4a8b-b138-a7b98fa44b95`
- **condition**:
  ```php
  $s->get('waarVindtHetEvenementPlaats.buiten') === true
  ```
- **actions**:
  - `$s->setFieldHidden('locatieSOpKaart', false);`

---

## `VergunningSchakeltMeldingUit`

- **uuid**: `vergunning-schakelt-melding-uit`
- **condition**:
  ```php
  if ($state->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging') {
            return false;
  ```
- **actions**:
  - `$state->setStepApplicable(MeldingStep::UUID, false);`

---

# Samenvattingen per doel

## Variabelen die door rules gezet worden

Per variabele: welke rules schrijven 'm + onder welke voorwaarde. Wordt input voor `FormDerivedState`-methodes.

### `addressToCheck` (2 writers)

- **AlsIsGelijkAanNone** (`d21486ca-b7b2-4a4c-9963-1f24ca7eeea4`)
  - `if (($s->get('waarVindtHetEvenementPlaats11') === '{\'route\': False, \'buiten\': False, \'gebouw\': False}') && ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') !== 'Nee')): set to:`
  - `'None'`
- **AlsIsNietGelijkAanPostcodeHouseletterHousenumberHo** (`91bf1bff-b1af-4da7-b310-e56854d48f61`)
  - `if (($s->get('meldingAdres') !== '{\'postcode\': \'\', \'houseLetter\': \'\', \'houseNumber\': \'\', \'houseNumberAddition\': \'\'}') && ($s->get('meldingAdres') !== '{\'postcode\': \'\', \'houseLetter\': \'\', \'houseNumber\': \'\', \'houseNumberAddition\': \'\', \'city\': \'\', \'streetName\': \'\', \'secretStreetCity\': \'\'}') && ($s->get('meldingAdres') !== 'None') && ($s->get('waarVindtHetEvenementPlaats11') === '{\'route\': False, \'buiten\': False, \'gebouw\': False}')): set to:`
  - `$s->get('meldingAdres')`

### `addressesToCheck` (2 writers)

- **AlsIsNietGelijkAanEnIsNietGe** (`bb866a33-aa14-437f-a7bf-3303ad75a5d9`)
  - `if (($s->get('adresSenVanHetEvenement') !== '{}') && ($s->get('adresSenVanHetEvenement') !== '[]') && ($s->get('adresSenVanHetEvenement') !== 'None')): set to:`
  - `$s->get('adresSenVanHetEvenement')`
- **AlsIsNietGelijkAanNone** (`974b5945-c4cf-4d1a-a5f8-34985255406d`)
  - `if (JsTruthy::of($s->get('adresVanDeGebouwEn')) && ($s->get('adresVanDeGebouwEn') !== 'None')): set to:`
  - `$s->get('adresVanDeGebouwEn')`

### `adresVanDeGebouwEn` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.adresVanDeGebouwEn')`

### `alcoholvergunning` (1 writer)

- **RuleB92d2e5a** (`b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7`)
  - `if ($s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true): set to:`
  - `'Ja'`

### `binnenVeiligheidsregio` (1 writer)

- **Rule6f1046a6** (`6f1046a6-7866-491b-b87d-65bd67aade6f`)
  - `if ($s->get('inGemeentenResponse') !== '{}'): set to:`
  - `$s->get('inGemeentenResponse.all.within')`

### `confirmationtext` (2 writers)

- **Rule3a1ac5f3** (`3a1ac5f3-eac2-40d6-8d46-9dad8622b3c6`)
  - `if ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'): set to:`
  - `'Bedankt voor het invullen van de details voor de melding van uw evenement.'`
- **Rule4e724924** (`4e724924-c5a7-451b-a2c5-282cf9a245ed`)
  - `if ($s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'): set to:`
  - `''`

### `emailadresOrganisatie` (1 writer)

- **AlsBoolEnIsN** (`5905fff0-6bec-4c28-9064-55772fb25859`)
  - `if (JsTruthy::of($s->get('eventloketSession.organisation_email')) && ($s->get('eventloketSession.organisation_email') !== 'None') && ($s->get('eventloketSession.organisation_address') !== 'NULL')): set to:`
  - `$s->get('eventloketSession.organisation_email')`

### `evenementInGemeente` (2 writers)

- **AlsBoolEnIsNietGelijkAanNone580a3ef8** (`580a3ef8-9fa6-4f5a-8714-502d86d6cb55`)
  - `if (JsTruthy::of($s->get('userSelectGemeente')) && ($s->get('userSelectGemeente') !== '')): set to:`
  - `$s->get((string) (((string) 'gemeenten.').((string) $s->get('userSelectGemeente'))))`
- **AlsReductieVan1BeginnendBij0IsGelijkA** (`a6fcec40-74f6-4741-862f-22ebf2de7142`)
  - `if ((is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) === 1): set to:`
  - `$s->get('inGemeentenResponse.all.items.0')`

### `eventloketPrefill` (1 writer)

- **RuleF56a54dd** (`f56a54dd-4af9-452f-8bbd-cee5fba3c79b`)
  - `if ($s->get('eventloketSession') !== '{}'): set to:`
  - `(JsTruthy::of($s->get('eventloketSession.prefill_data')) ? $s->get('eventloketSession.prefill_data') : '{}')`

### `eventloketPrefillLoaded` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `true`

### `gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.naam-van-het-evenement.gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen')`

### `geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.naam-van-het-evenement.geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning')`

### `gemeenten` (1 writer)

- **Rule6f1046a6** (`6f1046a6-7866-491b-b87d-65bd67aade6f`)
  - `if ($s->get('inGemeentenResponse') !== '{}'): set to:`
  - `$s->get('inGemeentenResponse.all.object')`

### `gpxBestandVanDeRoute` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.route.gpxBestandVanDeRoute')`

### `huisletter1` (1 writer)

- **AlsBoolEn** (`2f7b0e09-2730-4aab-89e5-8b0182ee68bb`)
  - `if ($s->get('eventloketSession.organisation_address') !== ''): set to:`
  - `$s->get('eventloketSession.organisation_address.houseLetter')`

### `huisnummer1` (1 writer)

- **AlsBoolEn** (`2f7b0e09-2730-4aab-89e5-8b0182ee68bb`)
  - `if ($s->get('eventloketSession.organisation_address') !== ''): set to:`
  - `$s->get('eventloketSession.organisation_address.houseNumber')`

### `huisnummertoevoeging1` (1 writer)

- **AlsBoolEn** (`2f7b0e09-2730-4aab-89e5-8b0182ee68bb`)
  - `if ($s->get('eventloketSession.organisation_address') !== ''): set to:`
  - `$s->get('eventloketSession.organisation_address.houseNumberAddition')`

### `isVergunningaanvraag` (1 writer)

- **AlsIsGelijkAanNeeOfVindendeactivite** (`87482f34-1e1f-4853-b2da-312c9b2cebf0`)
  - `if (($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')): set to:`
  - `true`

### `komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.route.komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan')`

### `locatieSOpKaart` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.locatieSOpKaart')`

### `naamVanDeRoute` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.route.naamVanDeRoute')`

### `plaatsnaam1` (1 writer)

- **AlsBoolEn** (`2f7b0e09-2730-4aab-89e5-8b0182ee68bb`)
  - `if ($s->get('eventloketSession.organisation_address') !== ''): set to:`
  - `$s->get('eventloketSession.organisation_address.city')`

### `postcode1` (1 writer)

- **AlsBoolEn** (`2f7b0e09-2730-4aab-89e5-8b0182ee68bb`)
  - `if ($s->get('eventloketSession.organisation_address') !== ''): set to:`
  - `$s->get('eventloketSession.organisation_address.postcode')`

### `risicoClassificatie` (1 writer)

- **AlsBoolEnBoolWatisdebelangrijksteleeftijdscatego** (`55ce8acd-f972-417d-8920-64c8b0744e14`)
  - `if (JsTruthy::of($s->get('watIsDeAantrekkingskrachtVanHetEvenement')) && JsTruthy::of($s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) && JsTruthy::of($s->get('isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid')) && JsTruthy::of($s->get('isEenDeelVanDeDoelgroepVerminderdZelfredzaam')) && JsTruthy::of($s->get('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten')) && JsTruthy::of($s->get('watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep')) && JsTruthy::of($s->get('isErSprakeVanOvernachten')) && JsTruthy::of($s->get('isErGebruikVanAlcoholEnDrugs')) && JsTruthy::of($s->get('watIsHetAantalGelijktijdigAanwezigPersonen')) && JsTruthy::of($s->get('inWelkSeizoenVindtHetEvenementPlaats')) && JsTruthy::of($s->get('inWelkeLocatieVindtHetEvenementPlaats')) && JsTruthy::of($s->get('opWelkSoortOndergrondVindtHetEvenementPlaats')) && JsTruthy::of($s->get('watIsDeTijdsduurVanHetEvenement')) && JsTruthy::of($s->get('welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing'))): set to:`
  - `(((((float) $s->get('watIsDeAantrekkingskrachtVanHetEvenement')) + ((float) $s->get('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep')) + ((float) $s->get('isErSprakeVanZanwezigheidVanPolitiekeAa...`

### `routesOpKaart` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.route.routesOpKaart')`

### `soortEvenement` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.naam-van-het-evenement.soortEvenement')`

### `straatnaam1` (1 writer)

- **AlsBoolEn** (`2f7b0e09-2730-4aab-89e5-8b0182ee68bb`)
  - `if ($s->get('eventloketSession.organisation_address') !== ''): set to:`
  - `$s->get('eventloketSession.organisation_address.streetName')`

### `telefoonnummerOrganisatie` (1 writer)

- **AlsBoolEnIsN0f284f5c** (`0f284f5c-ffb1-4512-981d-5954e56c8b9e`)
  - `if (JsTruthy::of($s->get('eventloketSession.organisation_phone')) && ($s->get('eventloketSession.organisation_phone') !== 'None') && ($s->get('eventloketSession.organisation_phone') !== 'NULL')): set to:`
  - `$s->get('eventloketSession.organisation_phone')`

### `userSelectGemeente` (1 writer)

- **AlsIsGelijkAanTrueEnReductieVanEvenemen** (`be547255-4a1b-4f37-96e8-919d5351e7a5`)
  - `if (($s->get('inGemeentenResponse.line.start_end_equal') === 'True') && ((is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11'))): set to:`
  - `''`

### `waarVindtHetEvenementPlaats` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.waarVindtHetEvenementPlaats')`

### `watIsDeNaamVanHetEvenementVergunning` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.naam-van-het-evenement.watIsDeNaamVanHetEvenementVergunning')`

### `watIsDeNaamVanUwOrganisatie` (1 writer)

- **AlsBoolEnIsNie** (`583c258c-fcbd-4f1c-b127-58d04b6ed050`)
  - `if (JsTruthy::of($s->get('eventloketSession.organisation_name')) && ($s->get('eventloketSession.organisation_name') !== 'None') && ($s->get('eventloketSession.organisation_name') !== 'NULL')): set to:`
  - `$s->get('eventloketSession.organisation_name')`

### `watIsHetKamerVanKoophandelNummerVanUwOrganisatie` (1 writer)

- **RuleF56a54dd** (`f56a54dd-4af9-452f-8bbd-cee5fba3c79b`)
  - `if ($s->get('eventloketSession') !== '{}'): set to:`
  - `$s->get('eventloketSession.kvk')`

### `watIsUwAchternaam` (1 writer)

- **AlsBoolEnIsNietGeli** (`8124340f-cce5-47da-8691-91ad37fd6af0`)
  - `if (JsTruthy::of($s->get('eventloketSession.user_last_name')) && ($s->get('eventloketSession.user_last_name') !== 'None') && ($s->get('eventloketSession.user_last_name') !== 'NULL')): set to:`
  - `$s->get('eventloketSession.user_last_name')`

### `watIsUwEMailadres` (1 writer)

- **RuleF56a54dd** (`f56a54dd-4af9-452f-8bbd-cee5fba3c79b`)
  - `if ($s->get('eventloketSession') !== '{}'): set to:`
  - `$s->get('eventloketSession.user_email')`

### `watIsUwTelefoonnummer` (1 writer)

- **RuleF56a54dd** (`f56a54dd-4af9-452f-8bbd-cee5fba3c79b`)
  - `if ($s->get('eventloketSession') !== '{}'): set to:`
  - `$s->get('eventloketSession.user_phone')`

### `watIsUwVoornaam` (1 writer)

- **RuleF56a54dd** (`f56a54dd-4af9-452f-8bbd-cee5fba3c79b`)
  - `if ($s->get('eventloketSession') !== '{}'): set to:`
  - `$s->get('eventloketSession.user_first_name')`

### `watVoorEvenementGaatPlaatsvindenOpDeRoute1` (1 writer)

- **AlsIsNietGelijkAanEnIsGelijkAanFa** (`29ff6bf6-c3fb-42e6-b523-d5478d203b85`)
  - `if (($s->get('eventloketPrefill') !== '{}') && ($s->get('watIsDeNaamVanHetEvenementVergunning') === '')): set to:`
  - `$s->get('eventloketPrefill.locatie-van-het-evenement-2.route.watVoorEvenementGaatPlaatsvindenOpDeRoute1')`

## Velden die door rules verborgen worden

### `ContentOverigeBijlage` (1 rule)

- **AlsIsGelijkAanBOfIsGelijkAanC** (`f1202010-b8b7-45c0-8f31-756190313451`) → show
  - condition: `($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')`

### `Lichtmasten` (1 rule)

- **RuleE9cf76d6** (`e9cf76d6-9eca-4d23-b546-f6f4a9c4d471`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true`

### `MeldingTekst` (2 rules)

- **AlsIsGelijkAanJaOfVindendeactivitei** (`8e022b2c-1742-4ff7-a5a0-50d02d05833e`) → show
  - condition: `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- **AlsIsGelijkAanNeeOfVindendeactivite** (`87482f34-1e1f-4853-b2da-312c9b2cebf0`) → hide
  - condition: `($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')`

### `NotWithin` (1 rule)

- **Rule9ac0b4c7** (`9ac0b4c7-ea17-48c4-9bd0-b760ed0570ba`) → show
  - condition: `$s->get('binnenVeiligheidsregio') === false`

### `Speeltoestellen` (1 rule)

- **RuleAcc04d68** (`acc04d68-e446-4c59-b8a5-d40ef246ee74`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true`

### `aanpassenLocatieEnOfVerwijderenStraatmeubilair` (1 rule)

- **Rule0ab47106** (`0ab47106-f334-492a-b676-a98ca88c2a64`) → show
  - condition: `$s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true`

### `adresVanDeGebouwEn` (1 rule)

- **Rule5e689e7d** (`5e689e7d-0a06-4301-ada5-d36132b285cb`) → show
  - condition: `$s->get('waarVindtHetEvenementPlaats.gebouw') === true`

### `adresgegevens` (2 rules)

- **AlsBool** (`ce043762-6d77-44dc-8e8c-cb605e9acdfa`) → hide
  - condition: `JsTruthy::of($s->get('eventloketSession.kvk'))`
- **AlsIsGelijkAan** (`1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a`) → show
  - condition: `$s->get('eventloketSession.kvk') === ''`

### `alcoholischeDranken` (1 rule)

- **RuleB92d2e5a** (`b92d2e5a-3ff7-4b1d-91d4-f1ca827247f7`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true`

### `algemeneVragen` (3 rules)

- **AlsBool47620576** (`47620576-e866-4f7e-98fb-cad476f4ac3b`) → show
  - condition: `JsTruthy::of($s->get('evenementInGemeente.brk_identification'))`
- **Rule6b2aeed1** (`6b2aeed1-8226-4a7c-9801-bbe61d576dca`) → show
  - condition: `$s->get('waarvoorWiltUEventloketGebruiken') === 'evenement'`
- **RuleB0b1b8ed** (`b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08`) → show
  - condition: `$s->get('evenementInGemeente') !== ''`

### `andersGroup` (1 rule)

- **RuleE0d010cd** (`e0d010cd-193d-4a26-8a01-89b185d5709e`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true`

### `bebordingsEnBewegwijzeringsplan` (1 rule)

- **Rule457c34ac** (`457c34ac-d4ac-4037-83b2-eaea58d24ccb`) → show
  - condition: `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A50') === true`

### `belemmeringVanVerkeer` (1 rule)

- **Rule8893efa1** (`8893efa1-663a-4ad6-9184-46ae7cb2ebf7`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true`

### `beveiligers1` (1 rule)

- **RuleD5681327** (`d5681327-869c-4a3a-be73-88c973668af1`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true`

### `bouwsels` (4 rules)

- **Rule145ceec2** (`145ceec2-91c7-4e67-8195-2444d734ddfc`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true`
- **Rule21e363f3** (`21e363f3-9ca8-42d4-b52e-bddfab43ddd6`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true`
- **Rule889aed1d** (`889aed1d-d7bc-4a93-b5b6-00c01f812724`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true`
- **RuleD8d28395** (`d8d28395-9e5e-4570-a4f3-129ad988ae8f`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true`

### `bouwsels10MSup2Sup` (1 rule)

- **Rule8e1a11b9** (`8e1a11b9-59f2-407b-8fb1-0fbee9712c08`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true`

### `brandgevaarlijkeStoffen` (1 rule)

- **Rule2d10885d** (`2d10885d-3e3a-4df1-a17b-d979668d2581`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true`

### `brandstofopslag` (1 rule)

- **Rule2d10885d** (`2d10885d-3e3a-4df1-a17b-d979668d2581`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true`

### `content200` (1 rule)

- **RuleB0b1b8ed** (`b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08`) → show
  - condition: `$s->get('evenementInGemeente') !== ''`

### `contentBalon` (1 rule)

- **Rule35501489** (`35501489-2e07-4d62-b5df-da1b4795d5e7`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true`

### `contentDieren` (1 rule)

- **Rule72e81725** (`72e81725-03fc-4c6e-8218-603bc7f07ef8`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true`

### `contentGemeenteMelding` (2 rules)

- **Rule6b2aeed1** (`6b2aeed1-8226-4a7c-9801-bbe61d576dca`) → show
  - condition: `$s->get('waarvoorWiltUEventloketGebruiken') === 'evenement'`
- **RuleB0b1b8ed** (`b0b1b8ed-4bdf-4fde-9657-b11cd3d88f08`) → show
  - condition: `$s->get('evenementInGemeente') !== ''`

### `contentGoNext` (2 rules)

- **AlsIsGelijkAanJaOfVindendeactivitei** (`8e022b2c-1742-4ff7-a5a0-50d02d05833e`) → hide
  - condition: `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- **AlsIsGelijkAanNeeOfVindendeactivite** (`87482f34-1e1f-4853-b2da-312c9b2cebf0`) → show
  - condition: `($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')`

### `contentLasershow` (1 rule)

- **Rule199313af** (`199313af-cc35-4409-8398-294c658ae03f`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true`

### `contentRouteDoorkuistMeerdereGemeenteInfo` (1 rule)

- **AlsBoolEnReductieVan1Accumul** (`3247522b-8603-4c7c-ae8d-b92a75fb35d6`) → show
  - condition: `JsTruthy::of($s->get('routeDoorGemeentenNamen')) && ((is_array($s->get('routeDoorGemeentenNamen')) ? count($s->get('routeDoorGemeentenNamen')) : 0) >= 2) && JsTruthy::of($s->get('userSelectGemeente11'))`

### `contentTattoo` (1 rule)

- **Rule945f1606** (`945f1606-e086-4999-983b-8b9c83dab421`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true`

### `contentVuurkorf` (1 rule)

- **RuleAd8eb74d** (`ad8eb74d-08d5-4813-9c00-a914f6618300`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true`

### `contentVuurwerk` (1 rule)

- **RuleAd564ba5** (`ad564ba5-b144-438a-9449-dda1800ecbd3`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true`

### `contentWapen` (1 rule)

- **RuleF5363d0b** (`f5363d0b-b344-4350-86c4-063b2ea97516`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true`

### `contentZeppelin` (1 rule)

- **RuleD138e53e** (`d138e53e-eb22-4c93-9ec5-daba437208c3`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true`

### `douches` (1 rule)

- **Rule3d9f1e6c** (`3d9f1e6c-85a9-449d-91c5-ebef408dd538`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true`

### `ehbo` (1 rule)

- **RuleDcd1e4b3** (`dcd1e4b3-7706-48df-a08f-3ad84369d580`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true`

### `etenBereidenOfVerkopen` (1 rule)

- **RuleE8e0f322** (`e8e0f322-bd43-4e79-9a3b-be489189920b`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true`

### `evenmentenInDeBuurtContent` (1 rule)

- **AlsBool00876823** (`00876823-b3f3-44f6-a177-d355c84c0b12`) → show
  - condition: `JsTruthy::of($s->get('evenementenInDeGemeente'))`

### `extraAfval` (1 rule)

- **Rule03a87183** (`03a87183-48c3-4e5b-b6ec-287c4f3daf97`) → show
  - condition: `$s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true`

### `geluidstorens` (1 rule)

- **Rule615d524a** (`615d524a-498d-4e30-8279-2dc41ec7d6ac`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true`

### `groteVoertuigen` (2 rules)

- **Rule4a05099f** (`4a05099f-5ded-49b6-a0a6-fc1544b55c25`) → show
  - condition: `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true`
- **Rule565bccec** (`565bccec-1a7b-40f3-975f-0edf8402b461`) → show
  - condition: `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true`

### `infoTekstVeiligheidsplan` (1 rule)

- **AlsIsGelijkAanBOfIsGelijkAanC** (`f1202010-b8b7-45c0-8f31-756190313451`) → show
  - condition: `($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')`

### `kansspelen` (1 rule)

- **Rule9b066ee5** (`9b066ee5-3e95-45a1-9864-c444f1508300`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true`

### `loadUserInformation` (1 rule)

- **RuleF56a54dd** (`f56a54dd-4af9-452f-8bbd-cee5fba3c79b`) → hide
  - condition: `$s->get('eventloketSession') !== '{}'`

### `locatieSOpKaart` (1 rule)

- **RuleFaa5fae6** (`faa5fae6-c19f-4a8b-b138-a7b98fa44b95`) → show
  - condition: `$s->get('waarVindtHetEvenementPlaats.buiten') === true`

### `marktkramen` (1 rule)

- **Rule6cda93b8** (`6cda93b8-4b85-4e9b-bc0e-89c45329ddac`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true`

### `meldingvraag1` (1 rule)

- **AlsIsGelijkAanJaEnBoolGemeentevar** (`454a40c6-43c8-42cd-9d2f-6d2ace4fec53`) → show
  - condition: `($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_1'))`

### `meldingvraag2` (1 rule)

- **AlsIsGelijkAanJaEnBool172fe1ad** (`172fe1ad-207f-429a-ace2-d2d07b4ea92a`) → show
  - condition: `($s->get('meldingvraag1') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_2'))`

### `meldingvraag3` (1 rule)

- **AlsIsGelijkAanJaEnBool4e042329** (`4e042329-a992-45ae-998b-521ea980c55a`) → show
  - condition: `($s->get('meldingvraag2') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_3'))`

### `meldingvraag4` (1 rule)

- **AlsIsGelijkAanJaEnBoolC7431a0c** (`c7431a0c-f315-4768-8372-8703629228b8`) → show
  - condition: `($s->get('meldingvraag3') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_4'))`

### `meldingvraag5` (1 rule)

- **AlsIsGelijkAanJaEnBool** (`63781392-9b7b-45e3-823d-5b039784882e`) → show
  - condition: `($s->get('meldingvraag4') === 'Ja') && JsTruthy::of($s->get('gemeenteVariabelen.report_question_5'))`

### `metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX` (1 rule)

- **RuleE8e0f322** (`e8e0f322-bd43-4e79-9a3b-be489189920b`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true`

### `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2.locatieVanOvernachtenDoorPersoneelOrganisatie1` (1 rule)

- **Rule7b13e485** (`7b13e485-188e-4b37-8a31-c310ed165109`) → show
  - condition: `$s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers1') === 'Ja'`

### `opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1` (1 rule)

- **Rule32f9bd89** (`32f9bd89-ac3d-4fa4-b89f-1b9a48b13efb`) → show
  - condition: `$s->get('isErSprakeVanOvernachtenDoorPubliekDeelnemers') === 'Ja'`

### `organisatieInformatie` (2 rules)

- **AlsBool** (`ce043762-6d77-44dc-8e8c-cb605e9acdfa`) → show
  - condition: `JsTruthy::of($s->get('eventloketSession.kvk'))`
- **AlsIsGelijkAan** (`1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a`) → hide
  - condition: `$s->get('eventloketSession.kvk') === ''`

### `overkappingen` (1 rule)

- **RuleBf2ee2f8** (`bf2ee2f8-9ea4-49a2-b1ab-2295c3b7052b`) → show
  - condition: `$s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true`

### `overnachtingen` (1 rule)

- **RuleB782fae6** (`b782fae6-2270-4f90-930a-af073989e0f9`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true`

### `podia` (1 rule)

- **Rule0c026fb1** (`0c026fb1-e43c-4fa7-a33f-615efd68d3bb`) → show
  - condition: `$s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true`

### `risicoClassificatieContent` (1 rule)

- **RuleB4fefcd8** (`b4fefcd8-faae-4139-93e1-e4b8108d6376`) → show
  - condition: `$s->get('risicoClassificatie') !== ''`

### `tenten` (1 rule)

- **Rule8aa421de** (`8aa421de-5ac8-4451-a646-ef94e82e0d00`) → show
  - condition: `$s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true`

### `toegangVoorHulpdienstenIsBeperkt` (1 rule)

- **Rule2a01382c** (`2a01382c-1fd2-4aac-82c7-c5fc22a5a4bf`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true`

### `userSelectGemeente` (1 rule)

- **AlsReductieVan1BeginnendBij0IsGroterD** (`e3992429-730a-4ed9-af3c-62ad897933fe`) → show
  - condition: `(is_array($s->get('evenementInGemeentenNamen')) ? count($s->get('evenementInGemeentenNamen')) : 0) >= 2`

### `veiligheidsplan` (1 rule)

- **AlsIsGelijkAanBOfIsGelijkAanC** (`f1202010-b8b7-45c0-8f31-756190313451`) → show
  - condition: `($s->get('risicoClassificatie') === 'B') || ($s->get('risicoClassificatie') === 'C')`

### `verkeersregelaars` (1 rule)

- **Rule2bbecc17** (`2bbecc17-8f88-474d-9399-acb4cd509541`) → show
  - condition: `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true`

### `verkooppuntenCashless` (1 rule)

- **RuleE21a3eae** (`e21a3eae-6e0f-479e-84e7-122e3401aac4`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true`

### `verkooppuntenMuntenEnBonnen` (1 rule)

- **RuleE21a3eae** (`e21a3eae-6e0f-479e-84e7-122e3401aac4`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true`

### `verkooppuntenToegangsKaarten` (1 rule)

- **RuleC1117aff** (`c1117aff-045d-4bf9-80c3-0ad446282328`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true`

### `versterkteMuziek` (1 rule)

- **Rule7b285070** (`7b285070-2c40-4d8f-9b18-d20dd745bbd4`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true`

### `vervoersmaatregelen` (1 rule)

- **RuleF494443a** (`f494443a-ef9a-4cb0-a0ff-9f422e2c3d2c`) → show
  - condition: `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true`

### `verzorgingVanKinderenJongerDan12Jaar` (1 rule)

- **Rule79be7168** (`79be7168-edd7-48db-af66-525fa6a5815a`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true`

### `voorwerpen` (7 rules)

- **Rule615d524a** (`615d524a-498d-4e30-8279-2dc41ec7d6ac`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true`
- **Rule6cda93b8** (`6cda93b8-4b85-4e9b-bc0e-89c45329ddac`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true`
- **RuleAcc04d68** (`acc04d68-e446-4c59-b8a5-d40ef246ee74`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true`
- **RuleC1117aff** (`c1117aff-045d-4bf9-80c3-0ad446282328`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true`
- **RuleE0d010cd** (`e0d010cd-193d-4a26-8a01-89b185d5709e`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true`
- **RuleE21a3eae** (`e21a3eae-6e0f-479e-84e7-122e3401aac4`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true`
- **RuleE9cf76d6** (`e9cf76d6-9eca-4d23-b546-f6f4a9c4d471`) → show
  - condition: `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true`

### `wCs` (1 rule)

- **Rule935dc38c** (`935dc38c-383c-4c3d-abe1-a741bfba4a32`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true`

### `waarschuwingGeenKvk` (1 rule)

- **AlsIsGelijkAan** (`1d1ef5b0-f099-4585-a6b5-db9fad8f3a7a`) → show
  - condition: `$s->get('eventloketSession.kvk') === ''`

### `watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc` (1 rule)

- **Rule21e363f3** (`21e363f3-9ca8-42d4-b52e-bddfab43ddd6`) → show
  - condition: `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true`

### `watVoorBouwselsPlaatsUOpDeLocaties` (1 rule)

- **Rule8e1a11b9** (`8e1a11b9-59f2-407b-8fb1-0fbee9712c08`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true`

### `wegOfVaarwegAfsluiten` (1 rule)

- **Rule2e67feb4** (`2e67feb4-08d6-46f8-ab24-3ee91a387cb7`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true`

### `welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement` (1 rule)

- **Rule0a5531ff** (`0a5531ff-5f95-42e3-b911-53affa4c88d6`) → show
  - condition: `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true`

### `welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` (1 rule)

- **Rule7b285070** (`7b285070-2c40-4d8f-9b18-d20dd745bbd4`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true`

### `wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning` (1 rule)

- **Rule7b285070** (`7b285070-2c40-4d8f-9b18-d20dd745bbd4`) → show
  - condition: `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true`

### `wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` (6 rules)

- **AlsIsGelijkAanJa** (`a757ea1f-24ee-40b8-a839-4e9997a33959`) → show
  - condition: `$s->get('meldingsvraag5') === 'Ja'`
- **AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti** (`ceac4877-e22f-4d59-afac-cf2f29cb93d9`) → show
  - condition: `($s->get('meldingvraag4') === 'Ja') && ((array_values(array_filter([ 0 => 'gemeenteVariabelen.report_question_5', ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)`
- **AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti981e2b88** (`981e2b88-49b3-4096-ae1d-07a4500e7ccc`) → show
  - condition: `($s->get('meldingvraag2') === 'Ja') && ((array_values(array_filter([ 0 => 'gemeenteVariabelen.report_question_3', ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)`
- **AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiB741d925** (`b741d925-75bf-4b8f-a0aa-47cdb0e5341d`) → show
  - condition: `($s->get('meldingvraag3') === 'Ja') && ((array_values(array_filter([ 0 => 'gemeenteVariabelen.report_question_4', ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)`
- **AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiEa096e0f** (`ea096e0f-e793-4df7-8292-df26ad862dc9`) → show
  - condition: `($s->get('meldingvraag1') === 'Ja') && ((array_values(array_filter([ 0 => 'gemeenteVariabelen.report_question_2', ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)`
- **AlsIsGelijkAanNeeEnTrueAlsOntbrek** (`a64ed84a-d0a3-4560-b782-a24be41b3e4a`) → show
  - condition: `($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Ja') && ((array_values(array_filter([ 0 => 'gemeenteVariabelen.report_question_1', ], static fn ($k) => $s->get($k) === null || $s->get($k) === ''))) ? true : false)`

## Stappen die door rules op niet-applicable gezet worden

### Step `5f986f16-6a3a-4066-9383-d71f09877f47`

**Wordt non-applicable door:**
- AlsIsGelijkAanNeeOfVindendeactivite — `($s->get('isHetAantalAanwezigenBijUwEvenementMinderDanSdf') === 'Nee') || ($s->get('vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen') === 'Nee') || ($s->get('WordtErAlleenMuziekGeluidGeproduceerdTussen') === 'Nee') || ($s->get('IsdeGeluidsproductieLagerDan') === 'Nee') || ($s->get('erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten') === 'Nee') || ($s->get('wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst') === 'Nee') || ($s->get('indienErObjectenGeplaatstWordenZijnDezeDanKleiner') === 'Nee') || ($s->get('meldingvraag1') === 'Nee') || ($s->get('meldingvraag2') === 'Nee') || ($s->get('meldingvraag3') === 'Nee') || ($s->get('meldingvraag4') === 'Nee') || ($s->get('meldingvraag5') === 'Nee') || ($s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Ja')`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `661aabb7-e927-4a75-8d95-0a665c5d83fe`

**Wordt applicable door:**
- Rule0c026fb1 — `$s->get('watVoorBouwselsPlaatsUOpDeLocaties.A55') === true`
- Rule2a01382c — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A11') === true`
- Rule2e67feb4 — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A10') === true`
- Rule7b285070 — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A1') === true`
- Rule8893efa1 — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A8') === true`
- Rule8aa421de — `$s->get('watVoorBouwselsPlaatsUOpDeLocaties.A54') === true`
- Rule8e1a11b9 — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A3') === true`
- Rule9b066ee5 — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A4') === true`
- RuleB92d2e5a — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A5') === true`
- RuleBf2ee2f8 — `$s->get('watVoorBouwselsPlaatsUOpDeLocaties.A56') === true`
- RuleE8e0f322 — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.A7') === true`

**Wordt non-applicable door:**
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`
- RuleD566bba6 — `$s->get('kruisAanWatVanToepassingIsVoorUwEvenementX.') === true`

### Step `6e285ace-f891-4324-b54e-639c1cfff9fa`

**Wordt applicable door:**
- Rule199313af — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38') === true`
- Rule35501489 — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37') === true`
- Rule72e81725 — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40') === true`
- Rule945f1606 — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42') === true`
- RuleAd564ba5 — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41') === true`
- RuleAd8eb74d — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43') === true`
- RuleD138e53e — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39') === true`
- RuleF5363d0b — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44') === true`

**Wordt non-applicable door:**
- Rule0a5531ff — `$s->get('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45') === true`
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `8a5fb30f-287e-41a2-a9bc-e7340bdaaa99`

**Wordt applicable door:**
- Rule03a87183 — `$s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33') === true`
- Rule0ab47106 — `$s->get('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32') === true`

**Wordt non-applicable door:**
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `ae44ab5b-c068-4ceb-b121-6e6907f78ef9`

**Wordt non-applicable door:**
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `c75cc256-6729-4684-9f9b-ede6265b3e72`

**Wordt non-applicable door:**
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `d790edb5-712a-4f83-87a8-1a86e4831455`

**Wordt applicable door:**
- Rule2d10885d — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26') === true`
- Rule615d524a — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27') === true`
- Rule6cda93b8 — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29') === true`
- RuleAcc04d68 — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25') === true`
- RuleC1117aff — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23') === true`
- RuleE0d010cd — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30') === true`
- RuleE21a3eae — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24') === true`
- RuleE9cf76d6 — `$s->get('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28') === true`

**Wordt non-applicable door:**
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `d87c01ce-8387-43b0-a8c8-e6cf5abb6da1`

**Wordt non-applicable door:**
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `e8f00982-ee47-4bec-bf31-a5c8d1b05e5e`

**Wordt applicable door:**
- Rule2bbecc17 — `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51') === true`
- Rule4a05099f — `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49') === true`
- Rule565bccec — `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48') === true`
- RuleF494443a — `$s->get('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52') === true`

**Wordt non-applicable door:**
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`

### Step `f4e91db5-fd74-4eba-b818-96ed2cc07d84`

**Wordt applicable door:**
- Rule145ceec2 — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A20') === true`
- Rule21e363f3 — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A18') === true`
- Rule3d9f1e6c — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A13') === true`
- Rule79be7168 — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A15') === true`
- Rule889aed1d — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A21') === true`
- Rule935dc38c — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A12') === true`
- RuleB782fae6 — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A17') === true`
- RuleD5681327 — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A53') === true`
- RuleD8d28395 — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A19') === true`
- RuleDcd1e4b3 — `$s->get('welkeVoorzieningenZijnAanwezigBijUwEvenement.A14') === true`

**Wordt non-applicable door:**
- Rule3a1ac5f3 — `$s->get('wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer') === 'Nee'`
- Rule8f418d89 — `$s->get('waarvoorWiltUEventloketGebruiken') === 'vooraankondiging'`


*Einde inventaris*

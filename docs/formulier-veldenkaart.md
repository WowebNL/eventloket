# Evenementformulier — Volledige Veldenkaart

Dit document beschrijft elk veld, elke stap, en elke logica regel van het
PRE-PROD Evenementformulier in Open Forms.

**Totaal**: 342 velden over 17 stappen, 0 logica acties op velden, 64 stap-visibility regels

---

## Stap 0: Contactgegevens

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `watIsUwVoornaam` | textfield | Wat is uw voornaam? | ✓ |  |  |
| `watIsUwAchternaam` | textfield | Wat is uw achternaam? | ✓ |  |  |
| `watIsUwEMailadres` | email | Wat is uw e-mailadres? | ✓ |  |  |
| `watIsUwTelefoonnummer` | textfield | Wat is uw telefoonnummer? | ✓ |  |  |
| `organisatieInformatie` | fieldset `hidden` | Organisatie informatie |  |  |  |
| —`watIsHetKamerVanKoophandelNummerVanUwOrganisatie` | textfield | Wat is het Kamer van Koophandel nummer van uw organisat | ✓ |  |  |
| —`watIsDeNaamVanUwOrganisatie` | textfield | Wat is de naam van uw organisatie? | ✓ |  |  |
| —`kolommen1` | columns | Kolommen |  |  |  |
| ——`postcode1` | textfield | Postcode | ✓ |  |  |
| ——`huisletter1` | textfield | Huisletter |  |  |  |
| ——`straatnaam1` | textfield | Straatnaam | ✓ |  |  |
| ——`huisnummer1` | textfield | Huisnummer | ✓ |  |  |
| ——`huisnummertoevoeging1` | textfield | Huisnummertoevoeging |  |  |  |
| ——`plaatsnaam1` | textfield | Plaatsnaam | ✓ |  |  |
| —`emailadresOrganisatie` | email | Wat is het e-mailadres van uw organisatie? |  |  |  |
| —`telefoonnummerOrganisatie` | textfield | Wat is het telefoonnummer van uw organisatie? | ✓ |  |  |
| `adresgegevens` | fieldset `hidden` | Adresgegevens |  |  |  |
| —`kolommen` | columns | Kolommen |  |  |  |
| ——`postcode` | textfield | Postcode | ✓ |  |  |
| ——`huisletter` | textfield | Huisletter |  |  |  |
| ——`straatnaam` | textfield | Straatnaam | ✓ |  |  |
| ——`land` | textfield | Land |  |  |  |
| ——`huisnummer` | textfield | Huisnummer | ✓ |  |  |
| ——`huisnummertoevoeging` | textfield | Huisnummertoevoeging |  |  |  |
| ——`plaatsnaam` | textfield | Plaatsnaam | ✓ |  |  |
| `extraContactpersonenToevoegen` | selectboxes | Extra contactpersonen toevoegen |  |  | `vooraf=Contactpersoon voorafgaand aan het `, `tijdens=Contactpersoon tijdens het evenemen`, `achteraf=Contactpersoon na het evenement` |
| `contactpersoonVoorafgaandAanHetEvenement` | fieldset | Contactpersoon voorafgaand aan het evenement |  | toon als [extraContactpersonenToevoegen] = [vooraf] |  |
| —`naam` | textfield | Naam | ✓ |  |  |
| —`telefoonnummer` | phoneNumber | Telefoonnummer | ✓ |  |  |
| —`eMailadres` | email | E-mailadres | ✓ |  |  |
| `contactpersoonVoorafgaandAanHetEvenement1` | fieldset | Contactpersoon tijdens het evenement |  | toon als [extraContactpersonenToevoegen] = [tijdens] |  |
| —`naam1` | textfield | Naam | ✓ |  |  |
| —`telefoonnummer1` | phoneNumber | Telefoonnummer | ✓ |  |  |
| —`eMailadres1` | email | E-mailadres | ✓ |  |  |
| `contactpersoonVoorafgaandAanHetEvenement2` | fieldset | Contactpersoon na het evenement |  | toon als [extraContactpersonenToevoegen] = [achteraf] |  |
| —`naam2` | textfield | Naam | ✓ |  |  |
| —`telefoonnummer2` | phoneNumber | Telefoonnummer | ✓ |  |  |
| —`eMailadres2` | email | E-mailadres | ✓ |  |  |

## Stap 1: Het evenement

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `watIsDeNaamVanHetEvenementVergunning` | textfield | Wat is de naam van het evenement? | ✓ |  |  |
| `geefEenKorteOmschrijvingVanHetEvenementWatIsDeNaamVanHetEvenementVergunning` | textarea | Geef een korte omschrijving van het evenement {{ watIsD | ✓ | verberg als [watIsDeNaamVanHetEvenementVergunning] = [] |  |
| `soortEvenement` | select | Wat voor soort evenement is {{ watIsDeNaamVanHetEveneme | ✓ | verberg als [watIsDeNaamVanHetEvenementVergunning] = [] | `=` |
| `omschrijfHetSoortEvenement` | textarea | Omschrijf het soort evenement | ✓ | toon als [soortEvenement] = [Anders] |  |
| `gaatHetHierOmEenPeriodiekTerugkerendeMarktJaarmarktOfWeekmarktWaarvoorDeGemeenteEenBesluitHeeftGenomenMetBetrekkingTotDeMarktdagen` | radio | Gaat het hier om een periodiek terugkerende markt (jaar |  | toon als [soortEvenement] = [Markt of braderie] | `=` |

## Stap 2: Locatie

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `waarVindtHetEvenementPlaats` | selectboxes | Waar vindt het evenement {{ watIsDeNaamVanHetEvenementV | ✓ |  | `gebouw=In een gebouw of meerdere gebouwen`, `buiten=Buiten op één of meerdere plaatsen`, `route=Op een route` |
| `veldengroep` | fieldset | In een gebouw of meerdere gebouwen |  | toon als [waarVindtHetEvenementPlaats] = [gebouw] |  |
| `adresVanDeGebouwEn` | editgrid `hidden` | Adres van de gebouw(en) | ✓ |  |  |
| —`naamVanDeLocatieGebouw` | textfield | Naam van de locatie | ✓ |  |  |
| —`adresVanHetGebouwWaarUwEvenementPlaatsvindt1` | addressNL | Adres van het gebouw waar uw evenement plaatsvindt. | ✓ |  |  |
| `buitenOpEenOfMeerderePlaatsen` | fieldset | Buiten op één of meerdere plaatsen |  | toon als [waarVindtHetEvenementPlaats] = [buiten] |  |
| `locatieSOpKaart` | editgrid `hidden` | Locatie(s) op kaart | ✓ |  |  |
| —`naamVanDeLocatieKaart` | textfield | Naam van de locatie | ✓ |  |  |
| —`buitenLocatieVanHetEvenement` | map | Buiten locatie van het evenement | ✓ |  |  |
| `route` | fieldset | Route |  | toon als [waarVindtHetEvenementPlaats] = [route] |  |
| —`routesOpKaart` | editgrid | Route op kaart | ✓ |  |  |
| ——`routeVanHetEvenement` | map | Route van het evenement | ✓ |  |  |
| —`gpxBestandVanDeRoute` | file | GPX bestand van de route |  |  |  |
| —`naamVanDeRoute` | textfield | Naam van de route | ✓ |  |  |
| —`watVoorEvenementGaatPlaatsvindenOpDeRoute1` | select | Wat voor evenement gaat plaatsvinden op de route? | ✓ |  | `fietstochtGeenWedstrijd=Fietstocht - geen wedstrijd`, `fietstochtWedstrijd=Fietstocht - wedstrijd`, `gemotoriseerdeToertochtGeenWedstrijd=Gemotoriseerde toertocht - geen wed`, `gemotoriseerdeToertochtWedstrijd=Gemotoriseerde toertocht - wedstrij` | +5 meer |
| —`welkSoortRouteEvenementBetreftUwEvenementX` | textarea | Welk soort evenement vindt plaats op de route? | ✓ | toon als [watVoorEvenementGaatPlaatsvindenOpDeRoute1] = [A114] |  |
| —`komtUwRouteOverWegenVanWegbeheerdersAndersDanDeBetreffendeGemeenteZoJaKruisDezeDanAan` | selectboxes | Komt uw route over wegen van wegbeheerders, anders dan  |  |  | `provincie=Provincie`, `waterschap=Waterschap`, `rijkswaterstaat=Rijkswaterstaat`, `staatsbosbeheer=Staatsbosbeheer` |
| `userSelectGemeente` | radio `hidden` | De ingevoerde locatie(s) of route valt binnen of doorkr | ✓ |  | `=` |

## Stap 3: Tijden

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `kolommen3` | columns | Kolommen |  |  |  |
| —`EvenementStart` | datetime | Wat is de start datum en tijdstip van uw evenement {{ w | ✓ |  |  |
| —`EvenementEind` | datetime | Wat is de eind datum en tijdstip van uw evenement {{ wa | ✓ |  |  |
| `zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten` | radio | Zijn er voorafgaand aan het evenement {{ watIsDeNaamVan | ✓ |  | `=` |
| `opbouwperiode` | columns | Kolommen |  |  |  |
| —`OpbouwStart` | datetime | Wat is de start datum en tijd van de opbouw uw evenemen | ✓ | toon als [zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten] = [Ja] |  |
| —`OpbouwEind` | datetime | Wat is de eind datum en tijd van de opbouw van uw evene | ✓ | toon als [zijnErVoorafgaandAanHetEvenementOpbouwactiviteiten] = [Ja] |  |
| `zijnErTijdensHetEvenementXOpbouwactiviteiten` | radio | Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvene | ✓ |  | `=` |
| `zijnErAansluitendAanHetEvenementAfbouwactiviteiten` | radio | Zijn er aansluitend aan het evenement {{ watIsDeNaamVan | ✓ |  | `=` |
| `opbouwperiode1` | columns | Kolommen |  |  |  |
| —`AfbouwStart` | datetime | Wat is de start datum en tijdstip van de afbouw uw even | ✓ | toon als [zijnErAansluitendAanHetEvenementAfbouwactiviteiten] = [Ja] |  |
| —`AfbouwEind` | datetime | Wat is de eind datum en tijdstip van de afbouw van uw e | ✓ | toon als [zijnErAansluitendAanHetEvenementAfbouwactiviteiten] = [Ja] |  |
| `zijnErTijdensHetEvenementXAfbouwactiviteiten3` | radio | Zijn er tijdens het evenement {{ watIsDeNaamVanHetEvene | ✓ |  | `=` |

## Stap 4: Vooraankondiging

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `waarvoorWiltUEventloketGebruiken` | radio | Waarvoor wilt u Eventloket gebruiken? | ✓ |  | `evenement=U wilt voor uw evementen een aanvra`, `vooraankondiging=U wilt voor uw evenement een vooraa` |
| `vooraankondiginggroep` | fieldset `hidden` | Vooraankondiging |  | toon als [waarvoorWiltUEventloketGebruiken] = [vooraankondiging] |  |
| —`aantalVerwachteAanwezigen` | number | Aantal verwachte aanwezigen | ✓ |  |  |

## Stap 5: Vergunningsplichtig scan

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `algemeneVragen` | fieldset `hidden` | Algemene vragen |  |  |  |
| —`isHetAantalAanwezigenBijUwEvenementMinderDanSdf` | radio | Is het aantal aanwezigen bij uw evenement minder dan {% | ✓ |  | `=` |
| —`vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen` | radio | Vinden de activiteiten van uw evenement plaats tussen { | ✓ | toon als [isHetAantalAanwezigenBijUwEvenementMinderDanSdf] = [Ja] | `=` |
| —`WordtErAlleenMuziekGeluidGeproduceerdTussen` | radio | Wordt er alleen muziek/geluid geproduceerd tussen {{ ge | ✓ | toon als [vindenDeActiviteitenVanUwEvenementPlaatsTussenTijdstippen] = [Ja] | `=` |
| —`IsdeGeluidsproductieLagerDan` | radio | Is de geluidsproductie lager dan {{ gemeenteVariabelen. | ✓ | toon als [WordtErAlleenMuziekGeluidGeproduceerdTussen] = [Ja] | `=` |
| —`erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten` | radio | Er vinden GEEN activiteiten plaats op de rijbaan, (brom | ✓ | toon als [IsdeGeluidsproductieLagerDan] = [Ja] | `=` |
| —`wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst` | radio | Worden er minder dan {{ gemeenteVariabelen.aantal_objec | ✓ | toon als [erVindenGeenActiviteitenPlaatsOpDeRijbaanBromFietspadOfParkeerplaatsOfAnderszinsEenBelemmeringVormenVoorHetVerkeerEnDeHulpdiensten] = [Ja] | `=` |
| —`indienErObjectenGeplaatstWordenZijnDezeDanKleiner` | radio | Indien er objecten geplaatst worden, zijn deze dan klei | ✓ | toon als [wordenErMinderDanObjectenBijvTentSpringkussenGeplaatst] = [Ja] | `=` |
| —`meldingvraag1` | radio `hidden` | {{ gemeenteVariabelen.report_question_1 }} | ✓ |  | `=` |
| —`meldingvraag2` | radio `hidden` | {{ gemeenteVariabelen.report_question_2 }} | ✓ |  | `=` |
| —`meldingvraag3` | radio `hidden` | {{ gemeenteVariabelen.report_question_3 }} | ✓ |  | `=` |
| —`meldingvraag4` | radio `hidden` | {{ gemeenteVariabelen.report_question_4 }} | ✓ |  | `=` |
| —`meldingvraag5` | radio `hidden` | {{ gemeenteVariabelen.report_question_5 }} | ✓ |  | `=` |
| —`wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer` | radio `hidden` | Worden er gebiedsontsluitingswegen en/of doorgaande weg | ✓ |  | `=` |

## Stap 6: Melding

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `wordtErAlcoholGeschonkenTijdensUwEvenement` | radio | Wordt er alcohol geschonken tijdens uw evenement? | ✓ |  | `=` |
| `wordenErFilmopnamesMetBehulpVanDronesGemaakt` | radio | Worden er filmopnames met behulp van drones gemaakt?  | ✓ |  | `=` |
| `vindenErActiviteitenPlaatsWaarvoorMogelijkBrandveiligheidseisenGelden` | radio | Vinden er activiteiten plaats, waarvoor mogelijk brandv |  |  | `=` |

## Stap 7: Risicoscan

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `watIsDeAantrekkingskrachtVanHetEvenement` | radio | Wat is de aantrekkingskracht van het evenement? | ✓ |  | `0.5=Wijk of buurt`, `1=Dorp`, `1.5=Gemeentelijk`, `2=Regionaal` | +2 meer |
| `watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep` | radio | Wat is de belangrijkste leeftijdscategorie van de doelg | ✓ |  | `0.25=0-15 jaar / met begeleiding`, `0.5=0-15 jaar / zonder begeleiding`, `0.75=15-18 jaar`, `0.5=18-30 jaar` | +3 meer |
| `isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid` | radio | Is er sprake van aanwezigheid van politieke aandacht en | ✓ |  | `0=Nee`, `1=Ja` |
| `isEenDeelVanDeDoelgroepVerminderdZelfredzaam` | radio | Is een deel van de doelgroep verminderd zelfredzaam? | ✓ |  | `1=Niet zelfredzaam`, `0.5=Beperkt zelfredzaam`, `0.25=Voldoende zelfredzaam`, `0=Volledig zelfredzaam` |
| `isErSprakeVanAanwezigheidVanRisicovolleActiviteiten` | radio | Is er sprake van aanwezigheid van risicovolle activitei | ✓ |  | `0=Nee`, `1=Ja` |
| `watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep` | radio | Wat is het grootste deel van de samenstelling van de do | ✓ |  | `0.5=Alleen toeschouwers`, `0.75=Combinatie toeschouwers en deelneme`, `1=Alleen deelnemers` |
| `isErSprakeVanOvernachten` | radio | Is er sprake van overnachten? | ✓ |  | `0=Er wordt niet overnacht of er wordt`, `1=Er wordt overnacht op een niet daar` |
| `isErGebruikVanAlcoholEnDrugs` | radio | Is er gebruik van alcohol en drugs? | ✓ |  | `0=Niet aanwezig`, `0.5=Aanwezig, zonder risicoverwachting`, `1=Aanwezig, met risicoverwachting` |
| `watIsHetAantalGelijktijdigAanwezigPersonen` | radio | Wat is het aantal gelijktijdig aanwezig personen? | ✓ |  | `0=Minder dan 150`, `0.25=150 - 2.000`, `0.5=2.000 - 5.000`, `0.75=5.000 - 10.000` | +2 meer |
| `inWelkSeizoenVindtHetEvenementPlaats` | radio | In welk seizoen vindt het evenement plaats? | ✓ |  | `0.25=Lente of herfst`, `0.5=Zomer of winter` |
| `inWelkeLocatieVindtHetEvenementPlaats` | radio | In welke locatie vindt het evenement plaats? | ✓ |  | `0.25=In een gebouw, als een daartoe inge`, `0.75=In een gebouw, als een niet daartoe`, `0.75=In een bouwsel`, `0.5=In de open lucht, op een daartoe in` | +2 meer |
| `opWelkSoortOndergrondVindtHetEvenementPlaats` | radio | Op welk soort ondergrond vindt het evenement plaats? | ✓ |  | `0.25=Verharde ondergrond`, `0.5=Onverharde ondergrond, vochtdoorlat`, `0.75=Onverharde ondergrond, drassig` |
| `watIsDeTijdsduurVanHetEvenement` | radio | Wat is de tijdsduur van het evenement? | ✓ |  | `0=Minder dan 3 uur tijdens daguren`, `0.25=Minder dan 3 uur tijdens avond- en `, `0.5=Tijdsduur van 3-12 uren tijdens de `, `0.75=Tijdsduur van 3 - 12 uren tijdens d` | +2 meer |
| `welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing` | radio | Welke beschikbaarheid van aan- en afvoerwegen is van to | ✓ |  | `1=Geen aan- en afvoerwegen`, `0.75=Matige aan- en afvoerwegen`, `0.5=Redelijke aan- en afvoerwegen`, `0=Goede aan- en afvoerwegen` |

## Stap 8: Vergunningsaanvraag: soort

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `voordatUVerderGaatMetHetBeantwoordenVanDeVragenVoorUwEvenementWillenWeGraagWetenOfUEerderEenVooraankondigingHeeftIngevuldVoorDitEvenement` | radio | Voordat u verder gaat met het beantwoorden van de vrage | ✓ |  | `=` |
| `watIsTijdensDeHeleDuurVanUwEvenementWatIsDeNaamVanHetEvenementVergunningHetTotaalAantalAanwezigePersonenVanAlleDagenBijElkaarOpgeteld` | number | Wat is tijdens de hele duur van uw evenement {{ watIsDe | ✓ |  |  |
| `watIsHetMaximaalAanwezigeAantalPersonenDatOpEnigMomentAanwezigKanZijnBijUwEvenementX` | number | Wat is het maximaal aanwezige aantal personen dat op en | ✓ |  |  |
| `watZijnDeBelangrijksteLeeftijdscategorieenVanHetPubliekTijdensUwEvenement` | radio | Wat zijn de belangrijkste leeftijdscategorieen van het  | ✓ |  | `018Jaar=0 - 18 jaar`, `1830Jaar=18 - 30 jaar`, `3045Jaar=30 - 45 jaar`, `45JaarEnOuder=45 jaar en ouder` |
| `isUwEvenementXGratisToegankelijkVoorHetPubliek` | radio | Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning | ✓ |  | `=` |
| `kruisAanWatVanToepassingIsVoorUwEvenementX` | selectboxes | Kruis aan wat van toepassing is voor uw evenement {{ wa |  |  | `A1=(Versterkte) muziek`, `A2=Versterkte spraak`, `A3=Bouwsels plaatsen groter dan 10m2, `, `A4= Een kansspel organiseren, zoals ee` | +7 meer |
| `welkeVoorzieningenZijnAanwezigBijUwEvenement` | selectboxes | Welke voorzieningen zijn aanwezig bij uw evenement {{ w |  |  | `A12=WC's plaatsen (of bestaande gebruik`, `A13=Douches plaatsen (of bestaande gebr`, `A53=Beveiligers inhuren`, `A14=Medische voorzieningen  treffen (Ve` | +8 meer |
| `welkeOverigeBouwwerkenGaatUPlaatsen` | textarea | Welke overige bouwwerken gaat u plaatsen? | ✓ | toon als [welkeVoorzieningenZijnAanwezigBijUwEvenement] = [A22] |  |
| `welkeVoorwerpenGaatUPlaatsenBijUwEvenementX` | selectboxes | Welke voorwerpen gaat u plaatsen bij uw evenement {{ wa |  |  | `A23=Verkooppunten  voor toegangskaarten`, `A24=Verkooppunten  voor consumptiemunte`, `A25=Speeltoestellen Attractietoestellen`, `A26=Aggregaten,  brandstofopslag en and` | +4 meer |
| `welkeAnderVoorwerpenGaatUPlaatsenBijEvenementX` | textarea | welke ander voorwerpen gaat u plaatsen bij evenement {{ |  | toon als [welkeVoorwerpenGaatUPlaatsenBijUwEvenementX] = [A30] |  |
| `kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX` | selectboxes | Kruis aan welke overige maatregelen/gevolgen van toepas |  |  | `A31=Toegangscontrole`, `A32=(Laten) aanpassen locatie en/of ver`, `A33=Er ontstaat extra afval`, `A34=Gebruik van eco-glazen of statiegel` | +2 meer |
| `welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX` | selectboxes | Welke van de onderstaande activiteiten vinden verder no |  |  | `A37=Ballonnen oplaten`, `A38=Lasershow`, `A39=(Reclame)zeppelin oplaten`, `A40=Activiteiten met dieren` | +7 meer |
| `welkActiviteitBetreftUwEvenementX` | textarea | Welk activiteit betreft uw evenement {{ watIsDeNaamVanH | ✓ | toon als [welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX] = [A46] |  |
| `kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX` | selectboxes | Kruis aan wat voor overige kenmerken van toepassing zij |  |  | `A48=Voertuigen parkeren die langer zijn`, `A49=Voorwerpen op de weg plaatsen`, `A50=Bewegwijzering aanbrengen`, `A51=Verkeersregelaars inzetten` | +1 meer |
| `isUwEvenementToegankelijkVoorMensenMetEenBeperking` | radio | Is uw evenement {{ watIsDeNaamVanHetEvenementVergunning | ✓ | toon als [welkeVoorzieningenZijnAanwezigBijUwEvenement] = [A16] | `=` |
| `voorHoeveelMensenMetEenLichamelijkeOfGeestelijkeBeperkingVerzorgtUOpvangTijdensUwEvenementX` | number | Voor hoeveel mensen met een lichamelijke of geestelijke | ✓ | toon als [welkeVoorzieningenZijnAanwezigBijUwEvenement] = [A16] |  |
| `welkeMaatregelenHeeftUGenomenOmMensenMetEenBeperkingOngehinderdDeelTeLatenNemenAanUwEvenement` | textarea | Welke maatregelen heeft u genomen om mensen met een bep | ✓ | toon als [isUwEvenementToegankelijkVoorMensenMetEenBeperking] = [Ja] |  |

## Stap 9: Vergunningaanvraag: kenmerken

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `versterkteMuziek` | fieldset `hidden` | Versterkte muziek |  |  |  |
| —`wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning` | selectboxes `hidden` | Wie maakt de muziek op locatie bij uw evenement {{ watI | ✓ |  | `dj=DJ`, `band=Band`, `orkest=Orkest`, `tapeArtiest=(Tape-)artiest` | +1 meer |
| —`opWelkeAndereManierWordtErMuziekGemaakt` | textarea | Op welke andere manier wordt er muziek gemaakt? | ✓ | toon als [wieMaaktDeMuziekOpLocatieBijUwEvenementWatIsDeNaamVanHetEvenementVergunning] = [anders] |  |
| —`welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX` | selectboxes `hidden` | Welke soorten muziek zijn er te horen op locatie evenem | ✓ |  | `A69=Klassiek`, `A70=Jazz`, `A71=Dance`, `A72=Pop (en overige)` |
| —`welkeSoortenDanceMuziekZijnErTeHorenOpLocatieEvenementX` | selectboxes | Welke soorten Dance muziek zijn er te horen op locatie  | ✓ | toon als [welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX] = [A71] | `acid=Acid`, `ambient=Ambient`, `club=Club`, `disco=Disco` | +11 meer |
| —`welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement` | selectboxes | Welke soorten popmuziek zijn er te horen op locatie eve | ✓ | toon als [welkeSoortenMuziekZijnErTeHorenOpLocatieEvenementX] = [A72] | `blues=Blues`, `country=Country`, `disco=Disco`, `funk=Funk` | +14 meer |
| —`welkeAnderSoortPopmuziekIsErTeHorenOpEvenementX` | textarea | Welke ander soort popmuziek is er te horen op evenement | ✓ | toon als [welkeSoortenPopmuziekZijnErTeHorenOpLocatieEvenement] = [anders] |  |
| —`watIsDeGeluidsbelastingInDecibelDBANorm0103DBVanUwEvenementX` | number | Wat is de geluidsbelasting in decibel (dB(A) norm - (0– | ✓ |  |  |
| —`watIsDeGeluidsbelastingInDecibelDBCNorm0103DBVanUwEvenement` | number | Wat is de geluidsbelasting in decibel Db(C) norm - (0–1 | ✓ |  |  |
| `bouwsels10MSup2Sup` | fieldset `hidden` | Bouwsels &gt; 10m<sup>2</sup>  |  |  |  |
| —`watVoorBouwselsPlaatsUOpDeLocaties` | selectboxes `hidden` | Wat voor bouwsels plaats u op de locaties? | ✓ |  | `A54=Tent(en)`, `A55=Podia`, `A56=Overkappingen`, `A57=Omheiningen` |
| —`tenten` | editgrid `hidden` | Welke tenten plaatst u? | ✓ |  |  |
| ——`tentnummer` | textfield | Tentnummer | ✓ |  |  |
| ——`lengteTent` | number | Lengte in meter | ✓ |  |  |
| ——`BreedteTent` | number | Breedte in meter | ✓ |  |  |
| ——`HoogteTent` | number | Hoogte in meter | ✓ |  |  |
| ——`wijzeVanVerankering` | radio | Wijze van verankering | ✓ |  | `palenInDeGrond=Palen in de grond`, `betonblokken=Betonblokken` |
| —`podia` | editgrid `hidden` | Welke podia plaatst u? | ✓ |  |  |
| ——`podiumnummer` | textfield | Podium nummer | ✓ |  |  |
| ——`lengtePodium` | number | Lengte in meter | ✓ |  |  |
| ——`BreedtePodium` | number | Breedte in meter | ✓ |  |  |
| ——`HoogtePodium` | number | Hoogte in meter | ✓ |  |  |
| —`overkappingen` | editgrid `hidden` | Welke overkappingen plaatst u? | ✓ |  |  |
| ——`overkappingnummer` | textfield | Overkapping nummer | ✓ |  |  |
| ——`lengteOverkapping` | number | Lengte in meter | ✓ |  |  |
| ——`BreedteOverkapping` | number | Breedte in meter | ✓ |  |  |
| ——`HoogteOverkapping` | number | Hoogte in meter | ✓ |  |  |
| ——`wijzeVanVerankering1` | radio | Wijze van verankering | ✓ |  | `palenInDeGrond=Palen in de grond`, `betonblokken=Betonblokken` |
| —`geefEenOmschrijvingVanSoortOmheining` | textarea | Geef een omschrijving van soort omheining | ✓ | toon als [watVoorBouwselsPlaatsUOpDeLocaties] = [A57] |  |
| —`plaatstUTijdelijkeConstructiesTentenPodiaEtcDanDientUNaastHetVeiligheidsplanTevensEenDeelplanTijdelijkeConstructiesTeMakenEnTeUploadenAlsBijlage` | file | Plaatst u tijdelijke constructies (tenten, podia etc.)  |  |  |  |
| `kansspelen` | fieldset `hidden` | Kansspelen |  |  |  |
| —`welkSoortKansspelBetreftHet` | textarea | Welk soort kansspel betreft het? | ✓ |  |  |
| —`isDeOrganisatieVanHetKansspelInHandenVanEenVereniging` | radio | Is de organisatie van het kansspel in handen van een ve | ✓ |  | `=` |
| —`bestaatDeVereningingDieHetKansspelOrganiseertLangerDan3Jaar` | radio | Bestaat de vereninging, die het kansspel organiseert la | ✓ | toon als [isDeOrganisatieVanHetKansspelInHandenVanEenVereniging] = [Ja] | `=` |
| —`watBentUVanPlanMetDeOpbrengstVanHetKansspelTeGaanDoen` | textarea | Wat bent u van plan met de opbrengst van het kansspel t | ✓ |  |  |
| —`geefEenIndicatieVanDeHoogteVanHetPrijzengeld` | currency | Geef een indicatie van de hoogte van het prijzengeld | ✓ |  |  |
| `alcoholischeDranken` | fieldset `hidden` | Alcoholische dranken |  |  |  |
| —`isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop` | radio | Is een persoon of organisatie verantwoordelijk voor de  | ✓ |  | `persoon=Persoon`, `organisatie=Organisatie` |
| —`persoongroep` | fieldset | Persoongroep |  | toon als [isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop] = [persoon] |  |
| ——`voornaamVanDePersoonAlcohol` | textfield | Voornaam van de persoon | ✓ |  |  |
| ——`achternaamVanDePersoon1Alcohol` | textfield | Achternaam van de persoon | ✓ |  |  |
| ——`geboortedatumPersoonAlcohol` | date | Geboortedatum persoon | ✓ |  |  |
| ——`geboorteplaatsPersoonAlcohol` | textfield | Geboorteplaats persoon | ✓ |  |  |
| —`organisatiegroep` | fieldset | Organisatiegroep |  | toon als [isEenPersoonOfOrganisatieVerantwoordelijkVoorDeAlcoholverkoop] = [organisatie] |  |
| ——`watIsDeNaamVanDeOrganisatie` | textfield | Wat is de naam van de organisatie? | ✓ |  |  |
| ——`watIsHetTelefoonnummerVanDeOrganisatie` | phoneNumber | Wat is het telefoonnummer van de organisatie? | ✓ |  |  |
| —`watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken` | editgrid | Op hoeveel punten en op welke locaties gaat u dranken e | ✓ |  |  |
| ——`naamVanDeLocatie` | textfield | Naam van de locatie | ✓ |  |  |
| ——`uitgiftepuntenVoedsel` | number | Uitgiftepunten voedsel | ✓ |  |  |
| ——`uitgiftepuntenDrank` | number | Uitgiftepunten drank | ✓ |  |  |
| ——`waarvanMetAlcohol` | number | Waarvan met alcohol | ✓ | verberg als [watZijnDeLocatiesWaarUDrankenEnOfVoedselGaatVerstrekken.uitgiftepuntenDrank] = [0] |  |
| `etenBereidenOfVerkopen` | fieldset `hidden` | Eten bereiden of verkopen |  |  |  |
| —`welkSoortBereidingVanEtenswarenIsVanToepassingOpLocatieEvenementX` | radio | Welk soort bereiding van etenswaren is van toepassing o | ✓ |  | `beperkteBereiding=Beperkte bereiding`, `eenvoudigeBereiding=Eenvoudige bereiding`, `uitgebreideBereiding=Uitgebreide bereiding` |
| —`maaktUGebruikVanEenCateraarSOpLocatieEvenementX` | radio | Maakt u gebruik van een cateraar(s) op locatie evenemen | ✓ |  | `=` |
| —`metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX` | selectboxes `hidden` | Met welke warmtebron wordt het eten ter plaatse klaarge | ✓ |  | `gas=Gas`, `houtskoolbarbecueOfHoutoven=Houtskoolbarbecue of houtoven`, `elektrisch=Elektrisch`, `frituur=Frituur` | +1 meer |
| —`welkeAndereWarmtebronWordtGebruikt` | textarea | Welke andere warmtebron wordt gebruikt? | ✓ | toon als [metWelkeWarmtebronWordtHetEtenTerPlaatseKlaargemaaktOpLocatieEvenementX] = [anders] |  |
| `belemmeringVanVerkeer` | fieldset `hidden` | Belemmering van verkeer |  |  |  |
| —`beschrijfOpWelkeWijzeErSprakeIsVanBelemmeringVanVerkeer` | textarea | Beschrijf op welke wijze er sprake is van belemmering v | ✓ |  |  |
| `wegOfVaarwegAfsluiten` | fieldset `hidden` | Weg of vaarweg afsluiten |  |  |  |
| —`welkeDoorgangenWiltUAfsluiten` | editgrid | Welke doorgangen wilt u afsluiten? | ✓ |  |  |
| ——`positieVanDeDoorgang` | map | Positie van de doorgang | ✓ |  |  |
| ——`naamVanDeDoorgang` | textfield | Naam van de doorgang | ✓ |  |  |
| ——`startVanDeAfsluiting` | datetime | Start van de afsluiting | ✓ |  |  |
| ——`eindVanDeAfsluiting` | datetime | Eind van de afsluiting | ✓ |  |  |
| `toegangVoorHulpdienstenIsBeperkt` | fieldset `hidden` | Toegang voor hulpdiensten is beperkt |  |  |  |
| —`watIsDeNaamVanDeFunctionarisOfPersoonDieDeTaakHeeftOmInGevalVanEenCalamiteitDeHulpdienstenOpTeVangen` | textfield | Wat is de naam van de functionaris of persoon die de ta | ✓ |  |  |
| —`watIsHetTelefoonnummerVanDeFunctionarisOfPersoonDieDeTaakHeeftOmInGevalVanEenCalamiteitDeHulpdienstenOpTeVangen` | phoneNumber | Wat is het telefoonnummer van de functionaris of persoo | ✓ |  |  |
| —`vermeldWaarBinnenOfBijHetEvenemententerreinDeHulpdienstenWordenOpgevangenInGevalVanEenCalamiteit` | textarea | Vermeld waar binnen of bij het evenemententerrein de hu | ✓ |  |  |

## Stap 10: Vergunningsaanvraag: voorzieningen

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `wCs` | fieldset `hidden` | WC's |  |  |  |
| —`hoeveelVasteToilettenZijnBeschikbaar` | number | Hoeveel vaste toiletten zijn beschikbaar? | ✓ |  |  |
| —`hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar` | number | Hoeveel tijdelijke chemische toiletten / Dixies zijn er | ✓ |  |  |
| —`hoeveelTijdelijkeDixiToilettenZijnErBeschikbaar` | number | Hoeveel tijdelijke gespoelde toiletten zijn er beschikb | ✓ | verberg als [hoeveelTijdelijkeChemischeToilettenZijnErBeschikbaar] = [0] |  |
| —`welkPercentageVanDeToilettenIsVoorHeren` | number | Hoeveel toiletten zijn voor heren? | ✓ |  |  |
| —`aantalToilettenDamen` | number | Hoeveel toiletten zijn voor dames? | ✓ |  |  |
| —`aantalToilettenMiva` | number | Hoeveel toiletten zijn voor MIVA/rolstoelgebruikers? | ✓ |  |  |
| —`handenwaspunten` | number | Hoeveel handenwaspunten worden er bij de toiletten inge | ✓ |  |  |
| —`reinigtUDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunning` | radio | Reinigt u de tijdelijke toiletten op locatie Evenement  | ✓ |  | `=` |
| —`gebruikenDeTijdelijkeToilettenOpLocatieEvenementWatIsDeNaamVanHetEvenementVergunningVoorHetSpoelenOppervlaktewater` | radio | Gebruiken de tijdelijke toiletten op locatie Evenement  | ✓ |  | `=` |
| `douches` | fieldset `hidden` | Douche's |  |  |  |
| —`hoeveelVasteDouchevoorzieningenZijnBeschikbaar` | number | Hoeveel vaste douchevoorzieningen zijn beschikbaar? | ✓ |  |  |
| —`hoeveelTijdelijkeDouchevoorzieningenZijnBeschikbaar` | number | Hoeveel tijdelijke douchevoorzieningen zijn beschikbaar | ✓ |  |  |
| —`wordenDeDouchesTussentijdsSchoonGemaakt` | radio | Worden de douches tussentijds schoon gemaakt? | ✓ |  | `=` |
| `ehbo` | fieldset `hidden` | EHBO |  |  |  |
| —`aantalVasteEersteHulpposten` | number | Aantal vaste eerste hulpposten | ✓ |  |  |
| —`aantalMobieleEersteHulpteams` | number | Aantal mobiele eerste hulpteams | ✓ |  |  |
| —`aantalEersteHulpverlenersMetNiveauBasisEersteHulp` | number | Aantal Eerste hulpverleners met niveau 'Basis eerste hu | ✓ |  |  |
| —`aantalEersteHulpverlenersMetNiveauEvenementenEersteHulp` | number | Aantal Eerste hulpverleners met niveau 'Evenementen eer | ✓ |  |  |
| —`aantalZorgprofessionalsMetNiveauBasisZorg` | number | Aantal Zorgprofessionals met niveau 'Basis Zorg' | ✓ |  |  |
| —`aantalZorgprofessionalsMetNiveauSpoedZorg` | number | Aantal Zorgprofessionals met niveau 'Spoed Zorg' | ✓ |  |  |
| —`aantalZorgprofessionalsMetNiveauMedischeZorg` | number | Aantal Zorgprofessionals met niveau 'Medische Zorg' | ✓ |  |  |
| —`aantalZorgprofessionalsMetNiveauSpecialistischeSpoedzorg` | number | Aantal Zorgprofessionals met niveau 'Specialistische Sp | ✓ |  |  |
| —`aantalZorgprofessionalsMetNiveauArtsenSpecialistischeSpoedzorg` | number | Aantal Zorgprofessionals met niveau 'Artsen specialisti | ✓ |  |  |
| —`welkeOrganisatieVerzorgtDeEersteHulp` | textfield | Welke organisatie verzorgt de eerste hulp? | ✓ |  |  |
| `verzorgingVanKinderenJongerDan12Jaar` | fieldset `hidden` | Verzorging van kinderen jonger dan 12 jaar |  |  |  |
| —`voorHoeveelKinderenInTotaalJongerDan12JaarIsVerzorgingNodig` | number | Voor hoeveel kinderen in totaal jonger dan 12 jaar is v | ✓ |  |  |
| —`hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan04Jaar` | number | Hoeveel van het totaal aantal kinderen onder 12 jaar va | ✓ |  |  |
| —`hoeveelVanHetTotaalAantalKinderenOnder12JaarValtInDeLeeftijdscategorieVan512Jaar` | number | Hoeveel van het totaal aantal kinderen onder 12 jaar va | ✓ |  |  |
| —`opWelkeLocatieOfLocatiesVindErOpvangVanDeKinderenOnder12JaarPlaats` | editgrid | Op welke locatie of locaties vind er opvang van de kind | ✓ |  |  |
| ——`locatieVanOpvangVanDeKinderenOnder12Jaar` | map | Locatie van opvang van de kinderen onder 12 jaar | ✓ |  |  |
| `overnachtingen` | fieldset `hidden` | Overnachtingen |  |  |  |
| —`voorHoeveelMensenVerzorgtUOvernachtingenTijdensUwEvenement1` | number | Voor hoeveel mensen verzorgt u overnachtingen tijdens u | ✓ |  |  |
| —`isErSprakeVanOvernachtenDoorPubliekDeelnemers` | radio | Is er sprake van overnachten door publiek/deelnemers? | ✓ |  | `=` |
| —`opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPubliekDeelnemers1` | editgrid `hidden` | Op welke locatie of locaties is er sprake van overnacht | ✓ |  |  |
| ——`locatieVanOvernachtenDoorPubliekDeelnemers` | map | Locatie van overnachten door publiek/deelnemers | ✓ |  |  |
| —`isErSprakeVanOvernachtenDoorPubliekDeelnemers1` | radio | Is er sprake van overnachten door personeel/organisatie | ✓ |  | `=` |
| —`opWelkeLocatieOfLocatiesIsErSprakeVanOvernachtenDoorPersoneelOrganisatie2` | editgrid `hidden` | Op welke locatie of locaties is er sprake van overnacht | ✓ |  |  |
| ——`locatieVanOvernachtenDoorPersoneelOrganisatie1` | map | Locatie van overnachten door personeel/organisatie | ✓ |  |  |
| `bouwsels` | fieldset `hidden` | Bouwsels |  |  |  |
| —`watIsHetMaximaleAantalPersonenDatTijdensUwEvenementXAanwezigIsInEenTentOfAndereBeslotenRuimtePodiumBouwwerkEtc` | number `hidden` | Wat is het maximale aantal personen dat tijdens uw even | ✓ |  |  |
| `beveiligers1` | fieldset `hidden` | Beveiligers |  |  |  |
| —`gegevensBeveiligingsorganisatieOpLocatieEvenementX1` | textarea | Gegevens beveiligingsorganisatie op locatie evenement { | ✓ |  |  |
| —`vergunningnummerBeveiligingsorganisatie1` | number | Vergunningnummer beveiligingsorganisatie | ✓ |  |  |
| —`vestigingsplaatsBeveiligingsorganisatie1` | textfield | Vestigingsplaats beveiligingsorganisatie | ✓ |  |  |
| —`aantalBeveiligersOpLocatieEvenementX1` | number | Aantal beveiligers op locatie evenement {{ watIsDeNaamV | ✓ |  |  |

## Stap 11: Vergunningsaanvraag: voorwerpen

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `voorwerpen` | fieldset `hidden` | Voorwerpen |  |  |  |
| —`verkooppuntenToegangsKaarten` | editgrid `hidden` | Verkooppunten toegangs-kaarten | ✓ |  |  |
| ——`locatieVerkooppuntToegangskaart` | textfield | Locatie | ✓ |  |  |
| ——`aantapVerkoopuntenToegangskaarten` | number | Aantal verkoopunten | ✓ |  |  |
| —`verkooppuntenMuntenEnBonnen` | editgrid `hidden` | Verkooppunten munten en bonnen | ✓ |  |  |
| ——`locatieVerkooppuntMuntenBonnen` | textfield | Locatie | ✓ |  |  |
| ——`aantapVerkoopuntenMuntenBonnen` | number | Aantal verkoopunten | ✓ |  |  |
| —`verkooppuntenCashless` | editgrid `hidden` | Verkooppunten cashless | ✓ |  |  |
| ——`locatieVerkooppuntCashless` | textfield | Locatie | ✓ |  |  |
| ——`aantapVerkoopuntenCashless` | number | Aantal verkoopunten | ✓ |  |  |
| —`Speeltoestellen` | editgrid `hidden` | Speeltoestellen | ✓ |  |  |
| ——`locatiespeeltoestellen` | textfield | Locatie | ✓ |  |  |
| ——`aantalSpeeltoestellen` | number | Aantal speeltoestellen | ✓ |  |  |
| —`brandstofopslag` | editgrid `hidden` | Brandstofopslag | ✓ |  |  |
| ——`locatiebrandstofopslag` | textfield | Locatie | ✓ |  |  |
| ——`aantalbrandstofopslag` | number | Aantal brandstofopslag | ✓ |  |  |
| —`geluidstorens` | editgrid `hidden` | Geluidstorens | ✓ |  |  |
| ——`locatieGeluidstoren` | textfield | Locatie | ✓ |  |  |
| ——`aantalGeluidstoren` | number | Aantal geluidstorens | ✓ |  |  |
| —`Lichtmasten` | editgrid `hidden` | Lichtmasten | ✓ |  |  |
| ——`locatieLichtmast` | textfield | Locatie | ✓ |  |  |
| ——`aantalLichtmast` | number | Aantal lichtmasten | ✓ |  |  |
| —`marktkramen` | editgrid `hidden` | Marktkramen | ✓ |  |  |
| ——`locatieMarktkraam` | textfield | Locatie | ✓ |  |  |
| ——`aantalMarktkraam` | number | Aantal marktkramen | ✓ |  |  |
| —`andersGroup` | editgrid `hidden` | Anders | ✓ |  |  |
| ——`locatieAnders` | textfield | Locatie | ✓ |  |  |
| ——`aantalAnders` | number | Aantal anders | ✓ |  |  |
| `brandgevaarlijkeStoffen` | fieldset `hidden` | Brandgevaarlijke stoffen |  |  |  |
| —`welkeStoffenGebruiktU` | editgrid | Welke stoffen gebruikt u? | ✓ |  |  |
| ——`typeStof` | textfield | Type stof | ✓ |  |  |
| ——`plaatsStof` | textfield | Plaats | ✓ |  |  |
| ——`opslagwijzeStof` | textfield | Opslagwijze | ✓ |  |  |
| ——`toelichtingStof` | textfield | Toelichting | ✓ |  |  |

## Stap 12: Vergunningaanvraag: maatregelen

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `aanpassenLocatieEnOfVerwijderenStraatmeubilair` | fieldset `hidden` | Aanpassen locatie en/of verwijderen straatmeubilair |  |  |  |
| —`geefEenOmschrijvingWelkeAanpassingenOpLocatieEvenementXWaarNodigZijnOfWelkStraatmeubilairUWiltVerwijderenOfAanpassen` | textarea | Geef een omschrijving welke aanpassingen op locatie eve | ✓ |  |  |
| `extraAfval` | fieldset `hidden` | Extra afval |  |  |  |
| —`wieMaaktDeLocatiesEnDeOmgevingDaarvanSchoonEnWanneerGebeurtDat` | editgrid | Wie maakt de locaties en de omgeving daarvan schoon, en | ✓ |  |  |
| ——`locatieAfval` | textfield | Locatie | ✓ |  |  |
| ——`doorWieAfval` | textfield | Door wie? | ✓ |  |  |
| ——`starttijdSchoonmaak` | datetime | Starttijd schoonmaak | ✓ |  |  |
| ——`eindtijdSchoonmaak` | datetime | Eindtijd schoonmaak | ✓ |  |  |
| —`hoeveelExtraAfvalinzamelpuntenGaatUOpLocatieEvenementXPlaatsen` | number | Hoeveel extra afvalinzamelpunten gaat u op locatie Even | ✓ |  |  |
| —`doetUAanAfvalscheidingOpLocatieEvenementX` | radio | Doet u aan afvalscheiding op locatie evenement {{ watIs | ✓ |  | `=` |
| —`voertUDeSchoonmaakZelfUit` | radio | Voert u de schoonmaak zelf uit?  | ✓ |  | `=` |
| —`uKuntHetAfvalplanHierUploadenOfLaterAlsBijlageToevoegen` | file | U kunt het afvalplan hier uploaden of later als bijlage |  | toon als [voertUDeSchoonmaakZelfUit] = [Ja] |  |
| `gemeentelijkeHulpmiddelen` | fieldset | Gemeentelijke hulpmiddelen |  |  |  |
| —`wilUGebruikMakenVanGemeentelijkeHulpmiddelen` | radio | Wil U gebruik maken van gemeentelijke hulpmiddelen? | ✓ |  | `=` |
| —`veldengroep2` | fieldset | Veldengroep |  | toon als [wilUGebruikMakenVanGemeentelijkeHulpmiddelen] = [Ja] |  |
| ——`dranghekken1` | number | Dranghekken |  |  |  |
| ——`wegafzettingen1` | number | Wegafzettingen |  |  |  |
| ——`vlaggen1` | number | Vlaggen |  |  |  |
| ——`vlaggenmasten1` | number | Vlaggenmasten |  |  |  |
| ——`parkeerverbodsborden1` | number | Parkeerverbodsborden |  |  |  |
| ——`bordenGeslotenVerklaring1` | number | Borden gesloten verklaring |  |  |  |
| ——`bordenEenrichtingsweg1` | number | Borden eenrichtingsweg |  |  |  |
| ——`wenstUTegenBetalingStroomAfTeNemenVanDeGemeente1` | radio | Wenst u tegen betaling stroom af te nemen van de gemeen | ✓ |  | `=` |
| ——`geefAanOpWelkeLocatieUStroomWilt1` | textarea | Geef aan op welke locatie u stroom wilt afnemen | ✓ | toon als [wenstUTegenBetalingStroomAfTeNemenVanDeGemeente] = [Ja] |  |

## Stap 13: Vergunningsaanvraag: extra activiteiten

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `welkeShoweffectenBentUVanPlanTeOrganiserenVoorUwEvenement` | textarea `hidden` | Welke showeffecten bent u van plan te organiseren voor  | ✓ |  |  |

## Stap 14: Vergunningaanvraag: overig

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `groteVoertuigen` | fieldset `hidden` | Voorwerpen op de weg |  |  |  |
| —`geefAanOpWelkeDataEnTijdenUDeVoorwerpenWiltPlaatsenOpDeOpenbareWegOfGroteVoertuigenWiltParkerenInDeBuurtVanHetEvenement` | editgrid | Geef aan op welke data en tijden u de voorwerpen wilt p |  |  |  |
| ——`voorwerp` | textfield | Voorwerp | ✓ |  |  |
| ——`positieVanHetVoorwerp` | map | Positie van het voorwerp | ✓ |  |  |
| ——`startTijdstipVoorwerp` | datetime | Start tijdstip | ✓ |  |  |
| ——`eindTijdstipVoorwerp` | datetime | Eind tijdstip | ✓ |  |  |
| —`vulHierEventueelInformatieInOverHetPlaatsenVanVoorwerpenOpDeOpenbareWegOfHetParkerenVanGroteVoertuigen` | textarea | Vul hier eventueel informatie in over het plaatsen van  |  |  |  |
| `verkeersregelaars` | fieldset `hidden` | Verkeersregelaars |  |  |  |
| —`huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie` | radio | Huurt u de verkeersregelaars in bij een daarin gespecia | ✓ |  | `=` |
| —`zijnDeInTeZettenPersonenBeroepsmatigeVerkeersregelaarsOfIsErSprakeVanEvenementenverkeersregelaars` | textarea | Zijn de in te zetten personen beroepsmatige verkeersreg | ✓ | toon als [huurtUDeVerkeersregelaarsInBijEenDaarinGespecialiseerdBedrijfOrganisatie] = [Ja] |  |
| —`hoeveelVerkeersregelaarsWiltUInzetten` | number | Hoeveel verkeersregelaars wilt u inzetten? | ✓ |  |  |
| `vervoersmaatregelen` | fieldset `hidden` | Vervoersmaatregelen |  |  |  |
| —`uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs` | selectboxes | U heeft aangegeven, dat u extra vervoersmaatregelen wil | ✓ |  | `extraParkeerplekkenInrichten=Extra parkeerplekken inrichten`, `extraFietsenstallingenPlaatsen=Extra fietsenstallingen plaatsen`, `inzettenPendelbussen=Inzetten pendelbussen`, `extraOpenbaarVervoerRegelen=Extra openbaar vervoer regelen` | +3 meer |
| —`welkeAndereMaatregelenUWiltNemen` | textarea | Welke andere maatregelen u wilt nemen | ✓ | toon als [uHeeftAangegevenDatUExtraVervoersmaatregelenWiltNemenVoorBezoekersVanUwEvenementXKruisHierAanWatVanToepassingIs] = [anders] |  |
| —`metWelkeOpenbaarVervoermaatschappijenHeeftUExtraAfsprakenGemaaktOverHetOpenbaarVervoer` | textarea | Met welke openbaar vervoermaatschappijen heeft u extra  | ✓ |  |  |
| `promotieEnCommunicatie` | fieldset | Promotie en communicatie |  |  |  |
| —`wiltUPromotieMakenVoorUwEvenement` | radio | Wilt u promotie maken voor uw evenement {{ watIsDeNaamV | ✓ |  | `=` |
| —`opWelkNiveauWiltUPromotieMaken` | radio | Op welk niveau wilt u promotie maken? | ✓ | toon als [wiltUPromotieMakenVoorUwEvenement] = [Ja] | `lokaal=Lokaal`, `regionaal=Regionaal`, `landelijk=Landelijk`, `lnternationaal=lnternationaal` |
| —`hoeWiltUPromotieMakenVoorUwEvenement` | selectboxes | Hoe wilt u promotie maken voor uw evenement {{ watIsDeN | ✓ | toon als [wiltUPromotieMakenVoorUwEvenement] = [Ja] | `driehoeksBorden=(Driehoeks)borden`, `posters=Posters`, `flyers=Flyers`, `spandoeken=Spandoeken` | +2 meer |
| —`opWelkeAndereManierWiltUPromotieMaken` | textarea | Op welke andere manier wilt u promotie maken? | ✓ | toon als [hoeWiltUPromotieMakenVoorUwEvenement] = [anders] |  |
| —`websiteVanUwEvenement` | textfield | Website van uw evenement {{ watIsDeNaamVanHetEvenementV |  |  |  |
| —`facebookVanUwEvenement1` | textfield | Facebookpagina van uw evenement {{ watIsDeNaamVanHetEve |  |  |  |
| —`xPaginaVanUwEvenementWatIsDeNaamVanHetEvenementVergunning` | textfield | X-pagina van uw evenement {{ watIsDeNaamVanHetEvenement |  |  |  |
| `omwonendenCommunicatie` | fieldset | Omwonenden communicatie |  |  |  |
| —`geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX` | radio | Geeft u omwonenden en nabijgelegen bedrijven vooraf inf | ✓ |  | `=` |
| —`opWelkeWijzeInformeertUHen` | textarea | Op welke wijze informeert u hen? | ✓ | toon als [geeftUOmwonendenEnNabijgelegenBedrijvenVoorafInformatieOverUwEvenementX] = [Ja] |  |
| —`wiltUDeInformatieTekstAanDeOmwonendeAlsBijlageToevoegen` | file | Wilt u de informatie-tekst aan de omwonende als bijlage |  |  |  |
| `organisatorischeAchtergrond` | fieldset | Organisatorische achtergrond |  |  |  |
| —`organiseertUUwEvenementXVoorDeEersteKeer` | radio | Organiseert u uw evenement {{ watIsDeNaamVanHetEvenemen | ✓ |  | `=` |
| —`welkeErvaringHeeftDeOrganisatorMetHetOrganiserenVanEvenementen` | textarea | Welke ervaring heeft de organisator met het organiseren |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| —`welkeRelevanteErvaringHeeftHetPersoneelDatDeOrganisatorInhuurtViaIntermediairs` | textarea | Welke relevante ervaring heeft het personeel dat de org |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| —`welkeRelevanteErvaringHeeftHetPersoneelVanOnderAannemersAanWieDeOrganisatorWerkUitbesteedt` | textarea | Welke relevante ervaring heeft het personeel van (onder |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| —`welkeRelevanteErvaringHebbenDeVrijwilligersDieDeOrganisatorInzet` | textarea | Welke relevante ervaring hebben de vrijwilligers die de |  | toon als [organiseertUUwEvenementXVoorDeEersteKeer] = [Nee] |  |
| `huisregelsEnFlankerendeEvenementen` | fieldset | Huisregels en flankerende evenementen |  |  |  |
| —`hanteertUHuisregelsVoorUwEvenementX` | radio | Hanteert u huisregels voor uw evenement {{ watIsDeNaamV | ✓ |  | `=` |
| —`uKuntHierHetHuisregelementUploaden` | file | U kunt hier het huisregelement uploaden |  | toon als [hanteertUHuisregelsVoorUwEvenementX] = [Ja] |  |
| —`organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024` | radio | Organiseert u ook flankerende evenementen (side events) | ✓ |  | `=` |
| —`lichtDeSideEventsToe` | textarea | Licht de side events toe | ✓ | toon als [organiseertUOokFlankerendeEvenementenSideEventsTijdensUwEvenementEvenementNaamSittard2024] = [Ja] |  |
| `verzekering` | fieldset | Verzekering |  |  |  |
| —`heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement` | radio | Heeft u een evenementenverzekering afgesloten voor uw e | ✓ |  | `=` |
| —`uploadDeVerzekeringspolis` | file | Upload de verzekeringspolis |  | toon als [heeftUEenEvenementenverzekeringAfgeslotenVoorUwEvenement] = [Ja] |  |

## Stap 15: Bijlagen

| Veld | Type | Label | Verplicht | Conditie | Opties / Logica |
|------|------|-------|-----------|----------|----------------|
| `veiligheidsplan` | file `hidden` | Veiligheidsplan |  |  |  |
| `bebordingsEnBewegwijzeringsplan` | file `hidden` | U heeft aangegeven, dat u gebruik gaat maken van bewegw |  |  |  |
| `bijlagen1` | file | Overige bijlagen |  |  |  |

## Stap 16: Type aanvraag

*Geen velden.*

---

## Stap-visibility Regels

**64 regels** bepalen of een stap getoond of verborgen wordt.

| Type | Trigger (JsonLogic) |
|------|---------------------|
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "waarvoorWiltUEventloketGebruiken"}, "vooraankondiging"]}` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"==": [{"var": "wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer"}, "Nee` |
| step-not-applicable | `{"or": [{"==": [{"var": "isHetAantalAanwezigenBijUwEvenementMinderDanSdf"}, "Nee"]}, {"==": [{"var":` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A1"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A3"}, true]}` |
| step-applicable | `{"==": [{"var": "watVoorBouwselsPlaatsUOpDeLocaties.A54"}, true]}` |
| step-applicable | `{"==": [{"var": "watVoorBouwselsPlaatsUOpDeLocaties.A55"}, true]}` |
| step-applicable | `{"==": [{"var": "watVoorBouwselsPlaatsUOpDeLocaties.A56"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A4"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A5"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A7"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A8"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A10"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX.A11"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A12"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A13"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A14"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A15"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A17"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A18"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A19"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A20"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A21"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A23"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A24"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A25"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A26"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A27"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A28"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A29"}, true]}` |
| step-applicable | `{"==": [{"var": "welkeVoorwerpenGaatUPlaatsenBijUwEvenementX.A30"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A32"}, tru` |
| step-applicable | `{"==": [{"var": "kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX.A33"}, tru` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A37"}, t` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A38"}, t` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A39"}, t` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A40"}, t` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A41"}, t` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A42"}, t` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A43"}, t` |
| step-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A44"}, t` |
| step-not-applicable | `{"==": [{"var": "welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX.A45"}, t` |
| step-applicable | `{"==": [{"var": "welkeVoorzieningenZijnAanwezigBijUwEvenement.A53"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A48"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A49"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A51"}, true]}` |
| step-applicable | `{"==": [{"var": "kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX.A52"}, true]}` |
| step-not-applicable | `{"==": [{"var": "kruisAanWatVanToepassingIsVoorUwEvenementX."}, true]}` |
# Stap 8: Risicoscan

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 3/3 gedekt.

## Risico-classificatie A/B/C op basis van 14 antwoorden

De Risicoscan kent elke antwoordoptie een numerieke score toe. De som van de 14 scores bepaalt welke classificatie het evenement krijgt: **A** (laag risico, som ≤ 6), **B** (middelhoog, som ≤ 9), of **C** (hoog risico, som > 9). Deze classificatie stuurt de rest van de aanvraag — welke extra vragen er gesteld worden en hoe grondig de behandelaar toetst — dus moet de optelling exact kloppen.

### ✅ Laag risico (A): minimale-risico-antwoorden bij elke vraag

Als een organisator de minst-risicovolle antwoorden geeft op alle 14 vragen (kleine doelgroep, bekende locatie, overdag, geen alcohol/drugs, etc.), dan moet het evenement worden geclassificeerd als A — laag risico. Dat betekent dat de vervolgvragen beperkt blijven en de behandelaar een lichte toets kan uitvoeren.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de aantrekkingskracht van het evenement?" = "0.5" (_Wijk of buurt_)
- Veld "Wat is de belangrijkste leeftijdscategorie van de doelgroep?" = "0.25" (_30-70 jaar_)
- Veld "Is er sprake van aanwezigheid van politieke aandacht en/of mediageniekheid?" = "0" (_Nee_)
- Veld "Is een deel van de doelgroep verminderd zelfredzaam?" = "0.25" (_Voldoende zelfredzaam_)
- Veld "Is er sprake van aanwezigheid van risicovolle activiteiten?" = "0" (_Nee_)
- Veld "Wat is het grootste deel van de samenstelling van de doelgroep?" = "0.5" (_Alleen toeschouwers_)
- Veld "Is er sprake van overnachten?" = "0" (_Er wordt niet overnacht of er wordt overnacht op een daartoe bestemde locatie_)
- Veld "Is er gebruik van alcohol en drugs?" = "0" (_Niet aanwezig_)
- Veld "Wat is het aantal gelijktijdig aanwezig personen?" = "0" (_Minder dan 150_)
- Veld "In welk seizoen vindt het evenement plaats?" = "0.25" (_Lente of herfst_)
- Veld "In welke locatie vindt het evenement plaats?" = "0.25" (_In een gebouw, als een daartoe ingerichte evenementenlocatie_)
- Veld "Op welk soort ondergrond vindt het evenement plaats?" = "0.25" (_Verharde ondergrond_)
- Veld "Wat is de tijdsduur van het evenement?" = "0" (_Minder dan 3 uur tijdens daguren_)
- Veld "Welke beschikbaarheid van aan- en afvoerwegen is van toepassing?" = "0.5" (_Redelijke aan- en afvoerwegen_)

**Dan verwachten we:**
- Veld `risicoClassificatie` = "A"

### ✅ Middelhoog risico (B): gemeentelijk evenement met alcohol en risicovolle activiteiten

Een gemeentelijk evenement met politieke aandacht, risicovolle activiteiten, alcoholgebruik en 150-2000 bezoekers zit in het midden van de risico-range. De som ligt tussen 6 en 9, dus classificatie B — middelhoog risico. De behandelaar stelt dan aanvullende vragen over maatregelen.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de aantrekkingskracht van het evenement?" = "1.5" (_Gemeentelijk_)
- Veld "Wat is de belangrijkste leeftijdscategorie van de doelgroep?" = "0.25" (_30-70 jaar_)
- Veld "Is er sprake van aanwezigheid van politieke aandacht en/of mediageniekheid?" = "1" (_Ja_)
- Veld "Is een deel van de doelgroep verminderd zelfredzaam?" = "0.25" (_Voldoende zelfredzaam_)
- Veld "Is er sprake van aanwezigheid van risicovolle activiteiten?" = "1" (_Ja_)
- Veld "Wat is het grootste deel van de samenstelling van de doelgroep?" = "0.5" (_Alleen toeschouwers_)
- Veld "Is er sprake van overnachten?" = "0" (_Er wordt niet overnacht of er wordt overnacht op een daartoe bestemde locatie_)
- Veld "Is er gebruik van alcohol en drugs?" = "1" (_Aanwezig, met risicoverwachting_)
- Veld "Wat is het aantal gelijktijdig aanwezig personen?" = "0.5" (_2.000 - 5.000_)
- Veld "In welk seizoen vindt het evenement plaats?" = "0.25" (_Lente of herfst_)
- Veld "In welke locatie vindt het evenement plaats?" = "0.25" (_In een gebouw, als een daartoe ingerichte evenementenlocatie_)
- Veld "Op welk soort ondergrond vindt het evenement plaats?" = "0.25" (_Verharde ondergrond_)
- Veld "Wat is de tijdsduur van het evenement?" = "0" (_Minder dan 3 uur tijdens daguren_)
- Veld "Welke beschikbaarheid van aan- en afvoerwegen is van toepassing?" = "0.5" (_Redelijke aan- en afvoerwegen_)

**Dan verwachten we:**
- Veld `risicoClassificatie` = "B"

### ✅ Hoog risico (C): grote doelgroep met verminderd-zelfredzame bezoekers en overnachting

Wanneer er meerdere risico-factoren samenkomen — een grote doelgroep met verminderd zelfredzame bezoekers, overnachting buiten een daarvoor ingerichte locatie, en slechte aan- en afvoerwegen — tilt de som het evenement boven de drempel van 9. Classificatie C betekent dat het een hoog-risico evenement is en de volle behandelaar-toets met maximum aan vervolgvragen in werking treedt.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Wat is de aantrekkingskracht van het evenement?" = "1.5" (_Gemeentelijk_)
- Veld "Wat is de belangrijkste leeftijdscategorie van de doelgroep?" = "0.25" (_30-70 jaar_)
- Veld "Is er sprake van aanwezigheid van politieke aandacht en/of mediageniekheid?" = "1" (_Ja_)
- Veld "Is een deel van de doelgroep verminderd zelfredzaam?" = "1" (_Niet zelfredzaam_)
- Veld "Is er sprake van aanwezigheid van risicovolle activiteiten?" = "1" (_Ja_)
- Veld "Wat is het grootste deel van de samenstelling van de doelgroep?" = "1" (_Alleen deelnemers_)
- Veld "Is er sprake van overnachten?" = "1" (_Er wordt overnacht op een niet daartoe bestemde locatie_)
- Veld "Is er gebruik van alcohol en drugs?" = "1" (_Aanwezig, met risicoverwachting_)
- Veld "Wat is het aantal gelijktijdig aanwezig personen?" = "0.5" (_2.000 - 5.000_)
- Veld "In welk seizoen vindt het evenement plaats?" = "0.25" (_Lente of herfst_)
- Veld "In welke locatie vindt het evenement plaats?" = "0.75" (_In de open lucht, op een niet daartoe ingericht evenemententerrein_)
- Veld "Op welk soort ondergrond vindt het evenement plaats?" = "0.25" (_Verharde ondergrond_)
- Veld "Wat is de tijdsduur van het evenement?" = "0" (_Minder dan 3 uur tijdens daguren_)
- Veld "Welke beschikbaarheid van aan- en afvoerwegen is van toepassing?" = "1" (_Geen aan- en afvoerwegen_)

**Dan verwachten we:**
- Veld `risicoClassificatie` = "C"

# Stap 14: Vergunningsaanvraag: extra activiteiten

_[← terug naar de index](../gedragsspecificatie.md)_

**Samenvatting:** ✅ Alle scenarios op deze pagina slagen — 2/2 gedekt.

## Inhoud-blokken per bijzondere activiteit

Voor bijzondere activiteiten als ballonnen oplaten of een lasershow toont het formulier specifieke inhoud-blokken met regelgeving. Elke aanvinken activeert ook de pagina "extra activiteiten" in de sidebar.

### ✅ Ballonnen oplaten (A37) → contentBalon-blok + stap actief

Als de organisator aangeeft ballonnen op te laten (activiteit A37), toont het formulier een inhoud-blok met de regelgeving rond ballon-oplatingen. De pagina "extra activiteiten" wordt daarmee ook van toepassing.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke van de onderstaande activiteiten vinden verder nog plaats tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Ballonnen oplaten" aangevinkt

**Dan verwachten we:**
- Veld "Content" _(op Stap 14: Vergunningsaanvraag: extra activiteiten)_ wordt **zichtbaar**
- Stap 14: Vergunningsaanvraag: extra activiteiten wordt **van toepassing** (getoond in sidebar)

### ✅ Lasershow (A38) → contentLasershow-blok + stap actief

Bij een lasershow (activiteit A38) is er specifieke regelgeving. Het formulier toont een inhoud-blok daarover en markeert deze pagina als van toepassing.

**Bewijs:** 🟢 Sterk — beide runtimes bevestigen elke check  ·  **PHP (Filament):** ✅  ·  **JS-spec ([json-logic-js](https://github.com/jwadhams/json-logic-js)):** ✅

**Gegeven (wat de gebruiker heeft ingevuld of wat bekend is):**
- Veld "Welke van de onderstaande activiteiten vinden verder nog plaats tijdens uw evenement {{ watIsDeNaamVanHetEvenementVergunning }}?" — "Lasershow" aangevinkt

**Dan verwachten we:**
- Veld "Content" _(op Stap 14: Vergunningsaanvraag: extra activiteiten)_ wordt **zichtbaar**
- Stap 14: Vergunningsaanvraag: extra activiteiten wordt **van toepassing** (getoond in sidebar)

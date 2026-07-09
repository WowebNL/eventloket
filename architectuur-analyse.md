# Architectuuranalyse Eventloket

Datum: 3 juli 2026
Scope: volledige codebase op branch `next/v1.2`
Methode: vijf parallelle deelanalyses (structuur, design patterns, duplicatie, kwaliteit en volwassenheid, datamodel en integratie), daarna handmatige verificatie van tegenstrijdige bevindingen.

---

## 1. Managementsamenvatting

Eventloket is een bovengemiddeld doordachte en volwassen Laravel-applicatie. De sterkste onderdelen zijn de multi-ZGW-connectielaag, de asynchrone job-architectuur rond het indienen van aanvragen, de release-discipline en de documentatie. De zwakste onderdelen zijn geconcentreerd op vier plekken: het `Zaak`-model dat te veel verantwoordelijkheden draagt (inclusief netwerk-I/O in accessors), een aantal grote duplicatieclusters in de Filament-laag, een inconsistente Actions-laag, en het ontbreken van een reconciliatiemechanisme tussen de lokale database en OpenZaak.

| Dimensie | Oordeel |
|---|---|
| Structuur en mappenindeling | Goed |
| Filament multi-panel architectuur | Goed, met inconsistenties |
| Design patterns en Laravel-conventies | Goed, met uitzonderingen (Actions zwak) |
| DRY | Matig tot goed (fundament aanwezig, niet consequent doorgetrokken) |
| Datamodel | Matig (god-model, STI-risico's) |
| ZGW-integratie | Goed tot zeer goed |
| Teststrategie | Volwassen (met blinde vlekken) |
| Tooling en quality gates | Volwassen (geen geautomatiseerde dependency-updates) |
| Foutafhandeling en observability | Gemiddeld tot volwassen |
| Security-signalen | Volwassen |
| Versiebeheer en releases | Voorbeeldig |
| Documentatie | Voorbeeldig |

Volwassenheidsoordeel als geheel: **volwassen productieapplicatie** met bewuste, goed gedocumenteerde trade-offs. De technische schuld is bekend terrein (geen verrassende rommel), maar zit op een paar plekken diep genoeg dat gericht refactoren loont.

De tien belangrijkste aanbevelingen staan in hoofdstuk 9. De drie met de hoogste prioriteit:

1. Haal de ZGW-fetch- en cachelogica uit het `Zaak`-model (netwerk-I/O in accessors is het grootste architectuurrisico).
2. Voeg een periodiek reconciliatiemechanisme toe tussen `reference_data` en OpenZaak (nu leunt consistentie volledig op webhooks).
3. Werk de vier grootste duplicatieclusters weg met de patronen die al in de codebase bestaan (`AbstractAcceptInvite` bewijst dat het team dit beheerst).

---

## 2. Feitelijke basis

### 2.1 Omvang en stack

| Metriek | Waarde |
|---|---|
| PHP-bestanden in `app/` | 536 (± 48.500 regels) |
| Testbestanden | 217 (195 Feature, 19 Unit, plus 10 Playwright-specs) |
| Migrations | 100 |
| Modellen | ± 38 (26 in `app/Models/`, 9 User-subklassen, 2 Thread-subklassen) |
| Policies | 31 |
| Jobs | 29 |
| Commits | 1190, strikt PR-gebaseerd |

Stack volgens `composer.json`: PHP ^8.4, **Laravel ^13.0**, **Filament ^5.0**, Passport ^13, Horizon ^5.47, Pest ^4, Larastan ^3 (level 5), Sentry, spatie/laravel-activitylog, spatie/laravel-settings-plugin, brick/geo, eigen packages `woweb/laravel-zgw-client` ^1.2 en `woweb/openzaak` op `dev-main`.

### 2.2 Documentatie versus werkelijkheid (bevinding op zichzelf)

Drie claims in de projectdocumentatie kloppen niet met de code:

1. **Versies.** `CLAUDE.md` noemt Laravel 12 en Filament 4, `composer.json` eist Laravel ^13.0 en Filament ^5.0. De README is wel correct. Documentatie is niet meegegroeid met de upgrade.
2. **PostGIS bestaat niet.** `CLAUDE.md` claimt "PostgreSQL 17+ with PostGIS (spatial queries)". In werkelijkheid zijn `municipalities.geometry` en `locations.geometry` gewone `jsonb`-kolommen (GeoJSON), en gebeurt alle ruimtelijke logica in PHP via `brick/geo` (zie 6.5).
3. **Database-pariteit.** Productie en lokale tests draaien op PostgreSQL (`phpunit.xml` regel 29), maar CI draait de testsuite op **MySQL 8** (`.github/workflows/pest.yml` regel 12). De 20 migraties met `jsonb` en queries als `whereJsonLength` gedragen zich op MySQL anders dan op Postgres. Tests bewijzen dus niet wat productie doet.

---

## 3. Structuur en algehele architectuur

### 3.1 Mappenstructuur

De top-level indeling volgt Laravel-conventies, met twee domein-"sub-frameworks": de publieke aanvraagwizard (`app/EventForm/`, 58 bestanden) en de backoffice (`app/Filament/`, 223 bestanden). De indeling is grotendeels consistent en leesbaar. Drie inconsistenties:

- **Services versus ValueObjects is een vage grens.** DTO's als `app/Services/Notificaties/AbonnementCheckResult.php`, `IssuedWebhookToken.php` en `app/Services/Zgw/ZaaktypeVersion.php`, `StatusPhase.php` zijn conceptueel value objects maar staan onder Services, terwijl `app/ValueObjects/` wel bestaat en netjes is opgezet.
- **ZGW-schrijfwerk is verdeeld over drie concepten** zonder harde regel: Jobs (`app/Jobs/Zaak/AddResultaatZGW.php`), Actions (`app/Actions/UpdateZaakReferenceData.php`) en Services (`app/Services/Zgw/MappedZaaktypeSync.php`).
- **Legacy-residu** van de verwijderde Open Forms-integratie: `app/Normalizers/OpenFormsNormalizer.php` (nog geïmporteerd door EventForm), een vrijwel lege `app/Casts/`, en verklarende comments in `routes/api.php` regels 8 tot 13.

### 3.2 Filament multi-panel opzet

Vier panels met eigen providers (`app/Providers/Filament/`), elk met eigen tenant-model: Organiser (Organisation), Municipality (Municipality), Advisor (Advisory), Admin (geen tenant). Het sharing-mechanisme is de sterkste keuze in deze laag: elk panel doet een dubbele `discoverResources`, voor de eigen namespace én voor `App\Filament\Shared\Resources` (bijvoorbeeld `OrganiserPanelProvider.php` regels 49 en 50).

Kanttekening: er bestaan twee sharing-strategieën naast elkaar voor dezelfde entiteit. Municipality, Admin en Advisor erven `ZaakResource` van de Shared base (Municipality-variant is 14 regels), maar de Organiser-variant (`app/Filament/Organiser/Resources/Zaken/ZaakResource.php`, 69 regels) erft van `Resource` en delegeert los naar `ZakenTable::configure()`. Die keuze is per resource ad hoc gemaakt en verhoogt de kans dat een wijziging in één panel wordt vergeten.

### 3.3 De EventForm-module

`app/EventForm/` is intern goed opgezet met logische sub-namespaces (Schema, State, Submit, Persistence, Validation, Template, Reporting). De orchestrator `SubmitEventForm.php` is voorbeeldig gedocumenteerd (docblock regels 29 tot 54 beschrijft de sync/async-hybride en de transactiegrens).

De module is echter **niet echt geïsoleerd**: 16 imports van `App\Models\Organisation`, directe dispatch van 9 concrete jobs uit `App\Jobs\`, en zelfs een import van `App\Filament\Organiser\Pages\Calendar`. Er zit geen interfacelaag tussen EventForm en de rest. Pragmatisch verdedigbaar, maar de modulegrens lekt.

### 3.4 Routing en API

- `routes/web.php` (112 regels) is mager en netjes: signed invite-routes, documentdownloads, twee test-only endpoints achter een environment-check.
- `routes/api.php` bevat nog één endpoint: de ZGW-webhook, beschermd met Passport client-credentials plus scope `notifications:receive` en een logging-middleware die vóór auth draait. Er is geen API-versioning, maar met één webhook-endpoint is dat verdedigbaar.
- De scheduler (`routes/console.php`) regelt Horizon-snapshots, wekelijkse Kadaster-sync, dagelijkse cleanups en webhook-token-rotatie. Ordelijk.

### 3.5 Grootste bestanden

De meeste grote bestanden zijn declaratieve Filament- of wizardschema's (gerechtvaardigd groot). Vier zijn echte code smells:

| Bestand | Regels | Probleem |
|---|---|---|
| `app/EventForm/State/FormFieldVisibility.php` | 1317 | 80 methoden, hardcoded veld-naar-bool-mapping. Configuratie vermomd als code, kandidaat voor data-driven aanpak. |
| `app/Filament/Organiser/Pages/EventFormPage.php` | 747 | God-page: mount, hydrate, submit, draft-persistence en fetch-orkestratie in één Livewire-klasse. |
| `app/Models/Zaak.php` | 602 | God-model, zie hoofdstuk 6. |
| `app/Filament/Shared/Resources/Zaken/Pages/ViewZaak.php` | 475 | `getHeaderActions()` beslaat regels 83 tot 464, bijna 380 regels in één methode. |

---

## 4. Design patterns en Laravel-conventies

### 4.1 Wat goed is

- **Enums (goed).** Alle domein-enums zijn backed string enums met `HasLabel` en vertaalde labels, en dragen gedrag waar zinvol (`AdviceStatus::activeStatuses()`). Enums als volwaardige domeinobjecten.
- **Notifications (goed).** `app/Notifications/BaseNotification.php` centraliseert queueing, notificatievoorkeuren (`RespectsNotificationPreferences`) en per-connectie onderdrukking. Volwassen en uitbreidbaar.
- **Value objects (goed).** Eigen implementatie met `final readonly class`, en `ZaakReferenceData` implementeert `Castable` met een inline cast (regels 108 tot 149), precies het idiomatische patroon. Kanttekening: variadic `...$otherParams` als vergaarbak ondermijnt de gesloten vorm van een VO.
- **Observers en scopes via attributen** (`#[ObservedBy]`, `#[ScopedBy]`), de moderne conventie.
- **Services (goed).** `ZgwConnectionResolver` is een voorbeeldig ontworpen singleton met per-request memoization en uitstekende docblocks. Terecht géén repositories, en terecht weinig FormRequests en API Resources (dit is een Filament-app, validatie zit in de schemas).
- **Traits (goed).** Slechts vijf, elk met één verantwoordelijkheid. Geen grab-bags.

### 4.2 Wat zwak is

- **Actions (zwak, meest inconsistente laag).** Geen gedeeld contract: `UpdateZaakReferenceData` gebruikt `public static function handle()`, `SyncGeometry` een instance met `execute()`, geen enkele is invokable. Erger: `SyncGeometry.php` regels 11 tot 18 accepteert `?KadasterService` in de constructor maar overschrijft die met `new KadasterService`, waardoor dependency injection en dus mocken onmogelijk is. `KadasterService.php` regels 10 tot 13 heeft hetzelfde probleem (config-parameter die direct wordt overschreven).
- **Job-reliability is ongelijk verdeeld.** `ZaaktypeNotificationReceived` is textbook (ShouldBeUnique, uniqueFor 600, tries 3, backoff 60, onderscheid transient versus gone). Maar de overige ± 28 jobs, inclusief de hele ZGW-schrijfketen, hebben géén expliciete `$tries` of `$backoff` en zijn niet aantoonbaar idempotent. Een dubbele POST van een resultaat of status naar ZGW kan schadelijk zijn. Positief tegenwicht: de client retryt bewust nooit POST-requests, en `NotifySlackOfFailedJob` vangt alle failures centraal af.
- **Autorisatie is verspreid.** `ZaakPolicy::viewAny` geeft voor elke rol `true`; de echte filtering zit in `view()`, global scopes en tenant-checks. Functioneel dekkend, maar je kunt nergens in één blik zien wat een rol mag. De policy leunt bovendien op meerdere `@phpstan-ignore` annotaties omdat de STI-typing en de policy-signatuur (`User $user`) wringen.
- **Twee UUID-strategieën naast elkaar.** `User` gebruikt een eigen `HasUuid`-trait (aparte kolom naast integer PK), `Zaak` gebruikt Laravels `HasUuids`. De eigen trait bevat ook een typefout in de methodenaam (`idByUuuid`).

---

## 5. DRY-analyse: duplicatiekaart

Positief kader vooraf: het fundament voor hergebruik bestaat en wordt beheerst. `AbstractAcceptInvite` (171 regels logica, vier concrete klassen van ± 50 regels), de `CalendarWidget`-base, de gedeelde `ZakenTable`/`ZaakInfolist` en het `Thread`-model met STI-subklassen zijn goede voorbeelden. De duplicatie hieronder is dus geen kwestie van onkunde maar van niet consequent doorgetrokken patronen.

### 5.1 Top-10 duplicatieclusters (op impact)

| # | Cluster | Omvang en overlap | Refactorvoorstel |
|---|---|---|---|
| 1 | **AdviceThread versus OrganiserThread Filament-stack**: ± 13 parallelle bestanden (Resources, Pages, Tables, Schemas) plus 3 inbox-widgets plus 2 notification-paren | 60 tot 70% overlap; Organiser-varianten zijn de Advice-varianten minus advies-velden | Abstracte ThreadResource en inbox-widget-base; threadsamenvatting en toMail/toDatabase-skelet naar `BaseNotification` |
| 2 | **Settings-resources dubbel in Admin en Municipality**: `AdvisoryResource` (plus 2 RelationManagers) en `ZgwRequestLogResource` bestaan twee keer los | Municipality's `UsersRelationManager` (137 regels) herschrijft zelfs de invite-logica inline terwijl er al een gedeelde `AdvisorUserInviteAction` bestaat | Naar `Shared/Resources` verplaatsen, zoals bij `MunicipalityVariableResource` al correct is gedaan |
| 3 | **ZGW-connectiepatroon in jobs**: `Zgw::connection(...)` komt 59 keer voor in ± 28 bestanden; de hele `Add*ZGW`-jobfamilie deelt hetzelfde skelet | ± 40% boilerplate per korte job, inclusief herhaalde `zgw_zaak_url`-guard | Abstracte `ZgwZaakJob` of `ResolvesZgwConnection`-trait, meteen de plek voor uniforme tries/backoff (zie 4.2) |
| 4 | **Pending-invites-widgets**: 6 near-identieke TableWidgets (± 300 regels), waarvan er twee vrijwel letterlijk hetzelfde zijn | ± 90% overlap, alleen query en translation-keys verschillen | Eén abstracte base met `getInvitesQuery()` en translation-namespace |
| 5 | **Organiser ZaakResource breekt uit het Shared-patroon** (zie 3.2) | Herhaalt resource-skeleton plus eigen infolist en pages | Base laten erven zoals de andere drie panels |
| 6 | **Invite-actions**: 4 keer hetzelfde create-plus-mail-plus-notify-blok met duplicate-email-validatie | 60 tot 70% overlap | Geparametriseerde `InviteAction::for(...)` of trait |
| 7 | **Invite-mails plus blade-templates**: 4 mailables en 4 blades | ± 90% identiek (alleen translation-key en één with-parameter verschillen) | Eén generieke `InviteMail` en één `mail.invite` blade |
| 8 | **Invite-modellen**: `AdminInvite`, `OrganisationInvite`, `MunicipalityInvite`, `AdvisoryInvite` | ± 80% gedeeld skelet (fillable, hidden, Expirable) | Abstract `Invite`-model, subklassen alleen relatie plus cast |
| 9 | **Herhaalde rol-arrays en helpers**: `[Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Coordinator, Role::Reviewer]` letterlijk op minstens 4 plekken; dubbele `getViewUrlForUser` (`Thread.php:175`, `Message.php:56`); identieke `getFilterSchema()` in Admin- en Advisor-calendar; datumformattering wisselt tussen hardcoded en `config('app.datetime_format')` | Verspreid | Statische helpers op de `Role`-enum (bijv. `Role::municipalityStaff()`), base-defaults, één datetime-configstandaard |
| 10 | **Vertaling omzeild**: 285 hardcoded Nederlandse labels (`->label('...')`, modalHeadings, placeholders) zonder `__()`; `lang/nl` heeft 96 bestanden, `lang/en` slechts 10 | Vooral in `app/EventForm/Schema/Steps/` | Consolidatie naar translation-keys; bepaal eerst of EN-ondersteuning überhaupt een doel is |

### 5.2 Conclusie DRY

De duplicatie is goed afgebakend en vrijwel volledig geconcentreerd in de Filament-laag rond vier domeinen: threads, invites, settings-resources en de ZGW-jobboilerplate. Clusters 1 tot en met 4 wegwerken raakt naar schatting 50 tot 60 bestanden en verwijdert vermoedelijk 1.500 tot 2.000 regels, met de bestaande huispatronen als sjabloon.

---

## 6. Datamodel en ZGW-integratie

### 6.1 ER-model in het kort

`Zaak` (tabel `zaken`) is de kern: belongsTo `Zaaktype` (dat belongsTo `Municipality`), `Organisation`, `OrganiserUser` en `MunicipalityUser` (behandelaar), self-relatie hoofdzaak/deelzaken voor doorkomsten, hasMany `AdviceThread` en `OrganiserThread` (STI op tabel `threads`) met `Message` en unread-tracking via pivot. Daarnaast: vier invite-modellen (Expirable), `Advisory` en `Organisation` met pivot-rollen, ZGW-configuratie (`MunicipalityZgwConnection` 1:1, `MunicipalityZaaktypeMapping`, `ZgwAbonnement`, `ZgwRequestLog`) en `Application` als Passport-client-eigenaar, bewust losgekoppeld van `User`.

### 6.2 Zaak als god-model (belangrijkste bevinding)

`app/Models/Zaak.php` (602 regels, ± 30 methodes) combineert vijf verantwoordelijkheden:

1. **ZGW-read-model met live HTTP in accessors**: `openzaak` (regels 346 tot 366), `documenten`, `besluiten`, `statustype` doen netwerk-calls met `Cache::rememberForever`. Elke attribuut-toegang kan een timeout of exceptie triggeren, midden in een Blade-view of notificatie-opbouw. Dit is het grootste architectuurrisico van het datamodel.
2. Autorisatie- en zichtbaarheidslogica (`filterDocumentenForRole`, `besluitIsPubliceerbaar`).
3. Feature-flags per connectie (`behandelaarCanChangeStatus`, `showsTab`).
4. Notificatie-routing (`relatedUsers`, `getMunicipalityHandlers`).
5. Presentatie (`toCalendarEvent`, `statusColor`).

Een `Services/Zgw/ZaakReadModel` bestaat al, maar het model omzeilt hem deels. De splitsing is halverwege blijven steken.

### 6.3 Single-table inheritance: goed concept, risicovolle randen

De STI-hydratie via `newFromBuilder()` (`User.php` regels 171 tot 186, exhaustieve match op `Role`) is netjes. De risico's zitten in het gebruik:

- **`ZaakEventScope` dwingt een rol-afhankelijke `select()` plus eager loads af in een global scope** (`app/Models/Scopes/ZaakEventScope.php` regels 15 tot 27). In queue- of console-context (geen auth-user) levert dat partial models op met stil `null`-gedrag. Bovendien registreert `Event.php` de scope dubbel (attribuut én `booted()`).
- **Base `User` versus subklasse door elkaar in relaties**: `Zaak::organiserUser()` levert een gescopede `OrganiserUser`, maar `Thread::createdBy()` en `Message::user()` leveren base `User`. `Thread::getViewUrlForUser()` gooit een exceptie bij een onbekende klasse.
- **Global-scope-valkuil bij uniciteit**: `OrganiserUser::where('email', ...)` mist gebruikers met een andere rol, `User::where('email', ...)` niet. Bron van subtiele bugs bij invites en deduplicatie.
- Geen expliciete morph-alias op `Zaak`/`Event`, waardoor polymorfe registraties (activitylog) `App\Models\Event` en `App\Models\Zaak` door elkaar kunnen opslaan.

### 6.4 ZGW-flow en consistentie

De submit-flow (synchrone ZGW-zaakcreatie plus lokale rij in één transactie, daarna een `Bus::chain` van negen verrijkingsjobs) is goed ontworpen en uitzonderlijk goed gedocumenteerd, inclusief bewuste acceptatie van weeszaken bij OpenZaak en het hashen van BSN/KvK als laatste chain-stap.

Drie risico's:

1. **Consistentie leunt volledig op webhooks.** `reference_data` (het gematerialiseerde read-model in jsonb) wordt alleen bijgewerkt via notificaties. Gaat een webhook verloren, dan divergeert de lokale weergave stil van OpenZaak, en de `rememberForever`-caches versterken dat: er is geen TTL en geen periodieke reconciliatie.
2. **Chain-faal laat partial state achter.** Een blijvend gefaalde job stopt de keten, inclusief `HashIdentifyingAttributes`, waardoor plain BSN/KvK langer dan bedoeld in `form_state_snapshot` blijft staan. Er is Slack-alerting op job-failures, maar geen bewaking op "keten halverwege blijven hangen".
3. **`main` is een single point of failure.** Elke null-, ongeldige of niet-geactiveerde connectie-resolutie valt stil terug op `main`, inclusief bij een te zwakke gemeente-secret (alleen een `Log::warning`). Host-ambiguïteit bij webhooks valt eveneens terug op `main`.

Daartegenover: de `woweb/laravel-zgw-client` is degelijk (afgedwongen timeouts op elke call, retries alleen op idempotente methodes, Retry-After-bewuste backoff, audit-events), en de `ZgwConnectionResolver` met runtime/management-splitsing, host-indexing en observer-gebaseerde cache-invalidatie is het architectonisch sterkste onderdeel van de applicatie. Wel is `woweb/openzaak` op `dev-main` gepind (een ongetagde branch als productie-dependency) en refereert de resolver nog aan het legacy-package.

### 6.5 Geospatial

Er is geen PostGIS. `CheckIntersects` laadt álle gemeenten met geometrie in geheugen en doet per check één databaseroundtrip per gemeente via brick/geo's PdoEngine (bij lijnen maal drie: lijn, startpunt, eindpunt). Niet indexeerbaar en slecht schaalbaar. Met echte PostGIS plus spatial index zou dit één query zijn. Daarnaast is de behandeling inconsistent: `Municipality.geometry` gebruikt de `AsGeoJson`-cast, `Location.geometry` een kale `json`-cast. De PDOK- en Kadaster-services hebben, anders dan de ZGW-client, geen timeouts of retries op hun externe calls.

### 6.6 Schema-observaties

- Unique-constraints op `public_id` en `zgw_zaak_url` zijn goed gekozen (webhook-lookups gebruiken ze).
- Ontbrekend: samengestelde index op de veelgebruikte `zaaktypen`-lookup (`municipality_id` plus `identificatie`), en een index passend bij de activitylog-query in `Zaak::organiserSubmittedDocumentUuids` (filtert op vier kolommen, potentieel traag op een groeiende log).
- `StatusResultaatColor` koppelt op statusnaam-string in plaats van een echte sleutel; een hernoeming in ZGW breekt kleuren stil.
- `config/database.php` default is sqlite terwijl het schema Postgres-specifieke `jsonb` gebruikt: latent breukpunt voor nieuwe omgevingen, naast de MySQL-CI-mismatch uit 2.2.

---

## 7. Kwaliteit en volwassenheid

### 7.1 Tests: volwassen, met blinde vlekken

Ruwweg 1.481 testcases. Sterk: 34 Filament-testbestanden op 30 resources, een eigen `ZgwHttpFake`, EventForm breed gedekt (State, Submit, Schema, Persistence, Equivalence), `Http::fake` in 47 bestanden. Zwak:

- **Policy-dekking is mager**: 7 testbestanden op 31 policies, terwijl autorisatie juist verspreid is over policies, scopes en tenant-checks (zie 4.2). Dit is de gevaarlijkste blinde vlek.
- 212 voorkomens van `assertOk`/`assertSuccessful` wijzen op een fors aandeel smoke-tests zonder diepere asserties.
- Acht services aantoonbaar ongetest, waaronder `KadasterService` en `MappedZaaktypeSync`.
- `tests/Pest.php` bevat nog de default scaffolding-stubs en `tests/TestCase.php` is leeg.
- CI test op MySQL, productie draait Postgres (zie 2.2).

### 7.2 Tooling en gates: volwassen, één gat

PHPStan level 5 zonder baseline (goed: geen verstopte schuld, wel 50 inline ignores), Pint en Pest in CI op elke PR, pre-commit hook met Pint, PHPStan, Rector dry-run en Pest, GitHub Actions SHA-pinned (voorbeeldige supply-chain-hardening), release-drafter en changelog-automatisering. Het gat: **geen Dependabot of Renovate**, dependency-updates gebeuren handmatig. In Rector staan 9 regels geskipt met een TODO die naar een "dedicated branch" verwijst die er nog niet is. In de pre-commit fallback zonder Docker is PHPStan uitgecommentarieerd.

### 7.3 Hygiëne en foutafhandeling

- Nul `env()`-calls buiten config, nul actieve debug-statements, slechts 5 TODO's op 536 bestanden. Netjes.
- 72 catch-blokken zonder lege bodies; ZGW-verkeer wordt geaudit in `ZgwRequestLog` (bewust zonder bodies, privacy). Webhook-logfouten breken de verwerking niet.
- Inconsistenties: `declare(strict_types=1)` in slechts 24% van de bestanden (nieuw werk wel, ouder werk niet), en ± 138 Nederlandstalige comments in verder Engelstalige code, tegen de eigen richtlijn in.
- N+1-bewaking ontbreekt: `Model::preventLazyLoading()` wordt niet gezet, `withCount` wordt nergens gebruikt, en enkele visibility-closures doen relatie-counts per rij.

### 7.4 Security-signalen (geen volledige audit)

Expliciete `$fillable` overal, signed en verlopende invite-URL's, webhook achter Passport-scope, geen secrets in de repo, `SECURITY.md` aanwezig. Volwassen basis.

### 7.5 Versiebeheer en documentatie

Versiebeheer is voorbeeldig: 1190 commits strikt via PR's, semantische branchnamen, backport-branches naar release-lijnen, geautomatiseerde changelog en Nederlandstalige release notes per release in `docs/releases/`. Documentatie eveneens: gedragsspecificatie per formulierstap (stap 01 tot en met 16), datamodel-documentatie, releaseproces, veldenkaart. Enige smet: de versie-discrepanties uit 2.2.

---

## 8. Geconsolideerde risicolijst

Gesorteerd op ernst maal waarschijnlijkheid:

1. **Netwerk-I/O in `Zaak`-accessors met eeuwige cache** (6.2, 6.4). Faalgedrag lekt naar views en notificaties, en stale data is onzichtbaar.
2. **Geen reconciliatie tussen lokale DB en OpenZaak** (6.4). Verloren webhook betekent stil divergerende zaakweergave.
3. **Autorisatie verspreid plus magere policy-tests** (4.2, 7.1). Regressies in wie-mag-wat worden niet door tests gevangen.
4. **Job-keten zonder uniforme retry- en idempotentie-baseline** (4.2). Dubbele ZGW-mutaties of blijvend hangende ketens, inclusief uitgestelde BSN/KvK-anonimisering.
5. **CI-database wijkt af van productie** (2.2). Tests bewijzen MySQL-gedrag, productie draait Postgres.
6. **Stille fallback naar `main`-connectie** (6.4). Misconfiguratie van een gemeente-koppeling wordt een log-regel in plaats van een fout.
7. **`woweb/openzaak` op `dev-main`** (6.4). Ongetagde branch als productie-dependency.
8. **Geospatiale aanpak schaalt niet** (6.5). N roundtrips per locatiecheck, PDOK/Kadaster-calls zonder timeout.
9. **STI-randgevallen** (6.3). Partial models in queue-context, base-User versus subklasse, ontbrekende morph-alias.
10. **Geen geautomatiseerde dependency-updates** (7.2).

---

## 9. Aanbevelingen

### Korte termijn (dagen, laag risico)

1. Zet `Model::preventLazyLoading(! app()->isProduction())` aan en los de gevonden lazy loads op.
2. Corrigeer `CLAUDE.md` (Laravel 13, Filament 5, geen PostGIS) en synchroniseer README.
3. Voeg Dependabot of Renovate toe.
4. Pin `woweb/openzaak` op een tag, of rond de migratie naar `laravel-zgw-client` af en verwijder het legacy-package plus `OpenFormsNormalizer`-residu.
5. Verwijder de Pest-scaffolding-stubs, de dubbele scope-registratie op `Event`, en de dode config-parameters in `KadasterService` en `SyncGeometry` (herstel daarmee meteen de dependency injection).
6. Voeg timeouts toe aan de PDOK- en Kadaster-calls.

### Middellange termijn (weken)

7. **Introduceer een `ZgwZaakJob`-base** (of trait) met connectie-resolutie, `zgw_zaak_url`-guard en een uniforme tries/backoff-baseline; maak de chain-jobs aantoonbaar idempotent. Dit lost duplicatiecluster 3 en risico 4 in één beweging op.
8. **Extraheer de ZGW-read-laag uit `Zaak`**: verplaats `openzaak`, `documenten`, `besluiten`, `statustype` naar het bestaande `ZaakReadModel`, vervang `rememberForever` door een TTL plus expliciete invalidatie.
9. **Bouw een reconciliatiecommand** dat periodiek `reference_data` tegen OpenZaak verifieert en afwijkingen rapporteert (dicht risico 2 en maakt webhook-verlies zichtbaar).
10. Werk duplicatieclusters 1, 2 en 4 weg (threads, settings-resources, pending-invites-widgets) volgens het `AbstractAcceptInvite`-patroon, en laat de Organiser-`ZaakResource` van de Shared base erven.
11. Breid policy-tests uit richting dekking van alle 31 policies, met per rol een matrix-test.
12. Zet CI over op PostgreSQL zodat test- en productieomgeving overeenkomen.

### Lange termijn (kwartaal)

13. Overweeg echte PostGIS voor de gemeentegrens-checks (één indexeerbare query in plaats van N roundtrips), of accepteer expliciet de huidige schaalgrens en documenteer die.
14. Maak `FormFieldVisibility` data-driven (de zichtbaarheidsregels als configuratie of database, niet als 1317 regels code) en splits `EventFormPage` op.
15. Trek PHPStan stapsgewijs op vanaf level 5 en werk de 50 inline ignores plus 9 Rector-skips weg.
16. Besluit over meertaligheid: óf de 285 hardcoded labels en de EN-taalbestanden bijtrekken, óf EN formeel schrappen.

---

## 10. Slotoordeel

Deze codebase hoort bij de betere Laravel-applicaties in zijn soort: de moeilijke onderdelen (multi-tenant ZGW-integratie, asynchrone verwerking, multi-panel autorisatie) zijn bewust ontworpen en goed gedocumenteerd, de procesvolwassenheid (releases, CI, documentatie) is hoog, en de technische schuld is afgebakend in plaats van diffuus. De rode draad door alle bevindingen is steeds dezelfde: **goede patronen zijn aanwezig maar niet overal doorgetrokken**. De Shared-namespace bestaat, maar Organiser breekt eruit. Het read-model bestaat, maar `Zaak` omzeilt het. Idempotente jobs bestaan, maar alleen bij notificaties. De abstracte invite-acceptance bestaat, maar mails en widgets zijn gekopieerd. Consequent afmaken van wat er al staat levert hier meer op dan nieuwe architectuur.

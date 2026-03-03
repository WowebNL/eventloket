# Datamodel Documentatie - Evenement Applicatie

**Datum:** 15 december 2025  
**Versie:** 1.0

---

## 1. Introductie

Deze evenement applicatie is een procesondersteuningssysteem voor gemeenten, organisatoren en adviesorganen bij het aanvragen en behandelen van evenementen. Het systeem beheert **procesdata** (workflows, communicatie, status) binnen de applicatie, terwijl de **zaakdata** wordt opgeslagen in het bronsysteem via de **Zaakgericht Werken (ZGW) API's**.

### Architectuur Principes
- **Scheiding van proces- en zaakdata**: Procesdata blijft lokaal, zaakdata in externe systemen
- **ZGW API integratie**: OpenZaak voor zaken, documenten en besluiten
- **Open Formulieren integratie**: Formulieren voor aanvragen, zaakregistratie via notificaties
- **Multi-tenancy**: Ondersteuning voor meerdere gemeenten en organisaties
- **Role-based access**: Strikte scheiding tussen rollen en tenants
- **Event-driven**: Notificaties API voor real-time synchronisatie

---

## 2. Kernentiteiten

### 2.1 Users (Gebruikers)
**Tabel:** `users`

**Beschrijving:** Centrale gebruikerstabel met Single Table Inheritance (STI) patroon voor verschillende rollen.

**Belangrijke velden:**
- `id` - Primary key
- `name`, `email`, `password` - Authenticatie
- `role` - Enum: Admin, MunicipalityAdmin, ReviewerMunicipalityAdmin, Reviewer, Advisor, Organiser
- `email_verified_at` - Email verificatie
- `app_authentication_secret` - 2FA ondersteuning
- `deleted_at` - Soft deletes

**Relaties:**
- Meerdere `municipalities` (many-to-many via `municipality_user`)
- Meerdere `organisations` (many-to-many via `organisation_user` met rol)
- Meerdere `advisories` (many-to-many via `advisory_user` met rol)
- Heeft `notification_preferences`
- Heeft `unread_messages`

**User Types (STI):**
- `AdminUser` - Platformbeheerder
- `MunicipalityUser` (base voor gemeentegebruikers)
  - `MunicipalityAdminUser` - Gemeentelijk beheerder
  - `ReviewerUser` - Behandelaar
  - `ReviewerMunicipalityAdminUser` - Behandelaar + beheerder
- `AdvisorUser` - Adviesdienst medewerker
- `OrganiserUser` - Organisator/aanvrager

**Beveiliging:**
- Soft deletes actief
- Password hashing
- 2FA ondersteuning via `app_authentication_*` velden
- Encrypted velden voor authenticatie secrets

---

### 2.2 Municipalities (Gemeenten)
**Tabel:** `municipalities`

**Beschrijving:** Gemeenten die het systeem gebruiken. Tenant voor gemeente-gebruikers.

**Belangrijke velden:**
- `id` - Primary key
- `name` - Gemeentenaam
- `brk_identification` - BRK identificatie
- `brk_uuid` - BRK UUID
- `geometry` - GeoJSON geometrie van gemeentegrens (JSONB)

**Relaties:**
- Heeft meerdere `users` (via `municipality_user`)
- Heeft meerdere `zaaktypen`
- Heeft meerdere `locations` (locaties binnen gemeente)
- Heeft meerdere `variables` (gemeente-specifieke instellingen)
- Heeft meerdere `default_advice_questions`
- Heeft meerdere `advisories` (gekoppelde adviesorganen)

**Beveiliging:**
- Gebruikers hebben alleen toegang tot hun eigen gemeente(n)
- Multi-tenancy via Filament

---

### 2.3 Organisations (Organisaties)
**Tabel:** `organisations`

**Beschrijving:** Organisaties die evenementen aanvragen. Tenant voor organisatoren.

**Belangrijke velden:**
- `id` - Primary key
- `uuid` - UUID voor externe referenties
- `type` - Enum: verschillende organisatietypes
- `name` - Organisatienaam
- `coc_number` - KvK nummer (8 cijfers, unique, nullable)
- `address`, `bag_id` - Adresgegevens
- `email`, `phone` - Contactgegevens
- `deleted_at` - Soft deletes

**Relaties:**
- Heeft meerdere `users` (via `organisation_user` met rol: Admin/Member)
- Heeft meerdere `zaken`
- Heeft meerdere `form_submission_sessions`

**Beveiliging:**
- Soft deletes actief
- Organisatoren hebben alleen toegang tot hun eigen organisatie(s)
- Multi-tenancy via Filament

---

### 2.4 Advisories (Adviesorganen)
**Tabel:** `advisories`

**Beschrijving:** Adviesorganen die advies geven over evenementen (bijv. Veiligheidsregio, Politie).

**Belangrijke velden:**
- `id` - Primary key
- `name` - Naam adviesorgaan

**Relaties:**
- Heeft meerdere `users` (via `advisory_user` met rol: Admin/Member)
- Gekoppeld aan meerdere `municipalities` (via `advisory_municipality`)
- Heeft meerdere `default_advice_questions`

**Beveiliging:**
- Adviseurs hebben alleen toegang tot hun eigen adviesorgaan
- Multi-tenancy via Filament

---

### 2.5 Zaaktypen
**Tabel:** `zaaktypen`

**Beschrijving:** Zaaktypen uit de ZGW Catalogi API, lokaal gecached voor performance.

**Belangrijke velden:**
- `id` - UUID (primary key)
- `name` - Naam zaaktype
- `zgw_zaaktype_url` - URL naar zaaktype in OpenZaak (unique)
- `municipality_id` - Gekoppelde gemeente (nullable)
- `is_active` - Of zaaktype actief is

**Relaties:**
- Behoort tot `municipality`
- Heeft meerdere `zaken`

**ZGW Integratie:**
- Synchronized met OpenZaak Catalogi API
- Cached documenttypen en resultaattypen

---

### 2.6 Zaken (Kernentiteit)
**Tabel:** `zaken`

**Beschrijving:** Centrale entiteit voor evenementaanvragen. Bevat **alleen procesdata**, zaakdata staat in OpenZaak.

**Belangrijke velden:**
- `id` - UUID (primary key)
- `public_id` - Publiek zichtbaar ID (unique)
- `zgw_zaak_url` - URL naar zaak in OpenZaak (unique) ⚠️
- `zaaktype_id` - FK naar zaaktype
- `organisation_id` - FK naar organisatie (nullable, cascade delete)
- `organiser_user_id` - FK naar aanvragende gebruiker (nullable, cascade delete)
- `data_object_url` - URL naar data-object in Objects API (nullable)
- `reference_data` - JSONB met referentiedata (naam, datums, etc.)
- `handled_status_set_by_user_id` - FK naar behandelende gebruiker (nullable)

**Relaties:**
- Behoort tot `zaaktype`
- Behoort tot `organisation` (optioneel)
- Behoort tot `organiser_user` (aanvrager)
- Heeft één `municipality` (via zaaktype)
- Heeft meerdere `threads` (advies- en organisatorenthreads)
- Heeft `handled_status_set_by_user`

**ZGW Integratie:**
- `zgw_zaak_url` verwijst naar zaak in OpenZaak
- Via deze URL worden zaakgegevens, status, documenten opgehaald
- Documenten worden gefilterd op vertrouwelijkheidsniveau per rol
- Besluiten worden opgehaald via Besluiten API

**Reference Data (JSONB):**
Bevat processpecifieke data zoals:
- `naam_evenement` - Evenementnaam
- `start_datum`, `eind_datum` - Datums
- `risico_classificatie` - A, B, of C

**Beveiliging:**
- Cascade deletes bij verwijdering organisatie/gebruiker
- Activity logging via Spatie ActivityLog
- Observer voor notificaties bij aanmaak
- Read-only na aanmaak (geen update/delete via applicatie)

---

### 2.7 Threads (Communicatie)
**Tabel:** `threads`

**Beschrijving:** Communicatiethreads binnen een zaak. STI patroon voor verschillende types.

**Belangrijke velden:**
- `id` - Primary key
- `zaak_id` - FK naar zaak
- `type` - Enum: Advice (advies), Organiser (organisator)
- `title` - Titel thread
- `advisory_id` - FK naar adviesorgaan (nullable, alleen voor advice threads)
- `advice_status` - Status advies (nullable)
- `advice_due_at` - Deadline advies (nullable)
- `created_by` - FK naar gebruiker die thread aanmaakte

**Relaties:**
- Behoort tot `zaak`
- Heeft meerdere `messages`
- Heeft meerdere `assigned_users` (via `thread_user`)
- Behoort tot `advisory` (alleen voor advice threads)
- Aangemaakt door `user`

**Thread Types (STI):**
- `AdviceThread` - Voor adviesvragen aan adviesorganen
- `OrganiserThread` - Voor communicatie met organisatoren

**Beveiliging:**
- Activity logging
- Toegang gebaseerd op rol en betrokkenheid bij zaak

---

### 2.8 Messages (Berichten)
**Tabel:** `messages`

**Beschrijving:** Individuele berichten binnen threads.

**Belangrijke velden:**
- `id` - Primary key
- `thread_id` - FK naar thread (cascade delete)
- `user_id` - FK naar gebruiker (cascade delete)
- `body` - Berichtinhoud (text)
- `documents` - JSON array met MessageDocument objecten

**Relaties:**
- Behoort tot `thread`
- Behoort tot `user` (auteur)
- Heeft meerdere `unread_by_users` (via `unread_messages`)

**Beveiliging:**
- Observer voor automatische notificaties bij nieuw bericht
- Unread tracking per gebruiker
- Activity logging
- Cascade deletes

---

### 2.9 Locations (Locaties)
**Tabel:** `locations`

**Beschrijving:** Voorgedefinieerde evenementlocaties per gemeente.

**Belangrijke velden:**
- `id` - Primary key
- `municipality_id` - FK naar gemeente (nullable, cascade delete)
- `name` - Locatienaam
- `postal_code`, `house_number`, `house_letter`, `house_number_addition` - Adres
- `street_name`, `city_name` - Adresgegevens (nullable)
- `active` - Of locatie actief is
- `geometry` - GeoJSON geometrie (JSONB, nullable)

**Relaties:**
- Behoort tot `municipality`

---

### 2.10 Municipality Variables
**Tabel:** `municipality_variables`

**Beschrijving:** Configureerbare variabelen per gemeente (instellingen, vragen, etc.).

**Belangrijke velden:**
- `id` - Primary key
- `municipality_id` - FK naar gemeente (nullable, cascade delete)
- `name` - Naam variabele
- `key` - Unieke sleutel (unique per gemeente)
- `type` - Enum: Text, Number, DateRange, TimeRange, DateTimeRange, Boolean, ReportQuestion
- `value` - JSONB waarde
- `is_default` - Of dit een standaardwaarde is
- `deleted_at` - Soft deletes

**Relaties:**
- Behoort tot `municipality`

**Unique constraint:** `[municipality_id, key]`

**Beveiliging:**
- Soft deletes
- Observer voor cache invalidatie

---

### 2.11 Default Advice Questions
**Tabel:** `default_advice_questions`

**Beschrijving:** Voorgedefinieerde adviesvragen per gemeente en adviesorgaan op basis van risicoclassificatie.

**Belangrijke velden:**
- `id` - Primary key
- `municipality_id` - FK naar gemeente (cascade delete)
- `advisory_id` - FK naar adviesorgaan (cascade delete)
- `risico_classificatie` - A, B, of C
- `title` - Titel vraag
- `description` - Omschrijving
- `response_deadline_days` - Aantal dagen deadline

**Relaties:**
- Behoort tot `municipality`
- Behoort tot `advisory`

---

## 3. Ondersteunende Entiteiten

### 3.1 Invite Models
Voor onboarding van nieuwe gebruikers:

**Organisation Invites** (`organisation_invites`):
- `organisation_id`, `email`, `role`, `token` (unique UUID)
- Verloopt na bepaalde tijd (Expirable trait)

**Advisory Invites** (`advisory_invites`):
- `advisory_id`, `email`, `role`, `token`
- Verloopt na bepaalde tijd

**Municipality Invites** (`municipality_invites`):
- Many-to-many met municipalities via `municipality_invite_municipality`
- `email`, `role`, `token`

**Admin Invites** (`admin_invite_municipality`):
- Many-to-many met municipalities
- Voor beheerders

### 3.2 Notification Preferences
**Tabel:** `notification_preferences`

- Per gebruiker en notificatietype configureerbare kanalen (mail, database)
- Unique constraint: `[user_id, notification_class]`

### 3.3 Form Submission Sessions
**Tabel:** `formsubmission_sessions`

**Beschrijving:** Tijdelijke sessies die de link leggen tussen een organisator die een formulier invult in Open Formulieren en hun account in Eventloket.

**Belangrijke velden:**
- `uuid` - UUID primary key (niet auto-increment)
- `user_id` - FK naar gebruiker (cascade delete)
- `organisation_id` - FK naar organisatie (cascade delete)
- `created_at`, `updated_at` - Timestamps

**Relaties:**
- Behoort tot `user` (OrganiserUser)
- Behoort tot `organisation`

**Gebruik in Open Formulieren flow:**
1. Organisator klikt op "Nieuwe aanvraag" in Eventloket
2. Sessie wordt aangemaakt met unieke UUID
3. Organisator wordt doorgestuurd naar Open Formulieren met UUID als authenticatie parameter
4. Open Formulieren gebruikt UUID om gebruikers- en organisatiegegevens op te halen via API
5. Na formulierinzending wordt sessie gebruikt om zaak te koppelen aan organisatie

**Lifecycle:**
- Aangemaakt bij start formulier
- Gebruikt tijdens formulierinzending
- Kan opgeruimd worden na succesvolle zaakregistratie (cleanup job)

### 3.4 Applications (API Clients)
**Tabel:** `applications`

- OAuth Passport clients voor API toegang
- Ondersteuning voor externe applicaties

### 3.5 Activity Log
**Tabel:** `activity_log` (Spatie ActivityLog)

- Audit trail voor Zaak en Thread wijzigingen
- Polymorphic relaties (subject, causer)
- JSONB properties veld

---

## 4. Pivot Tabellen & Many-to-Many Relaties

### 4.1 Gebruikersrelaties
- **`municipality_user`**: Users ↔ Municipalities
- **`organisation_user`**: Users ↔ Organisations (met `role` veld: Admin/Member)
- **`advisory_user`**: Users ↔ Advisories (met `role` veld: Admin/Member)

### 4.2 Overige
- **`advisory_municipality`**: Advisories ↔ Municipalities
- **`thread_user`**: Threads ↔ Users (toegewezen gebruikers)
- **`unread_messages`**: Messages ↔ Users (ongelezen berichten tracking)
- **`admin_invite_municipality`**: Admin invites ↔ Municipalities

---

## 5. Zaakgericht Werken (ZGW) Integratie

### 5.1 Architectuur
De applicatie implementeert een **hybride architectuur**:
- **Procesdata** → Lokale PostgreSQL database
- **Zaakdata** → OpenZaak (ZGW API's)

### 5.2 ZGW URL Referenties
Elke `Zaak` heeft een `zgw_zaak_url` die verwijst naar de zaak in OpenZaak:
```
https://openzaak.example.com/zaken/api/v1/zaken/{uuid}
```

Via deze URL worden real-time opgehaald:
- **Zaakgegevens**: identificatie, status, eigenschappen
- **Statusgeschiedenis**: alle statuswijzigingen
- **Documenten**: via ZaakInformatieObjecten relaties
- **Zaakobjecten**: gekoppelde objecten (adressen, personen)
- **Resultaten**: besluitvorming

### 5.3 API Endpoints
**Zaken API:**
- `GET /zaken/api/v1/zaken/{uuid}?expand=...` - Zaak ophalen met gerelateerde data

**Catalogi API:**
- `GET /catalogi/api/v1/zaaktypen` - Zaaktypen
- `GET /catalogi/api/v1/informatieobjecttypen` - Documenttypen
- `GET /catalogi/api/v1/resultaattypen` - Resultaattypen

**Documenten API:**
- `GET /documenten/api/v1/enkelvoudiginformatieobjecten/{uuid}` - Document ophalen

**Besluiten API:**
- `GET /besluiten/api/v1/besluiten?zaak={zaak_url}` - Besluiten van zaak
- `GET /besluiten/api/v1/besluitinformatieobjecten` - Besluitdocumenten

**Objects API:**
- Formulierdata opslag in `data_object_url`

### 5.4 Caching Strategie
- **Zaaktypen**: Lokaal gecached in `zaaktypen` tabel
- **Zaakgegevens**: `Cache::rememberForever()` per zaak
- **Documenten**: Gefilterd op vertrouwelijkheidsniveau per rol
- **Cache invalidatie**: Via observers bij wijzigingen

### 5.5 Vertrouwelijkheid & Filtering
Documenten worden gefilterd op basis van gebruikersrol:

| Rol | Toegang tot vertrouwelijkheidsniveau |
|-----|--------------------------------------|
| Admin | Alle niveaus |
| Municipality Admins/Reviewers | Intern en lager |
| Advisors | Beperkt intern en lager |
| Organisers | Openbaar |

**Implementatie:** `DocumentVertrouwelijkheden` enum met `fromUserRole()` methode

---

## 5A. Open Formulieren Integratie

### 5A.1 Architectuur Overzicht
Open Formulieren is een headless formulierenengine die volledig geïntegreerd is met de ZGW standaarden. Voor Eventloket fungeert Open Formulieren als het primaire aanvraagkanaal voor organisatoren.

**Componenten:**
- **Open Formulieren** - Formulierendefinities en -logica
- **Open Formulieren SDK** - JavaScript widget geëmbedded in Eventloket
- **OpenZaak** - Zaakregistratie
- **Objects API** - Formulierdata opslag
- **Notificaties API** - Event bus voor zaakregistratie events
- **Eventloket API** - Authenticatie en context voor formulierinzendingen

### 5A.2 Aanvraag Flow (End-to-End)

```
┌─────────────┐         ┌──────────────────┐         ┌──────────────┐
│ Organisator │         │  Eventloket      │         │     Open     │
│             │────────>│                  │────────>│  Formulieren │
└─────────────┘  Login  └──────────────────┘  Embed  └──────────────┘
                                │                            │
                                │ 1. Create Session          │
                                │ 2. Generate UUID           │
                                │ 3. Redirect to form        │
                                │                            │
                                │<───────────────────────────│
                                │   API: Get user/org data   │
                                │                            │
                                │                            │ 4. Submit form
                                │                            │
                                │                            ▼
                         ┌──────────────┐            ┌────────────┐
                         │ Objects API  │<───────────│  OpenZaak  │
                         │ (Form data)  │            │  (Zaak)    │
                         └──────────────┘            └────────────┘
                                                            │
                                                            │ 5. Zaak created
                                                            ▼
                                                     ┌──────────────────┐
                                                     │  Notificaties    │
                                                     │      API         │
                                                     └──────────────────┘
                                                            │
                                                            │ 6. Notification
                                                            ▼
                         ┌──────────────────────────────────────────┐
                         │  Eventloket Webhook Endpoint             │
                         │  POST /api/notifications                 │
                         └──────────────────────────────────────────┘
                                           │
                                           │ 7. Process notification
                                           ▼
                                    ┌─────────────┐
                                    │   Zaak      │
                                    │   Model     │
                                    │  (Local DB) │
                                    └─────────────┘
```


---

## 6. Beveiligingsmaatregelen

### 6.1 Multi-Tenancy
**Filament Multi-Tenancy** voor strikte scheiding:
- Gemeentegebruikers: Tenant = Municipality
- Organisatoren: Tenant = Organisation  
- Adviseurs: Tenant = Advisory
- Admins: Geen tenant (volledige toegang)

**Implementatie:**
- `HasTenants` interface op User models
- `canAccessTenant()` validatie
- `getTenants()` voor beschikbare tenants

### 6.2 Policy-based Authorization
Elke model heeft een Policy:
- `ZaakPolicy` - Toegang tot zaken op basis van rol en tenant
- `ThreadPolicy` - Toegang tot threads op basis van betrokkenheid
- `MessagePolicy` - Toegang tot berichten
- `OrganisationPolicy`, `AdvisoryPolicy`, `MunicipalityPolicy`

**Voorbeelden:**
- Organisatoren kunnen alleen hun eigen organisatie-zaken zien
- Gemeente-reviewers zien alleen zaken van hun gemeente
- Adviseurs zien alleen zaken met adviesthreads voor hun orgaan

### 6.3 Soft Deletes
Soft deletes actief op:
- `users`
- `organisations`
- `municipality_variables`

→ Historische data blijft bewaard, relaties blijven intact

### 6.4 Cascade Deletes
Strikte cascade delete regels:
- Verwijdering `organisation` → verwijdert gekoppelde `zaken`
- Verwijdering `thread` → verwijdert `messages`
- Verwijdering `municipality` → verwijdert `locations`, `variables`

### 6.5 Foreign Key Constraints
Alle relaties hebben foreign key constraints voor referentiële integriteit:
```sql
CONSTRAINT fk_name FOREIGN KEY (column) 
    REFERENCES table(id) 
    ON DELETE CASCADE/SET NULL
```

### 6.6 Encrypted Data
- Passwords: Hashed via bcrypt
- 2FA secrets: Encrypted in database
- Recovery codes: Encrypted arrays
- OAuth tokens: Encrypted via Passport

### 6.7 Email Verificatie
- `MustVerifyEmail` interface op User model
- `email_verified_at` timestamp
- Verificatie verplicht voor toegang

### 6.8 Activity Logging
**Spatie ActivityLog** voor audit trail:
- Alle wijzigingen aan `Zaak` gelogd
- Alle wijzigingen aan `Thread` en `Message` gelogd
- Causer (wie) en subject (wat) tracking
- JSONB properties voor oude/nieuwe waarden

### 6.9 Observers
**Model Observers** voor automatische acties:
- `ZaakObserver`: Notificaties bij nieuwe zaak, adviesvragen aanmaken
- `MessageObserver`: Notificaties naar thread participants, unread tracking
- `MunicipalityVariableObserver`: Cache invalidatie
- `MunicipalityObserver`: Geometrie processing

### 6.10 Input Validatie
- Fillable velden expliciet gedefinieerd per model
- Type casting via `casts()` methode
- Enum validatie voor vaste waardenlijsten
- Unique constraints op kritieke velden (email, token, zgw_zaak_url)

---

## 7. Data Consistentie & Integriteit

### 7.1 Database Constraints

**Unique Constraints:**
- `users.email` - Geen duplicate emails
- `organisations.coc_number` - Unieke KvK nummers
- `zaken.public_id` - Unieke publieke identificatie
- `zaken.zgw_zaak_url` - Elke zaak eenmalig geregistreerd
- `zaaktypen.zgw_zaaktype_url` - Elke zaaktype eenmalig
- `municipality_variables.[municipality_id, key]` - Unieke keys per gemeente
- `notification_preferences.[user_id, notification_class]` - Unieke voorkeuren

**Composite Primary Keys:**
- Pivot tabellen gebruiken composite keys: `[entity1_id, entity2_id]`

### 7.2 Nullable Fields Strategy
Strategisch gebruik van nullable velden:
- `zaak.organisation_id` - Nullable voor gemeentelijke evenementen
- `zaak.organiser_user_id` - Nullable voor handmatig aangemaakte zaken
- `location.municipality_id` - Nullable voor algemene locaties
- `zaaktype.municipality_id` - Nullable voor generieke zaaktypen

### 7.3 JSONB voor Flexibiliteit
PostgreSQL JSONB voor semi-gestructureerde data:
- `zaak.reference_data` - Flexibele referentiedata
- `municipality_variable.value` - Dynamische waardes
- `notification_preference.channels` - Array van kanalen
- `municipality.geometry`, `location.geometry` - GeoJSON

**Voordelen:**
- Schema flexibiliteit zonder migraties
- Efficient indexing en querying
- Type safety via ValueObjects (ZaakReferenceData)

### 7.4 Transactionele Integriteit
- Database transacties voor multi-step operaties
- Queue jobs voor async ZGW operaties
- Retry mechanisme voor API calls
- Event sourcing via Activity Log

### 7.5 Data Migratie & Seeding
- Seeders voor Zuid-Limburg gemeenten
- Default municipality variables
- Test data factories voor alle models

---

## 8. Notificatie & Communicatie Flow

### 8.1 Notificatie Types
- `NewZaak` - Bij nieuwe zaak aanmaak
- `NewAdviceThreadMessage` - Nieuw bericht in adviesthread
- `NewOrganiserThreadMessage` - Nieuw bericht in organisatorenthread

### 8.2 Notificatie Kanalen
- **Database**: In-app notificaties (opgeslagen in `notifications` tabel)
- **Email**: Via mail driver
- **Configureerbaar**: Per gebruiker via `notification_preferences`

### 8.3 Notificatie Flow

**Bij nieuwe Zaak:**
1. `ZaakObserver::created()` triggered
2. Dispatch `CreateConceptAdviceQuestions` job
3. Notify `organiserUser` (aanvrager)
4. Notify alle gemeente reviewers

**Bij nieuw Message:**
1. `MessageObserver::created()` triggered
2. Bepaal thread participants via `getParticipants()`
3. Stuur notificatie naar alle participants (excl. auteur)
4. Maak `unread_messages` entries
5. Email verzonden via queue

### 8.4 Unread Tracking
- `unread_messages` pivot tabel
- Entry per ongelezen bericht per gebruiker
- Verwijderd bij markeren als gelezen
- Gebruikt voor badges/counters in UI

---

## 9. Single Table Inheritance (STI)

### 9.1 User STI
**Tabel:** `users` met `role` discriminator

```php
User::resolveClassForRole(Role $role): string
```

**Subclasses:**
- `AdminUser`
- `MunicipalityUser` (abstract base)
  - `MunicipalityAdminUser`
  - `ReviewerUser`
  - `ReviewerMunicipalityAdminUser`
- `AdvisorUser`
- `OrganiserUser`

**Voordelen:**
- Type safety in code
- Specifieke methodes per rol
- Shared authenticatie/autorisatie
- Polymorphic relaties mogelijk

### 9.2 Thread STI
**Tabel:** `threads` met `type` discriminator

```php
Thread::resolveClassForThreadType(ThreadType $type): string
```

**Subclasses:**
- `AdviceThread` - Heeft `advisory_id`, `advice_status`, `advice_due_at`
- `OrganiserThread` - Communicatie met organisatoren

### 9.3 Event STI
**Model:** `Event extends Zaak`

- Global scope filtering op specifieke zaaktypen
- Event-specifieke business logic
- Calendar integration (Eventable interface)

---

## 10. Queues & Jobs

### 10.1 Background Jobs
- `CreateConceptAdviceQuestions` - Adviesvragen aanmaken bij nieuwe zaak
- Email verzending via queue
- Document processing
- ZGW API calls (async voor performance)

### 10.2 Job Batches
**Tabel:** `job_batches`
- Batch tracking voor langlopende operaties
- Progress monitoring
- Failure handling

---

## 11. OAuth & API Access

### 11.1 Laravel Passport
**Tabellen:**
- `oauth_clients`
- `oauth_access_tokens`
- `oauth_refresh_tokens`
- `oauth_device_codes`

### 11.2 Application Model
- Registratie van externe applicaties
- Token management
- Endpoint restrictions via `all_endpoints` flag

---

## 12. Performance & Optimization

### 12.1 Eager Loading
- `with()` voor N+1 query preventie
- Lazy loading prevention in productie

### 12.2 Caching
- `Cache::rememberForever()` voor statische ZGW data
- Per-zaak caching van OpenZaak responses
- Cache keys: `zaak.{id}.{property}`

### 12.3 Indexing
Database indices op:
- Foreign keys (automatisch)
- Unique constraints
- Vaak gezochte velden (email, zgw_zaak_url)
- Composite indices op pivot tabellen

### 12.4 Query Scopes
- `Thread::advice()`, `Thread::organiser()`
- `MunicipalityUser::reviewers()`, `MunicipalityUser::admins()`
- Herbruikbare query logic

---

## 13. Testing & Quality

### 13.1 Factories
Database factories voor alle models via Faker:
- Realistische test data
- Relationship handling
- State patterns

### 13.2 Seeders
- `SouthLimburgMunicipalitiesSeeder` - Zuid-Limburg gemeenten
- Default data voor development

---

## 14. Samenvatting & Conclusies

### 14.1 Sterke Punten
- **Duidelijke scheiding proces- vs zaakdata** via ZGW integratie  
- **Robuuste beveiliging** via policies, multi-tenancy, encryption  
- **Data integriteit** via constraints, cascade deletes, soft deletes  
- **Flexibiliteit** via JSONB, STI patterns, configureerbare variabelen  
- **Audit trail** via activity logging  
- **Notificatie systeem** met unread tracking  
- **Performance** via caching en eager loading  

### 14.2 Architectuur Highlights
- **Zaakgericht Werken** als fundament voor zaakbeheer
- **Open Formulieren** voor flexibele aanvraagformulieren
- **Event-driven** via Notificaties API, observers en jobs
- **Multi-tenancy** voor schaalbaarheid
- **Single Table Inheritance** voor type safety
- **API-first** via OAuth Passport en ZGW API's
- **Webhooks** voor real-time synchronisatie tussen systemen

### 14.3 Data Flow (Compleet)
1. **Initiatie**: Organisator klikt "Nieuwe aanvraag" 
2. **Formulier**: Redirect naar Open Formulieren met sessie UUID
3. **Context**: Open Formulieren haalt gebruikers-/organisatiegegevens op via Eventloket API
4. **Inzending**: Formulier verzonden → Open Formulieren registreert zaak in OpenZaak + Objects API
5. **Notificatie**: OpenZaak stuurt webhook naar Eventloket via Notificaties API
6. **Registratie**: Eventloket verwerkt notificatie → `Zaak` record aangemaakt met `zgw_zaak_url`
7. **Adviesronde**: `ZaakObserver` triggert → `AdviceThread` per adviesorgaan, notificaties
8. **Communicatie**: Gemeente/adviseurs/organisator uitwisselen `Messages` in threads, email notificaties
9. **Besluitvorming**: Gemeentebehandelaar zet status/resultaat in OpenZaak
10. **Archivering**: Zaak blijft in OpenZaak, proces history in `activity_log`

---

**Documentatie Einde**

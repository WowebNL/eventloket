# Rollen en Rechten Documentatie

Dit document beschrijft alle rollen, panels en bijbehorende rechten binnen de evenement-applicatie.

## Inhoudsopgave

1. [Gebruikersrollen](#gebruikersrollen)
2. [Organisatierollen](#organisatierollen)
3. [Adviesdienstrollen](#adviesdienstrollen)
4. [Panels en Toegang](#panels-en-toegang)
5. [Resources per Panel](#resources-per-panel)
6. [Rechten per Rol](#rechten-per-rol)
7. [Document Vertrouwelijkheidsniveaus](#document-vertrouwelijkheidsniveaus)

---

## Gebruikersrollen

Het systeem kent **6 hoofdrollen** (gedefinieerd in `App\Enums\Role`):

### 1. Admin (Platformbeheerder)
- **Code**: `admin`
- **Label**: Platformbeheerder
- **Omschrijving**: Volledige controle over het hele platform
- **Panel**: Admin Panel
- **Rechten**:
  - Volledige CRUD rechten op alle resources
  - Kan alle user types beheren (behalve andere Admins verwijderen)
  - Kan municipalities, advisories en applications beheren
  - Kan content management uitvoeren
  - Toegang tot alle document vertrouwelijkheidsniveaus
  - Kan gebruikers force deleten en restoren
  - Kan alle zaken en activiteiten bekijken

### 2. MunicipalityAdmin (Gemeentelijk beheerder)
- **Code**: `municipality_admin`
- **Label**: Gemeentelijk beheerder
- **Omschrijving**: Beheerder van één of meerdere gemeentes
- **Panel**: Municipality Panel (tenant-based)
- **Rechten**:
  - Toegang tot Settings cluster
  - Kan MunicipalityAdmin en Reviewer gebruikers beheren binnen eigen gemeentes
  - Kan advisories beheren (waar alle gekoppelde gemeentes toegankelijk zijn)
  - CRUD rechten op locations, variables en default advice questions binnen eigen gemeentes
  - Kan zaken bekijken en activiteiten inzien binnen eigen gemeentes
  - Toegang tot vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk, Confidentieel
  - Kan gebruikers soft deleten en restoren (geen force delete)

### 3. ReviewerMunicipalityAdmin (Behandelaar en gemeentelijk beheerder)
- **Code**: `reviewer_municipality_admin`
- **Label**: Behandelaar en gemeentelijk beheerder
- **Omschrijving**: Combinatie van behandelaar en gemeentelijk beheerder rechten
- **Panel**: Municipality Panel (tenant-based)
- **Rechten**:
  - Alle rechten van MunicipalityAdmin
  - Alle rechten van Reviewer
  - Kan zaken behandelen en toewijzen aan zichzelf
  - Toegang tot Settings cluster
  - Kan MunicipalityAdmin gebruikers aanmaken en bewerken
  - Kan ReviewerUser verwijderen binnen eigen gemeentes
  - Toegang tot vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk, Confidentieel
  - Werkvoorraad management (nieuwe, eigen, alle zaken)

### 4. Reviewer (gemeentelijk behandelaar)
- **Code**: `reviewer`
- **Label**: Behandelaar
- **Omschrijving**: Behandelt zaken voor een gemeente
- **Panel**: Municipality Panel (tenant-based)
- **Rechten**:
  - Kan zaken bekijken en beheren binnen eigen gemeentes
  - Kan zaken in behandeling nemen
  - Werkvoorraad management (nieuwe zaken, eigen zaken, alle zaken)
  - Kan AdvisorUsers bekijken
  - Toegang tot vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk, Confidentieel
  - Kan activiteiten bekijken van zaken in eigen gemeentes
  - **Geen** toegang tot Settings cluster
  - **Geen** gebruikersbeheer rechten

### 5. Advisor (Adviesdienst)
- **Code**: `advisor`
- **Label**: Adviesdienst
- **Omschrijving**: Werkt voor een adviesdienst en verstrekt adviezen
- **Panel**: Advisor Panel (tenant-based)
- **Rechten**:
  - Kan zaken bekijken waarvoor advies gevraagd is
  - Kan adviesthreads beheren en beantwoorden
  - Kan zich toewijzen aan adviesthreads
  - Werkvoorraad management (nieuwe adviesaanvragen, toegewezen aan mij, alle)
  - Kan AdvisorUsers bekijken
  - Toegang tot vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk
  - Kan activiteiten bekijken van zaken waarvoor advies gevraagd is
  - **Extra rechten als Advisory Admin**: Gebruikersbeheer, Settings cluster toegang, adviseurs uitnodigen
  - Kan zichzelf (en als Admin ook anderen) verwijderen uit advisory

### 6. Organiser (Organisator)
- **Code**: `organiser`
- **Label**: Organisator
- **Omschrijving**: Organiseert evenementen via een organisatie
- **Panel**: Organiser Panel (tenant-based)
- **Rechten**:
  - Kan eigen zaken bekijken (van eigen organisatie)
  - Kan communiceren via OrganiserThreads
  - Kan organisaties aanmaken (Business en Personal)
  - Toegang tot vertrouwelijkheidsniveau: Zaakvertrouwelijk
  - **Extra rechten als Organisation Admin**: Gebruikersbeheer, Settings cluster toegang (niet voor Personal organisations), organisatie-instellingen bewerken, Business organisaties verwijderen, leden uitnodigen, rollen wijzigen
  - **Beperkingen**: Geen zaken aanmaken/bewerken/verwijderen, geen events beheren, geen activiteiten bekijken

---

## Organisatierollen

Binnen organisaties bestaan **2 rollen** (gedefinieerd in `App\Enums\OrganisationRole`):

### 1. Member (Lid)
- **Code**: `member`
- **Omschrijving**: Basis lid van een organisatie
- **Rechten**:
  - Kan zaken van de organisatie bekijken
  - Kan communiceren via OrganiserThreads
  - Kan eigen profiel bewerken
  - **Geen** toegang tot Settings cluster
  - **Geen** organisatie-instellingen wijzigen
  - **Geen** gebruikersbeheer rechten

### 2. Admin (Beheerder)
- **Code**: `admin`
- **Omschrijving**: Beheerder van een organisatie met uitgebreide rechten
- **Rechten**:
  - Alle rechten van Member
  - Kan OrganiserUsers beheren binnen de organisatie
  - Kan organisatie-instellingen bewerken (alleen Business organisations)
  - Kan Business organisaties bewerken en verwijderen
  - Kan andere gebruikers uitnodigen
  - Kan rol van andere leden wijzigen
  - Toegang tot Settings cluster (niet voor Personal organisations)
  - Kan organisatie profiel bewerken
  - **Beperking**: Personal organisations kunnen niet bewerkt of verwijderd worden

---

## Adviesdienstrollen

Binnen adviesdiensten bestaan **2 rollen** (gedefinieerd in `App\Enums\AdvisoryRole`):

### 1. Member (Lid)
- **Code**: `member`
- **Omschrijving**: Basis lid van een adviesdienst
- **Rechten**:
  - Kan adviesaanvragen bekijken en beantwoorden
  - Kan zich toewijzen aan adviesthreads
  - Kan zichzelf verwijderen uit de advisory
  - Geen toegang tot Settings cluster
  - Geen gebruikersbeheer rechten

### 2. Admin (Beheerder)
- **Code**: `admin`
- **Omschrijving**: Beheerder van een adviesdienst met uitgebreide rechten
- **Rechten**:
  - Alle rechten van Member
  - Kan AdvisorUsers beheren binnen de advisory
  - Kan andere adviseurs uitnodigen
  - Kan andere adviseurs bewerken en verwijderen
  - Toegang tot Settings cluster
  - Kan adviseurs toewijzen aan adviesthreads

---

## Panels en Toegang

Het systeem heeft **4 panels**:

### 1. Admin Panel
- **Path**: `/admin`
- **ID**: `admin`
- **Toegang**: 
  - Role::Admin
- **Features**:
  - Dashboard
  - Municipality management
  - Advisory management
  - Application management
  - User management (alle rollen)
  - Content management (Organiser Panel, Welcome)
  - Database notificaties
  - 2FA ondersteuning

### 2. Municipality Panel
- **Path**: `/municipality`
- **ID**: `municipality`
- **Tenant**: Municipality (gemeente-gebaseerd)
- **Toegang**:
  - Role::MunicipalityAdmin
  - Role::ReviewerMunicipalityAdmin
  - Role::Reviewer
- **Features**:
  - Dashboard
  - Zaak management (werkvoorraad: nieuw, mij toegewezen, alle)
  - Calendar (evenementen)
  - Settings cluster (alleen voor MunicipalityAdmin en ReviewerMunicipalityAdmin)
  - Database notificaties
  - 2FA ondersteuning

### 3. Organiser Panel
- **Path**: `/organiser`
- **ID**: `organiser`
- **Tenant**: Organisation (organisatie-gebaseerd)
- **Toegang**:
  - Role::Organiser
- **Features**:
  - Dashboard met intro widget
  - Zaak management
  - Organisation management
  - Settings cluster (alleen voor Organisation Admins, niet voor Personal organisations)
  - Registratie en email verificatie
  - Database notificaties
  - 2FA ondersteuning

### 4. Advisor Panel
- **Path**: `/advisor`
- **ID**: `advisor`
- **Tenant**: Advisory (adviesdienst-gebaseerd)
- **Toegang**:
  - Role::Advisor
- **Features**:
  - Dashboard
  - Zaak management (werkvoorraad: nieuw, mij toegewezen, alle)
  - Settings cluster (alleen voor Advisory Admins)
  - Database notificaties
  - 2FA ondersteuning

---

## Resources per Panel

### Admin Panel Resources

#### Hoofdresources:
1. **MunicipalityResource** - Gemeente beheer
2. **AdvisoryResource** - Adviesdienst beheer
3. **ApplicationResource** - Aanvraag beheer
4. **AdminUserResource** - Admin gebruikers beheer
5. **ZaakResource** - Zaak overzicht

#### Settings/Content Pages:
- **ManageOrganiserPanel** - Organiser panel content beheer
- **ManageWelcome** - Welcome page content beheer

#### Relation Managers bij Municipality:
- MunicipalityAdminUsersRelationManager
- ReviewerMunicipalityAdminUsersRelationManager
- ReviewerUsersRelationManager
- LocationsRelationManager
- DefaultAdviceQuestionsRelationManager
- VariablesRelationManager

### Municipality Panel Resources

#### Shared Resources:
- **ZakenResource** - Zaak overzicht en beheer

#### Settings Cluster Resources (toegang: MunicipalityAdmin, ReviewerMunicipalityAdmin):
1. **MunicipalityAdminUserResource** - Gemeentelijk beheerders
2. **AdvisoryResource** - Adviesdiensten
3. **LocationResource** - Locaties
4. **MunicipalityVariableResource** - Gemeente variabelen
5. **DefaultAdviceQuestionResource** - Standaard adviesvragen

#### Relation Managers bij Advisory:
- MunicipalitiesRelationManager
- UsersRelationManager

#### Pages:
- **Calendar** - Evenementen kalender (kalender en tabel weergave)
- **Dashboard** - Overzicht

### Organiser Panel Resources

#### Shared Resources:
- **ZakenResource** - Zaken overzicht

#### Settings Cluster Resources (toegang: Organisation Admins, niet Personal):
1. **OrganiserUserResource** - Organisatie gebruikers beheer

#### Pages:
- **Dashboard** - Overzicht met widgets
- **Register** - Nieuwe organisator registratie
- **RegisterOrganisation** - Nieuwe organisatie aanmaken
- **EditOrganisationProfile** - Organisatie profiel bewerken

### Advisor Panel Resources

#### Shared Resources:
- **ZakenResource** - Zaken overzicht met adviesthread filters

#### Settings Cluster Resources (toegang: Advisory Admins):
1. **AdvisorUserResource** - Adviseur gebruikers beheer

#### Pages:
- **Dashboard** - Overzicht

### Shared Resources (gebruikt in meerdere panels)

1. **Zaken (Zaak)** - Zaak management
   - ZaakResource met sub-resources:
     - AdviceThreads
     - OrganiserThreads
   - Activities (activiteiten logging)
   - Threads (communicatie)
   - Locations
   - DefaultAdviceQuestions
   - MunicipalityVariables
   - OrganiserUsers
   - ReviewerUsers
   - MunicipalityAdminUsers
   - AdvisorUsers

---

## Rechten per Rol

### Admin (Platformbeheerder)

#### Panel Toegang:
- ✅ Admin Panel (volledige toegang)

#### Resources:
- ✅ **Municipality**: Volledige CRUD rechten
- ✅ **Advisory**: Volledige CRUD rechten
- ✅ **Application**: Volledige CRUD rechten
- ✅ **User Management**: Kan alle user types beheren (MunicipalityAdmin, ReviewerMunicipalityAdmin, Advisor, Reviewer, Organiser)
- ✅ **Location**: Volledige CRUD rechten
- ✅ **MunicipalityVariable**: Volledige rechten
- ✅ **DefaultAdviceQuestion**: Volledige rechten
- ✅ **Zaak**: Kan alle zaken bekijken en activiteiten inzien
- ✅ **Organisation**: Volledige CRUD rechten
- ✅ **Content Management**: Kan Organiser Panel en Welcome content bewerken

#### Specifieke Rechten:
- Kan gebruikers aanmaken, bewerken, verwijderen, restoren en force deleten
- Kan MunicipalityAdmin gebruikers bewerken
- Kan geen andere Admin gebruikers verwijderen
- Toegang tot alle document vertrouwelijkheidsniveaus
- Kan activiteiten van alle zaken bekijken

### MunicipalityAdmin (Gemeentelijk beheerder)

#### Panel Toegang:
- ✅ Municipality Panel (tenant-based, alleen eigen gemeentes)

#### Resources:
- ✅ **Settings Cluster**: Volledige toegang
- ✅ **MunicipalityAdminUser**: Kan aanmaken, bewerken en verwijderen binnen eigen gemeentes
- ✅ **ReviewerUser**: Kan reviewers beheren binnen eigen gemeentes
- ✅ **Advisory**: Kan advisories beheren waar alle gemeentes aan gekoppeld zijn waartoe de admin toegang heeft
- ✅ **Location**: CRUD binnen eigen gemeentes
- ✅ **MunicipalityVariable**: Beheer binnen eigen gemeente
- ✅ **DefaultAdviceQuestion**: Beheer binnen eigen gemeente
- ✅ **Zaak**: Kan zaken bekijken en activiteiten inzien binnen eigen gemeentes

#### Specifieke Rechten:
- Kan alleen Reviewer gebruikers in eigen gemeentes beheren
- Kan Municipality record niet bewerken (alleen Admin)
- Kan andere MunicipalityAdmin gebruikers beheren (create, update, delete, restore, forceDelete)
- Toegang tot document vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk, Confidentieel
- Kan activiteiten bekijken van zaken in eigen gemeentes
- Kan geen gebruikers force deleten (alleen Admin)

### ReviewerMunicipalityAdmin (Behandelaar en gemeentelijk beheerder)

#### Panel Toegang:
- ✅ Municipality Panel (tenant-based, alleen eigen gemeentes)

#### Resources:
- ✅ **Settings Cluster**: Volledige toegang
- ✅ **MunicipalityAdminUser**: Kan aanmaken en bewerken
- ✅ **ReviewerUser**: Kan beheren binnen eigen gemeentes
- ✅ **Advisory**: Kan advisories bekijken en bewerken waar alle gemeentes van de admin bij horen
- ✅ **Location**: CRUD binnen eigen gemeentes
- ✅ **MunicipalityVariable**: Beheer binnen eigen gemeente
- ✅ **DefaultAdviceQuestion**: Beheer binnen eigen gemeente
- ✅ **Zaak**: Kan zaken bekijken, beheren en activiteiten inzien binnen eigen gemeentes

#### Specifieke Rechten:
- Combineert rechten van MunicipalityAdmin en Reviewer
- Kan zaken behandelen en toewijzen aan zichzelf
- Kan advisories alleen verwijderen als MunicipalityAdmin (niet als ReviewerMunicipalityAdmin)
- Toegang tot document vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk, Confidentieel
- Kan activiteiten bekijken van zaken in eigen gemeentes
- Kan ReviewerUser verwijderen binnen eigen gemeentes

### Reviewer (Behandelaar)

#### Panel Toegang:
- ✅ Municipality Panel (tenant-based, alleen eigen gemeentes)

#### Resources:
- ❌ **Settings Cluster**: Geen toegang
- ✅ **Zaak**: Kan zaken bekijken en beheren binnen eigen gemeentes
- ✅ **AdvisorUser**: Kan adviseurs bekijken

#### Specifieke Rechten:
- Kan zaken in behandeling nemen
- Kan werkvoorraad beheren (nieuwe zaken, eigen zaken, alle zaken)
- Geen toegang tot gebruikers- of gemeente-instellingen
- Toegang tot document vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk, Confidentieel
- Kan activiteiten bekijken van zaken in eigen gemeentes

### Advisor (Adviesdienst medewerker)

#### Panel Toegang:
- ✅ Advisor Panel (tenant-based, alleen eigen adviesdiensten)

#### Resources:
- ✅ **Zaak**: Kan zaken bekijken waarvoor advies gevraagd is
- ✅ **AdviceThread**: Kan adviesthreads beheren en beantwoorden
- ✅ **Settings Cluster** (alleen als Advisory Admin): Gebruikers beheer

#### Specifieke Rechten Advisory Admin:
- Kan AdvisorUsers beheren binnen eigen advisory
- Kan andere adviseurs bewerken en verwijderen binnen shared advisories
- Toegang tot Settings cluster

#### Specifieke Rechten Advisory Member:
- Kan adviesaanvragen bekijken en beantwoorden
- Kan zich toewijzen aan adviesthreads
- Kan geen gebruikers beheren
- Toegang tot document vertrouwelijkheidsniveaus: Zaakvertrouwelijk, Vertrouwelijk

#### Algemene Rechten:
- Werkvoorraad management (nieuwe adviesaanvragen, toegewezen aan mij, alle)
- Kan zichzelf en andere adviseurs verwijderen binnen de advisory
- Kan activiteiten bekijken van zaken waarvoor advies gevraagd is
- Kan AdvisorUsers bekijken

### Organiser (Organisator)

#### Panel Toegang:
- ✅ Organiser Panel (tenant-based, alleen eigen organisaties)

#### Resources:
- ✅ **Zaak**: Kan eigen zaken bekijken (van eigen organisatie)
- ✅ **OrganiserThread**: Kan communiceren over zaken
- ✅ **Settings Cluster** (alleen als Organisation Admin, niet voor Personal organisations): Gebruikers beheer
- ✅ **Organisation**: Kan organisaties aanmaken

#### Specifieke Rechten Organisation Admin:
- Kan OrganiserUsers beheren binnen eigen organisatie
- Kan organisatie-instellingen bewerken
- Kan Business organisaties bewerken en verwijderen
- Kan andere gebruikers uitnodigen
- Kan rol van andere leden wijzigen
- Toegang tot Settings cluster

#### Specifieke Rechten Organisation Member:
- Kan zaken bekijken van de organisatie
- Kan communiceren via OrganiserThreads
- Kan geen organisatie-instellingen wijzigen
- Kan geen gebruikers beheren

#### Algemene Rechten:
- Kan organisaties aanmaken (Business en Personal)
- Personal organisations kunnen niet bewerkt of verwijderd worden
- Kan alleen zaken zien van organisaties waar ze lid van zijn
- Toegang tot document vertrouwelijkheidsniveau: Zaakvertrouwelijk
- Kan geen activiteiten bekijken van zaken

#### Beperkingen:
- Geen toegang tot Settings cluster voor Personal organisations
- Kan geen zaken aanmaken, bewerken of verwijderen
- Kan geen events aanmaken of bewerken

---

## Document Vertrouwelijkheidsniveaus

Het systeem hanteert **3 vertrouwelijkheidsniveaus** voor documenten:

### 1. Zaakvertrouwelijk (Openbaar)
**Toegang voor rollen:**
- Reviewer
- Advisor
- Organiser

**Beschrijving**: Minst vertrouwelijk niveau, toegankelijk voor organisatoren en adviseurs

### 2. Vertrouwelijk
**Toegang voor rollen:**
- Reviewer
- Advisor

**Beschrijving**: Middenniveau, niet toegankelijk voor organisatoren

### 3. Confidentieel
**Toegang voor rollen:**
- Reviewer

**Beschrijving**: Hoogst vertrouwelijk niveau, alleen voor behandelaars

### Toegangsmatrix per Rol:

| Rol                        | Zaakvertrouwelijk | Vertrouwelijk | Confidentieel |
|----------------------------|-------------------|---------------|---------------|
| Admin                      | ✅                | ✅            | ✅            |
| MunicipalityAdmin          | ✅                | ✅            | ✅            |
| ReviewerMunicipalityAdmin  | ✅                | ✅            | ✅            |
| Reviewer                   | ✅                | ✅            | ✅            |
| Advisor                    | ✅                | ✅            | ❌            |
| Organiser                  | ✅                | ❌            | ❌            |

---

## Soft Delete en Restore Rechten

### Admin
- Kan alle user types (behalve andere Admins) soft deleten
- Kan alle user types restoren
- Kan alle user types force deleten (permanent verwijderen)

### MunicipalityAdmin
- Kan Reviewer gebruikers in eigen gemeentes soft deleten
- Kan Reviewer gebruikers in eigen gemeentes restoren
- Kan MunicipalityAdmin gebruikers beheren (delete, restore, forceDelete)
- **Kan geen gebruikers force deleten**

### Soft-deleted gebruikers
- Kunnen zelf geen acties meer uitvoeren
- Kunnen geen andere gebruikers beheren
- Kunnen niet inloggen

---

## Invite Systeem

### Admin Invites
- Alleen Admin kan invites versturen voor:
  - MunicipalityAdmin
  - ReviewerMunicipalityAdmin
  - Advisor
  - Reviewer

### Municipality Invites
- MunicipalityAdmin en ReviewerMunicipalityAdmin kunnen invites versturen voor:
  - MunicipalityAdmin (binnen eigen gemeente)
  - Reviewer (binnen eigen gemeente)

### Advisory Invites
- Advisory Admins kunnen adviseurs uitnodigen
- Alleen binnen eigen advisory

### Organisation Invites
- Organisation Admins kunnen leden uitnodigen
- Alleen voor Business organisations (niet voor Personal)

---

## Tenant Ownership

### Municipality Panel
- **Tenant**: Municipality
- **Ownership Relation**: `municipalities` (many-to-many via pivot table)
- Gebruikers kunnen toegang hebben tot meerdere gemeentes

### Organiser Panel
- **Tenant**: Organisation
- **Ownership Relation**: `organisations` (many-to-many via pivot table met role)
- Gebruikers kunnen lid zijn van meerdere organisaties met verschillende rollen

### Advisor Panel
- **Tenant**: Advisory
- **Ownership Relation**: `advisories` (many-to-many via pivot table met role)
- Gebruikers kunnen tot meerdere advisories behoren met verschillende rollen

---

## Navigatie Items per Panel

### Municipality Panel Navigatie
1. **Nieuwe zaken** - Zaken zonder behandelaar
2. **Mijn werkvoorraad** - Aan gebruiker toegewezen zaken
3. **Alle zaken** - Overzicht van alle zaken
4. **Evenementen kalender** - Kalender weergave
5. **Evenementen lijst** - Tabel weergave
6. **Settings** (alleen MunicipalityAdmin/ReviewerMunicipalityAdmin)

### Advisor Panel Navigatie
1. **Nieuwe adviesaanvragen** - Threads zonder toegewezen adviseurs
2. **Mijn adviesaanvragen** - Aan gebruiker toegewezen threads
3. **Alle adviesaanvragen** - Overzicht van alle adviesthreads
4. **Settings** (alleen Advisory Admins)

### Organiser Panel Navigatie
1. **Dashboard** - Overzicht met intro en shortlink widgets
2. **Zaken** - Eigen zaken van de organisatie
3. **Settings** (alleen Organisation Admins, niet voor Personal)

### Admin Panel Navigatie
1. **Dashboard**
2. **Municipalities** - Gemeente beheer
3. **Advisories** - Adviesdienst beheer
4. **Applications** - Aanvraag beheer
5. **Admin Users** - Admin gebruikers
6. **Zaak** - Zaak overzicht
7. **Content Management** - Organiser Panel en Welcome content

---

## Belangrijke Beperkingen

### Algemeen
- Events kunnen door niemand aangemaakt, bewerkt of verwijderd worden (read-only)
- Zaken kunnen niet via UI aangemaakt worden (externe integratie)
- Soft-deleted gebruikers kunnen geen acties uitvoeren

### Per Rol
- **Reviewer**: Geen toegang tot gemeente-instellingen
- **Organiser**: Geen toegang tot Settings voor Personal organisations
- **Advisory Member**: Kan geen gebruikers beheren
- **Organisation Member**: Kan geen organisatie-instellingen wijzigen
- **MunicipalityAdmin**: Kan municipality records niet bewerken

### Organisaties
- Personal organisations kunnen niet bewerkt of verwijderd worden
- Alleen Business organisations hebben toegang tot Settings
- Organisation Admins kunnen organisatie verwijderen, Members niet

---

## 2FA (Two-Factor Authentication)

Alle panels ondersteunen 2FA via app-based authentication:
- Configureerbaar via `config('app.require_2fa')`
- Ondersteunt recovery codes
- Verplicht wanneer geconfigureerd

---

## Database Notificaties

Alle panels hebben ondersteuning voor database notificaties:
- Real-time meldingen voor gebruikers
- Gebruikt voor invite systeem
- Gebruikt voor zaak updates
- Gebruikt voor thread berichten

---

## Laatst bijgewerkt
8 december 2025

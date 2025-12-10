# Rollen Overzicht

Dit document geeft een overzicht van alle rollen binnen Eventloket.

## Inhoudsopgave

1. [Gebruikersrollen](#gebruikersrollen)
2. [Organisatierollen](#organisatierollen)
3. [Adviesdienstrollen](#adviesdienstrollen)
4. [Panels en Toegang](#panels-en-toegang)

---

## Gebruikersrollen

Het systeem kent **6 hoofdrollen**

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

Binnen organisaties bestaan **2 rollen** 

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

Binnen adviesdiensten bestaan **2 rollen**

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

## Laatst bijgewerkt
8 december 2025

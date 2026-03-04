# Lokale ontwikkelomgeving — Full Architecture

Dit document beschrijft hoe je het volledige applicatielandschap van Eventloket lokaal opstart inclusief alle ZGW-services.

## Applicaties in de stack

| Service | URL | Admin |
|---|---|---|
| **Eventloket** (Laravel) | http://localhost:80 | — |
| **Open Zaak** | http://localhost:8001 | http://localhost:8001/admin |
| **Open Notificaties** | http://localhost:8002 | http://localhost:8002/admin |
| **Open Forms** | http://localhost:8003 | http://localhost:8003/admin |
| **Objecttypes API** | http://localhost:8004 | http://localhost:8004/admin |
| **Objects API** | http://localhost:8005 | http://localhost:8005/admin |
| **Mailpit** (mailcatcher) | http://localhost:8025 | — |

Alle Django admin interfaces: gebruikersnaam `admin`, wachtwoord `admin`.

---

## Vereisten

| Tool | Versie |
|---|---|
| Docker Desktop | ≥ 4.x |
| RAM | ≥ 8 GB beschikbaar voor Docker |
| Disk | ≥ 4 GB vrije ruimte |

---

## Eenmalige setup

### 1. Clone en voorbereiding

```bash
git clone <repo-url> eventloket
cd eventloket
cp .env.example .env
```

### 2. .env instellen voor de full-architecture stack

Zet de volgende waarden in je `.env` (de commentaarblokken onderin `.env.example` bevatten alle waarden):

```dotenv
# Database → PostgreSQL (PostGIS vereist voor zaakgeometrie)
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=eventloket
DB_USERNAME=sail
DB_PASSWORD=password

# Poorten — voorkomt conflict bij gelijktijdig gebruik van MySQL en PostgreSQL
FORWARD_MYSQL_PORT=3306
FORWARD_PGSQL_PORT=5432
FORWARD_REDIS_PORT=6379

# ZGW Services (interne Docker namen = DNS binnen het sail netwerk)
OPENZAAK_URL=http://open-zaak:8000/
OPENZAAK_CLIENT_ID=eventloket
OPENZAAK_CLIENT_SECRET=local-secret-eventloket
OPENZAAK_CATALOGI_URL=http://open-zaak:8000/catalogi/api/v1/catalogussen/  # vul UUID in na setup

OBJECTSAPI_URL=http://objects-api:8000
OBJECTSAPI_TOKEN=local-token-eventloket
OBJECTSAPI_OBJECTSTYPE_TAAK_URL=http://objecttypes-api:8000/api/v2/objecttypes/<taak-uuid>  # zie setup output

OPEN_FORMS_BASE_URL=http://open-forms:8000
OPEN_FORMS_MAIN_FORM_UUID=  # vul in na aanmaken formulier in Open Forms
```

### 3. Stack opstarten

```bash
docker compose -f docker-compose-full-architecture.yml up -d
```

De eerste keer duurt dit ~5-10 minuten. Alle ZGW services downloaden hun images en voeren database-migraties uit.

Controleer de status:
```bash
docker compose -f docker-compose-full-architecture.yml ps
```

Wacht totdat alle services `running` of `healthy` zijn.

### 4. Eenmalige cross-service configuratie

```bash
bash docker/setup-local-services.sh
```

Dit script configureert automatisch:
- Open Zaak ↔ Open Notificaties verbinding (ZGW JWT credentials)
- API-tokens voor Objecttypes en Objects API
- ZGW service registraties in Open Forms
- Laravel Passport OAuth2 client voor inbound notificaties

Aan het einde print het script de `.env`-waarden die je moet aanvullen (o.a. de Taak objecttype URL en het Passport client secret).

### 5. Na het setup script

#### 5a. Catalogus aanmaken in Open Zaak

1. Ga naar http://localhost:8001/admin/catalogi/catalogus/
2. Voeg een nieuwe Catalogus toe (domein, rsin ingeven)
3. Kopieer de UUID uit de URL
4. Stel `OPENZAAK_CATALOGI_URL` in je `.env` in op de volledige URL:
   ```
   OPENZAAK_CATALOGI_URL=http://open-zaak:8000/catalogi/api/v1/catalogussen/<uuid>
   ```

#### 5b. Zaaktypen synchroniseren

```bash
docker compose -f docker-compose-full-architecture.yml exec laravel.test php artisan openzaak:sync-zaaktypen
```

#### 5c. Open Notificaties webhook authenticatie bijwerken

Het setup script registreert een placeholder webhook abonnement. Update het met het echte Passport secret:

1. Ga naar http://localhost:8002/admin/datamodel/abonnement/
2. Open het abonnement voor `http://laravel.test/api/open-notifications/listen`
3. Stel de `auth` header in op `Bearer <passport_client_secret>` (zie output van setup script)

---

## Dagelijks gebruik

### Stack starten en stoppen

```bash
# Starten
docker compose -f docker-compose-full-architecture.yml up -d

# Stoppen (data blijft behouden via Docker volumes)
docker compose -f docker-compose-full-architecture.yml stop

# Volledig verwijderen inclusief data
docker compose -f docker-compose-full-architecture.yml down -v
```

### Logs bekijken

```bash
# Alle services
docker compose -f docker-compose-full-architecture.yml logs -f

# Specifieke service
docker compose -f docker-compose-full-architecture.yml logs -f open-zaak
docker compose -f docker-compose-full-architecture.yml logs -f laravel.test
```

### Management commands uitvoeren

```bash
# Laravel
docker compose -f docker-compose-full-architecture.yml exec laravel.test php artisan <command>

# Open Zaak
docker compose -f docker-compose-full-architecture.yml exec open-zaak python /app/src/manage.py <command>

# Open Notificaties
docker compose -f docker-compose-full-architecture.yml exec open-notificaties python /app/src/manage.py <command>
```

---

## Architectuur & credential overzicht

```
Laravel (.env: OPENZAAK_CLIENT_ID=eventloket)
  │─ JWT ──────────────────────────────────────────────────────► Open Zaak :8001
  │─ Token (OBJECTSAPI_TOKEN) ─────────────────────────────────► Objects API :8005

Open Zaak
  │─ JWT (client_id=open-zaak, secret=local-secret-oz-to-on) ──► Open Notificaties :8002
  └─ Kanalen registreren (register_kanalen) ────────────────────► Open Notificaties

Open Notificaties
  │─ JWT (client_id=open-notificaties) ────────────────────────► Open Zaak AC API
  └─ POST webhook ─────────────────────────────────────────────► Laravel :80/api/open-notifications/listen

Open Forms :8003
  │─ JWT (client_id=open-forms) ───────────────────────────────► Open Zaak :8001
  │─ Token (local-token-of-to-obj) ────────────────────────────► Objects API :8005
  └─ Token (local-token-of-to-objt) ──────────────────────────► Objecttypes API :8004

Objects API :8005
  └─ Token (local-token-obj-to-objt) ─────────────────────────► Objecttypes API :8004
```

### Vaste lokale credentials (niet voor productie)

| Van → Naar | Type | client_id / token |
|---|---|---|
| Open Zaak → Open Notificaties | ZGW JWT secret | `local-secret-oz-to-on` |
| Open Notificaties → Open Zaak AC | ZGW JWT secret | `local-secret-on-to-oz` |
| Open Forms → Open Zaak | ZGW JWT secret | `local-secret-of-to-oz` |
| Eventloket → Open Zaak | ZGW JWT secret | `local-secret-eventloket` |
| Open Forms → Objects API | API Token | `local-token-of-to-obj` |
| Open Forms → Objecttypes API | API Token | `local-token-of-to-objt` |
| Eventloket → Objects API | API Token | `local-token-eventloket` |
| Objects API → Objecttypes API | API Token | `local-token-obj-to-objt` |

---

## Redis DB-partitionering

Elke service gebruikt een aparte Redis database index om key-conflicten te vermijden:

| Service | Redis DB |
|---|---|
| Laravel (queue/cache) | `/0` |
| Open Zaak | `/1` |
| Open Notificaties | `/2` |
| Open Forms | `/3` |
| Objecttypes API | `/4` |
| Objects API | `/5` |

---

## Troubleshooting

### Service start niet op / is unhealthy

```bash
docker compose -f docker-compose-full-architecture.yml logs <service-naam>
```

**Open Zaak unhealthy**: De healthcheck is een HTTP-liveness check op `/admin/login/`. Als Open Zaak niet healthy wordt, controleer of de database-migraties slaagden.

**Port already in use**: Als poort 3306 of 5432 al in gebruik is op je host, pas dan in `.env` aan:
```dotenv
FORWARD_MYSQL_PORT=3307
FORWARD_PGSQL_PORT=5433
```

### Notificaties komen niet aan bij Laravel

1. Controleer of de Open Notificaties Celery worker draait:
   ```bash
   docker compose -f docker-compose-full-architecture.yml ps open-notificaties-worker
   ```
2. Controleer het webhook abonnement in Open Notificaties admin (Authorization header ingevuld?)
3. Bekijk de Laravel logs: `docker compose ... logs laravel.test | grep notification`

### Open ZaakJWT authentication errors (401/403)

JWT tokens verlopen na 1 uur. De `woweb/openzaak` package genereert automatisch nieuwe tokens per request, dus dit zou geen probleem moeten zijn. Als het toch optreedt:
- Controleer of `OPENZAAK_CLIENT_ID` en `OPENZAAK_CLIENT_SECRET` overeenkomen met de geregistreerde Applicatie in Open Zaak
- Controleer of de Applicatie de juiste scopes heeft (beheer autorisaties in admin)

### Open Forms toont geen formulieren

- Controleer of `OPEN_FORMS_MAIN_FORM_UUID` ingesteld is
- Controleer of het formulier gepubliceerd is in Open Forms admin

### Reset & herstart configuratie

Om de cross-service configuratie opnieuw uit te voeren (bijv. na `docker compose down -v`):

```bash
docker compose -f docker-compose-full-architecture.yml down -v
docker compose -f docker-compose-full-architecture.yml up -d
bash docker/setup-local-services.sh
```

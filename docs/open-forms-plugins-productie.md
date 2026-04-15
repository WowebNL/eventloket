# Open Forms Eventloket Plugins — Productie Installatie

## Overzicht

Eventloket gebruikt twee custom plugins voor Open Forms:

| Plugin | Repository | Functie |
|--------|-----------|---------|
| `openforms-eventloket-auth` | [WowebNL/openforms-eventloket-auth](https://github.com/WowebNL/openforms-eventloket-auth) | Authenticatie: verifieert Eventloket-gebruikers via HMAC tokens |
| `openforms-eventloket-prefill` | [WowebNL/openforms-eventloket-prefill](https://github.com/WowebNL/openforms-eventloket-prefill) | Prefill: matcht Objects API data automatisch op formuliervelden |

## Installatie via custom Docker image

### 1. Dockerfile

Het bestand `docker/open-forms/Dockerfile` in de Eventloket repo bevat de configuratie:

```dockerfile
FROM openformulieren/open-forms:3.3.9

USER root
COPY openforms-eventloket-auth /tmp/eventloket-auth
COPY openforms-eventloket-prefill /tmp/eventloket-prefill
RUN pip install /tmp/eventloket-auth /tmp/eventloket-prefill
USER maykin
```

Voor productie kun je de plugins direct vanuit GitHub installeren i.p.v. lokale COPY:

```dockerfile
FROM openformulieren/open-forms:3.3.9

USER root
RUN pip install \
    git+https://github.com/WowebNL/openforms-eventloket-auth.git@main \
    git+https://github.com/WowebNL/openforms-eventloket-prefill.git@main
USER maykin
```

Of pin op een specifieke versie/tag:

```dockerfile
RUN pip install \
    git+https://github.com/WowebNL/openforms-eventloket-auth.git@v0.1.0 \
    git+https://github.com/WowebNL/openforms-eventloket-prefill.git@v0.1.0
```

### 2. Image bouwen en pushen

```bash
# Bouw de image
docker build -t registry.example.com/eventloket/open-forms:3.3.9-eventloket \
    -f docker/open-forms/Dockerfile .

# Push naar private registry
docker push registry.example.com/eventloket/open-forms:3.3.9-eventloket
```

### 3. Image gebruiken

Vervang in de productie docker-compose of orchestrator de officiële image door de custom image:

```yaml
# Was:
open-forms:
    image: openformulieren/open-forms:3.3.9

# Wordt:
open-forms:
    image: registry.example.com/eventloket/open-forms:3.3.9-eventloket
```

Doe hetzelfde voor de `open-forms-worker` service — die moet dezelfde image gebruiken.

### 4. Environment variabelen

Voeg de volgende environment variabelen toe aan zowel de `open-forms` als `open-forms-worker` service:

```yaml
environment:
    # Activeer de plugins
    - OPEN_FORMS_EXTENSIONS=openforms_eventloket_auth,openforms_eventloket_prefill

    # Auth plugin configuratie
    - EVENTLOKET_AUTH_VALIDATE_URL=https://eventloket.example.com/api/validate-eventloket-token
    - EVENTLOKET_AUTH_TOKEN_URL=https://eventloket.example.com/oauth/token
    - EVENTLOKET_AUTH_CLIENT_ID=<passport-client-id>
    - EVENTLOKET_AUTH_CLIENT_SECRET=<passport-client-secret>
```

De `EVENTLOKET_AUTH_CLIENT_ID` en `EVENTLOKET_AUTH_CLIENT_SECRET` zijn de credentials van een Laravel Passport client credentials grant client. Maak deze aan in Eventloket:

```bash
php artisan passport:client --client --name=openforms-auth-plugin
```

### 5. Open Forms admin configuratie

Na deployment moeten de plugins geconfigureerd worden in de Open Forms admin:

#### Auth plugin

1. Ga naar het formulier in de Open Forms admin
2. Voeg `eventloket` toe als authenticatie backend
3. Optioneel: stel `auto_login_authentication_backend` in op `eventloket`

#### Prefill plugin

1. Ga naar de formuliervariabelen van het formulier
2. Configureer een variabele met:
   - `prefill_plugin`: `eventloket_prefill`
   - `prefill_options`: `{"objects_api_group": "<naam van de Objects API groep>"}`

#### Service fetch voor contactgegevens

1. Maak een `ServiceFetchConfiguration` aan:
   - Naam: `Eventloket sessie`
   - Service: de Eventloket API service
   - Pad: `api/validate-eventloket-token`
   - Methode: `GET`
   - Query parameters: `{"token": ["{{ eventloket_token }}"]}`
   - Data mapping: JsonLogic `{"var": "data"}`

2. Koppel deze aan de `eventloketSession` formuliervariabele

## Updaten van plugins

Bij een update van de plugins:

1. Bouw een nieuwe image met de geüpdatete plugin versies
2. Push naar de registry
3. Herstart de `open-forms` en `open-forms-worker` containers

```bash
# Bouw met nieuwe versie
docker build -t registry.example.com/eventloket/open-forms:3.3.9-eventloket \
    -f docker/open-forms/Dockerfile .

docker push registry.example.com/eventloket/open-forms:3.3.9-eventloket

# Herstart services
docker compose up -d open-forms open-forms-worker
```

## Updaten van Open Forms versie

Bij een update van Open Forms zelf:

1. Wijzig de `FROM` regel in het Dockerfile naar de nieuwe versie
2. Test of de plugins compatibel zijn met de nieuwe versie
3. Bouw en push de nieuwe image

```dockerfile
# Update de base image versie
FROM openformulieren/open-forms:3.4.0
```

## Eventloket configuratie

Aan de Eventloket (Laravel) kant moet het volgende geconfigureerd zijn:

```env
# .env
OPEN_FORMS_TOKEN_SIGNING_KEY=<willekeurige-lange-string>
OPEN_FORMS_FORM_SLUG=<slug-van-het-formulier>
OPEN_FORMS_BASE_URL=https://openforms.example.com
OPEN_FORMS_MAIN_FORM_UUID=<uuid-van-het-formulier>
OPEN_FORMS_PREFILL_OBJECT_TYPE_URL=<url-van-het-objecttype>
OPEN_FORMS_PREFILL_OBJECT_TYPE_VERSION=1
```

## Verificatie

Na deployment, verifieer dat de plugins correct geladen zijn:

```bash
docker compose exec open-forms python src/manage.py shell -c "
from openforms.authentication.registry import register as auth_reg
from openforms.prefill.registry import register as prefill_reg
print('Auth plugins:', [p for p in auth_reg._registry if 'eventloket' in p])
print('Prefill plugins:', [p for p in prefill_reg._registry if 'eventloket' in p])
"
```

Verwachte output:
```
Auth plugins: ['eventloket']
Prefill plugins: ['eventloket_prefill']
```

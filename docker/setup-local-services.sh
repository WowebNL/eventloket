#!/usr/bin/env bash
# =============================================================================
# Eventloket — Local Full-Architecture Setup Script
# =============================================================================
#
# Run ONCE after starting the full Docker Compose stack:
#
#   docker compose -f docker-compose-full-architecture.yml up -d
#   bash docker/setup-local-services.sh
#
# What this script does:
#   1.  Waits for all services to be reachable
#   2.  Configures Open Zaak  → connects to Open Notificaties, creates applications
#   3.  Configures Open Notificaties → connects to Open Zaak AC, registers webhooks
#   4.  Configures Objecttypes API → creates API tokens, Taak + Aanvraag objecttypen
#   4b. Creates Laravel Passport client 'open-forms-api' for Open Forms service fetches
#   5.  Configures Objects API     → creates API tokens, links to Objecttypes
#   5c. Configures Open Forms      → ZGW services, Objects/Objecttypes APIs, ZGW API group,
#       + Eventloket API service (for form variable service fetches via OAuth2 Bearer token)
#   6.  Configures Open Notificaties — widens auth column to TEXT, registers Eventloket abonnement
#   7.  Configures Laravel         → creates Passport client for Open Notificaties, obtains JWT
#       via client_credentials, updates abonnement auth with: Bearer <JWT>
#   8.  Imports Open Zaak catalogus (VRZL) → zaaktypen, statustypen, informatieobjecttypen etc.
#   9.  Imports Open Forms formulier (Evenementformulier) via REST API
#       (patches staging Eventloket service URL → local Eventloket API service URL)
#
# After completion, update your .env with the printed OBJECTSAPI_OBJECTSTYPE_TAAK_URL
# and OPEN_FORMS_MAIN_FORM_UUID values.
#
# =============================================================================

set -euo pipefail

# ─── Compose project name ────────────────────────────────────────────────────
COMPOSE_FILE="docker-compose-full-architecture.yml"
COMPOSE_CMD="docker compose -f ${COMPOSE_FILE}"

# ─── Fixed local dev credentials (must match between services) ───────────────
# Open Zaak ↔ Open Notificaties
OZ_CLIENT_ID="open-zaak"
OZ_TO_ON_SECRET="local-secret-oz-to-on"

ON_CLIENT_ID="open-notificaties"
ON_TO_OZ_SECRET="local-secret-on-to-oz"

# Open Forms → Open Zaak
OF_CLIENT_ID="open-forms"
OF_TO_OZ_SECRET="local-secret-of-to-oz"

# Eventloket (Laravel) → Open Zaak
EL_CLIENT_ID="eventloket"
# Must meet ReallySimpleJWT requirements: ≥12 chars, uppercase, lowercase, digit, special char from *&!@%^#$
EL_TO_OZ_SECRET="L0c@l!Eventloket2026"

# Objects API → Open Zaak (for notifications)
OBJ_CLIENT_ID="objects-api"
OBJ_TO_OZ_SECRET="local-secret-obj-to-oz"

# Token authentication (Objects ↔ Objecttypes ↔ Open Forms ↔ Laravel)
TOKEN_OBJT_INTERNAL="local-token-obj-to-objt"   # Objects API → Objecttypes API
TOKEN_OF_TO_OBJ="local-token-of-to-obj"         # Open Forms → Objects API
TOKEN_OF_TO_OBJT="local-token-of-to-objt"       # Open Forms → Objecttypes API
TOKEN_EL_TO_OBJ="local-token-eventloket"        # Laravel → Objects API (= OBJECTSAPI_TOKEN)
TOKEN_OZ_TO_OBJ="local-token-oz-to-obj"         # Open Zaak → Objects API (for zaakobject URL validation)

# ─── Colours ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC} $*"; }
success() { echo -e "${GREEN}[OK]${NC}   $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $*"; }
error()   { echo -e "${RED}[ERR]${NC}  $*"; }

# ─── Helper: wait until HTTP endpoint responds ───────────────────────────────
wait_for_http() {
    local name="$1" url="$2" retries=40
    info "Waiting for ${name} at ${url} ..."
    for i in $(seq 1 ${retries}); do
        if curl -sf --max-time 5 "${url}" > /dev/null 2>&1; then
            success "${name} is up"
            return 0
        fi
        sleep 5
    done
    error "${name} did not become available at ${url} after $((retries * 5))s"
    exit 1
}

# ─── Helper: run python code in a service container ──────────────────────────
run_python() {
    local service="$1" code="$2"
    ${COMPOSE_CMD} exec -T "${service}" python /app/src/manage.py shell -c "${code}"
}

run_manage() {
    local service="$1"; shift
    ${COMPOSE_CMD} exec -T "${service}" python /app/src/manage.py "$@"
}

# ─── Helper: run python code in services that use /app/src/manage.py ────────
# All ZGW services (objecttypes-api, objects-api, open-forms, etc.) use /app/src/
run_python_root() {
    local service="$1" code="$2"
    ${COMPOSE_CMD} exec -T "${service}" python /app/src/manage.py shell -c "${code}"
}

run_manage_root() {
    local service="$1"; shift
    ${COMPOSE_CMD} exec -T "${service}" python /app/src/manage.py "$@"
}

# =============================================================================
echo ""
echo -e "${CYAN}══════════════════════════════════════════════════════${NC}"
echo -e "${CYAN}  Eventloket — Local Service Configuration${NC}"
echo -e "${CYAN}══════════════════════════════════════════════════════${NC}"
echo ""

# =============================================================================
# 0. Wait for services
# =============================================================================
info "Step 0: Waiting for all services to be reachable..."

wait_for_http "Open Zaak"        "http://localhost:8001/admin/login/"
wait_for_http "Open Notificaties" "http://localhost:8002/admin/login/"
wait_for_http "Objecttypes API"  "http://localhost:8004/admin/login/"
wait_for_http "Objects API"      "http://localhost:8005/admin/login/"
wait_for_http "Open Forms"       "http://localhost:8003/admin/login/"
wait_for_http "Laravel app"      "http://localhost:80"

# =============================================================================
# 1. Configure Open Zaak
# =============================================================================
echo ""
info "Step 1: Configuring Open Zaak..."

run_python "open-zaak" "
import os
from django.db import transaction

# ── 1a. Service: Open Notificaties (NRC) ────────────────────────────────────
from zgw_consumers.models import Service
from zgw_consumers.constants import APITypes, AuthTypes

nrc_service, created = Service.objects.update_or_create(
    slug='open-notificaties',
    defaults={
        'label': 'Open Notificaties',
        'api_type': APITypes.nrc,
        'api_root': 'http://open-notificaties:8000/api/v1/',
        'auth_type': AuthTypes.zgw,
        'client_id': '${OZ_CLIENT_ID}',
        'secret': '${OZ_TO_ON_SECRET}',
        'user_id': '${OZ_CLIENT_ID}',
        'user_representation': 'Open Zaak',
    }
)
print(f'NRC service: {\"created\" if created else \"updated\"} -> {nrc_service.api_root}')

# ── 1b. NotificatiesConfig ───────────────────────────────────────────────────
try:
    from notifications_api_common.models import NotificationsConfig
    notif_config = NotificationsConfig.get_solo()
    notif_config.notifications_api_service = nrc_service
    notif_config.save()
    print('NotificationsConfig: linked to Open Notificaties service')
except Exception as e:
    print(f'NotificationsConfig: {e} (may need manual config at /admin/notifications/notificationsconfig/)')

# ── 1c. Applicaties ──────────────────────────────────────────────────────────
from openzaak.components.autorisaties.models import Applicatie

apps_to_create = [
    {
        'label': 'Open Notificaties',
        'client_ids': ['${ON_CLIENT_ID}'],
        'secret': '${ON_TO_OZ_SECRET}',
        'scopes_by_component': {
            'autorisaties': ['autorisaties.lezen'],
            'notificaties': ['notificaties.consumeren', 'notificaties.publiceren'],
        },
    },
    {
        'label': 'Open Zaak (self — notificaties)',
        'client_ids': ['${OZ_CLIENT_ID}'],
        'secret': '${OZ_TO_ON_SECRET}',
        'scopes_by_component': {
            'notificaties': ['notificaties.consumeren', 'notificaties.publiceren'],
        },
    },
    {
        'label': 'Open Forms',
        'client_ids': ['${OF_CLIENT_ID}'],
        'secret': '${OF_TO_OZ_SECRET}',
        'scopes_by_component': {
            'catalogi':    ['catalogi.lezen'],
            'zaken':       ['zaken.aanmaken', 'zaken.lezen', 'zaken.bijwerken'],
            'documenten':  ['documenten.aanmaken', 'documenten.lezen'],
        },
    },
    {
        'label': 'Eventloket',
        'client_ids': ['${EL_CLIENT_ID}'],
        'secret': '${EL_TO_OZ_SECRET}',
        'scopes_by_component': {
            'catalogi':   ['catalogi.lezen'],
            'zaken':      ['zaken.aanmaken', 'zaken.lezen', 'zaken.bijwerken', 'zaken.geforceerd-bijwerken'],
            'documenten': ['documenten.aanmaken', 'documenten.lezen', 'documenten.bijwerken'],
            'besluiten':  ['besluiten.aanmaken', 'besluiten.lezen'],
            'autorisaties': ['autorisaties.lezen'],
        },
    },
    {
        'label': 'Objects API',
        'client_ids': ['${OBJ_CLIENT_ID}'],
        'secret': '${OBJ_TO_OZ_SECRET}',
        'scopes_by_component': {
            'notificaties': ['notificaties.consumeren', 'notificaties.publiceren'],
        },
    },
]

for app_data in apps_to_create:
    try:
        app, created = Applicatie.objects.get_or_create(
            label=app_data['label'],
            defaults={
                'client_ids': app_data['client_ids'],
                # heeft_alle_autorisaties=True for local dev — grants all scopes without individual Autorisatie rows
                'heeft_alle_autorisaties': True,
            }
        )
        if not created:
            app.client_ids = app_data['client_ids']
            app.heeft_alle_autorisaties = True
            app.save()
        print(f'Applicatie \"{app_data[\"label\"]}\": {\"created\" if created else \"updated\"}')
    except Exception as e:
        print(f'Applicatie \"{app_data[\"label\"]}\": FAILED — {e}')
        print('  -> Configure manually at http://localhost:8001/admin/autorisaties/applicatie/')

print('Open Zaak applications configured.')

# ── 1d. JWTSecrets — allow callers to authenticate against Open Zaak ─────────
# Without JWTSecrets, Open Zaak cannot validate inbound JWT tokens from ON/OF/EL
from vng_api_common.models import JWTSecret
for app_data in apps_to_create:
    for client_id in app_data['client_ids']:
        js, created = JWTSecret.objects.update_or_create(
            identifier=client_id,
            defaults={'secret': app_data['secret']}
        )
        print(f'JWTSecret for {client_id}: {\"created\" if created else \"updated\"}')
print('Open Zaak JWTSecrets configured.')

# ── 1e. Set Site domain so URL filters work with internal Docker hostnames ────
from django.contrib.sites.models import Site
site = Site.objects.get(id=1)
if site.domain != 'host.docker.internal:8001':
    site.domain = 'host.docker.internal:8001'
    site.name = 'Open Zaak (local)'
    site.save()
    print('Site domain updated to host.docker.internal:8001')
else:
    print('Site domain already set to host.docker.internal:8001')

# ── 1f. External services: Objects API + Objecttypes API ─────────────────────
# NOTE: host.docker.internal is required instead of single-label Docker hostnames
# because Django's URLValidator rejects them; this matches what Open Forms sends.
from zgw_consumers.models import Service
from zgw_consumers.constants import APITypes, AuthTypes
# ── 1f-0. Self-referencing Catalogi service (needed for roltype URL validation in Open Zaak) ──
ztc_self, created = Service.objects.update_or_create(
    slug='open-zaak-catalogi-self',
    defaults={
        'label': 'Open Zaak (Catalogi API — self)',
        'api_type': APITypes.ztc,
        'api_root': 'http://host.docker.internal:8001/catalogi/api/v1/',
        'auth_type': AuthTypes.zgw,
        'client_id': '${EL_CLIENT_ID}',
        'secret': '${EL_TO_OZ_SECRET}',
        'user_id': '${EL_CLIENT_ID}',
        'user_representation': 'Open Zaak self',
    }
)
print(f'Catalogi self-service in Open Zaak: {"created" if created else "updated"} -> {ztc_self.api_root}')

obj_svc, created = Service.objects.update_or_create(
    slug='objects-api',
    defaults={
        'label': 'Objects API',
        'api_type': APITypes.orc,
        'api_root': 'http://host.docker.internal:8005/api/v2/',
        'auth_type': AuthTypes.api_key,
        'header_key': 'Authorization',
        'header_value': 'Token ${TOKEN_OZ_TO_OBJ}',
    }
)
print(f'Objects API service in Open Zaak: {"created" if created else "updated"} -> {obj_svc.api_root}')

objt_svc, created = Service.objects.update_or_create(
    slug='objecttypes-api',
    defaults={
        'label': 'Objecttypes API',
        'api_type': APITypes.orc,
        'api_root': 'http://host.docker.internal:8004/api/v2/',
        'auth_type': AuthTypes.api_key,
        'header_key': 'Authorization',
        'header_value': 'Token ${TOKEN_EL_TO_OBJ}',
    }
)
print(f'Objecttypes API service in Open Zaak: {"created" if created else "updated"} -> {objt_svc.api_root}')
"

# Register notification channels
info "Registering notification channels in Open Zaak..."
run_manage "open-zaak" register_kanalen || warn "register_kanalen failed — run manually: docker compose exec open-zaak python /app/src/manage.py register_kanalen"

success "Open Zaak configured."

# =============================================================================
# 2. Configure Open Notificaties
# =============================================================================
echo ""
info "Step 2: Configuring Open Notificaties..."

run_python "open-notificaties" "
from zgw_consumers.models import Service
from zgw_consumers.constants import APITypes, AuthTypes

# ── 2a. Service: Open Zaak Autorisaties API ──────────────────────────────────
ac_service, created = Service.objects.update_or_create(
    slug='open-zaak-ac',
    defaults={
        'label': 'Open Zaak (Autorisaties API)',
        'api_type': APITypes.ac,
        'api_root': 'http://open-zaak:8000/autorisaties/api/v1/',
        'auth_type': AuthTypes.zgw,
        'client_id': '${ON_CLIENT_ID}',
        'secret': '${ON_TO_OZ_SECRET}',
        'user_id': '${ON_CLIENT_ID}',
        'user_representation': 'Open Notificaties',
    }
)
print(f'AC service: {\"created\" if created else \"updated\"} -> {ac_service.api_root}')

# ── 2b. AutorisatiesConfig ───────────────────────────────────────────────────
try:
    from vng_api_common.authorizations.models import AuthorizationsConfig
    ac_config = AuthorizationsConfig.get_solo()
    ac_config.authorizations_api_service = ac_service
    ac_config.save()
    print('AuthorizationsConfig: linked to Open Zaak AC service')
except Exception as e:
    print(f'AuthorizationsConfig: {e} (check at /admin/authorizations/authorizationsconfig/)')

# ── 2c. Allow Open Zaak to authenticate via JWTSecret (inbound credentials) ──
try:
    from vng_api_common.models import JWTSecret
    cred, created = JWTSecret.objects.update_or_create(
        identifier='${OZ_CLIENT_ID}',
        defaults={'secret': '${OZ_TO_ON_SECRET}'}
    )
    print(f'JWTSecret for open-zaak: {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'JWTSecret: {e}')
    print('  -> Configure manually at http://localhost:8002/admin/ (JWTSecret)')

# ── 2d. Self-reference NRC service ───────────────────────────────────────────
nrc_self, created = Service.objects.update_or_create(
    slug='open-notificaties-self',
    defaults={
        'label': 'Open Notificaties (self)',
        'api_type': APITypes.nrc,
        'api_root': 'http://open-notificaties:8000/api/v1/',
        'auth_type': AuthTypes.zgw,
        'client_id': '${ON_CLIENT_ID}',
        'secret': '${ON_TO_OZ_SECRET}',
        'user_id': '${ON_CLIENT_ID}',
        'user_representation': 'Open Notificaties',
    }
)
print(f'NRC self-service: {\"created\" if created else \"updated\"}')

print('Open Notificaties base configuration done.')
print('NOTE: Webhook subscriptions (abonnementen) will be registered via register_kanalen.')
print('      The Eventloket webhook subscription must be registered after Passport setup (see Step 7).')

# ── 2e. Create kanalen directly (register_kanalen may fail on documentatieLink URL validation) ──
from nrc.datamodel.models import Kanaal
kanalen_names = ['autorisaties', 'besluiten', 'besluittypen', 'documenten', 'informatieobjecttypen', 'zaaktypen', 'zaken', 'objecten']
for naam in kanalen_names:
    k, created = Kanaal.objects.get_or_create(naam=naam)
    print(f'Kanaal {naam}: {\"created\" if created else \"exists\"}')
print('Kanalen configured in Open Notificaties.')

# ── 2f. JWTSecret for Objects API (so it can publish to the objecten kanaal) ──
try:
    from vng_api_common.models import JWTSecret
    cred, created = JWTSecret.objects.update_or_create(
        identifier='${OBJ_CLIENT_ID}',
        defaults={'secret': '${OBJ_TO_OZ_SECRET}'}
    )
    print(f'JWTSecret for objects-api: {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'JWTSecret objects-api: {e}')
"

success "Open Notificaties configured."

# =============================================================================
# 3. Configure Objecttypes API
# =============================================================================
echo ""
info "Step 3: Configuring Objecttypes API..."

run_python_root "objecttypes-api" "
from objecttypes.token.models import TokenAuth

try:
    tokens = [
        {'identifier': 'open-forms',  'token': '${TOKEN_OF_TO_OBJT}',    'label': 'Open Forms'},
        {'identifier': 'objects-api', 'token': '${TOKEN_OBJT_INTERNAL}', 'label': 'Objects API'},
        {'identifier': 'eventloket',  'token': '${TOKEN_EL_TO_OBJ}',     'label': 'Eventloket'},
    ]
    for t in tokens:
        obj, created = TokenAuth.objects.update_or_create(
            token=t['token'],
            defaults={
                'identifier': t.get('identifier', t['label']),
                'contact_person': t['label'],
                'email': t['label'].lower().replace(' ', '') + '@localhost',
            }
        )
        print(f'Token for {t[\"label\"]}: {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'Token creation failed: {e}')
    print('Configure manually at http://localhost:8004/admin/token/tokenauth/add/')
    print('Create tokens: ${TOKEN_OF_TO_OBJT} (Open Forms), ${TOKEN_OBJT_INTERNAL} (Objects API), ${TOKEN_EL_TO_OBJ} (Eventloket)')
" || warn "Objecttypes API token setup had issues — see output above"

# Create the Taak objecttype
info "Creating Taak objecttype..."
TAAK_UUID=$(run_python_root "objecttypes-api" "
import uuid, json
try:
    from objecttypes.core.models import ObjectType, ObjectVersion
    
    TAAK_UUID = uuid.UUID('550e8400-e29b-41d4-a716-446655440001')
    
    taak_schema = {
        '\$schema': 'http://json-schema.org/draft-07/schema',
        'title': 'Taak',
        'type': 'object',
        'properties': {
            'identificatie': {'type': 'string'},
            'zaak': {'type': 'string', 'format': 'uri'},
            'status': {'type': 'string', 'enum': ['open', 'afgerond', 'verwijderd']},
            'formulier': {'type': 'object'},
            'toewijzing': {'type': 'object'},
        },
        'required': ['status'],
    }
    
    ot, created = ObjectType.objects.get_or_create(
        uuid=TAAK_UUID,
        defaults={
            'name': 'Taak',
            'name_plural': 'Taken',
            'description': 'Taak objecttype voor Eventloket',
            'data_classification': 'open',
            'maintainer_organization': 'Eventloket',
            'maintainer_department': 'Development',
            'contact_person': 'admin',
            'contact_email': 'admin@localhost',
            'source': '',
            'update_frequency': 'unknown',
            'provider_organization': 'Eventloket',
            'documentation_url': '',
            'labels': {},
        }
    )
    
    if created:
        ObjectVersion.objects.create(
            object_type=ot,
            version=1,
            json_schema=taak_schema,
            status='published',
        )
        print(f'TAAK_UUID={TAAK_UUID}')
    else:
        print(f'TAAK_UUID={ot.uuid}')
        
except Exception as e:
    import uuid
    print(f'TAAK_CREATION_FAILED: {e}')
    print(f'Configure manually at http://localhost:8004/admin/')
" 2>/dev/null | grep -E "TAAK_UUID|TAAK_CREATION_FAILED" | head -1 || echo "TAAK_UUID=unknown")

if echo "${TAAK_UUID}" | grep -q "TAAK_UUID="; then
    TAAK_UUID_VALUE=$(echo "${TAAK_UUID}" | sed 's/TAAK_UUID=//')
    success "Taak objecttype UUID: ${TAAK_UUID_VALUE}"
    TAAK_OBJECTTYPE_URL="http://objecttypes-api:8000/api/v2/objecttypes/${TAAK_UUID_VALUE}"
else
    warn "Taak objecttype not auto-created — create manually at http://localhost:8004/admin/"
    TAAK_OBJECTTYPE_URL="http://objecttypes-api:8000/api/v2/objecttypes/<taak-uuid>"
fi

# Create the Aanvraag objecttype (used by Open Forms registration backends)
info "Creating Aanvraag objecttype..."
AANVRAAG_UUID=$(run_python_root "objecttypes-api" "
import uuid
try:
    from objecttypes.core.models import ObjectType, ObjectVersion

    AANVRAAG_UUID = uuid.UUID('550e8400-e29b-41d4-a716-446655440002')

    aanvraag_schema = {
        '\$schema': 'https://json-schema.org/draft/2020-12/schema',
        'type': 'object',
        'required': [],
        'properties': {
            'risicoClassificatie': {
                'type': 'string',
                'title': 'risico_classificatie',
            },
        },
        'additionalProperties': True,
    }

    ot, created = ObjectType.objects.get_or_create(
        uuid=AANVRAAG_UUID,
        defaults={
            'name': 'Aanvraag',
            'name_plural': 'Aanvragen',
            'description': 'Evenement aanvraag objecttype voor Open Forms registratie',
            'data_classification': 'open',
            'maintainer_organization': 'Eventloket',
            'maintainer_department': 'Development',
            'contact_person': 'admin',
            'contact_email': 'admin@localhost',
            'source': '',
            'update_frequency': 'unknown',
            'provider_organization': 'Eventloket',
            'documentation_url': '',
            'labels': {},
        }
    )

    if created:
        ObjectVersion.objects.create(
            object_type=ot,
            version=1,
            json_schema=aanvraag_schema,
            status='published',
        )
        print(f'AANVRAAG_UUID={AANVRAAG_UUID}')
    else:
        print(f'AANVRAAG_UUID={ot.uuid}')

except Exception as e:
    print(f'AANVRAAG_CREATION_FAILED: {e}')
    print(f'Configure manually at http://localhost:8004/admin/')
" 2>/dev/null | grep -E "AANVRAAG_UUID|AANVRAAG_CREATION_FAILED" | head -1 || echo "AANVRAAG_UUID=unknown")

if echo "${AANVRAAG_UUID}" | grep -q "AANVRAAG_UUID="; then
    AANVRAAG_UUID_VALUE=$(echo "${AANVRAAG_UUID}" | sed 's/AANVRAAG_UUID=//')
    success "Aanvraag objecttype UUID: ${AANVRAAG_UUID_VALUE}"
    AANVRAAG_OBJECTTYPE_URL="http://objecttypes-api:8000/api/v2/objecttypes/${AANVRAAG_UUID_VALUE}"
else
    warn "Aanvraag objecttype not auto-created — create manually at http://localhost:8004/admin/"
    AANVRAAG_OBJECTTYPE_URL="http://objecttypes-api:8000/api/v2/objecttypes/550e8400-e29b-41d4-a716-446655440002"
fi

success "Objecttypes API configured."

# =============================================================================
# 4. Configure Objects API
# =============================================================================
echo ""
info "Step 4: Configuring Objects API..."

run_python_root "objects-api" "
# ── 4a. API Tokens ────────────────────────────────────────────────────────────
from objects.token.models import TokenAuth

try:
    tokens = [
        {'identifier': 'open-forms', 'token': '${TOKEN_OF_TO_OBJ}', 'label': 'Open Forms'},
        {'identifier': 'eventloket', 'token': '${TOKEN_EL_TO_OBJ}', 'label': 'Eventloket'},
    ]
    for t in tokens:
        obj, created = TokenAuth.objects.update_or_create(
            token=t['token'],
            defaults={
                'identifier': t.get('identifier', t['label']),
                'contact_person': t['label'],
                'email': t['label'].lower().replace(' ','') + '@localhost',
                'is_superuser': True,
            }
        )
        print(f'Token for {t[\"label\"]}: {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'Token creation failed: {e}')
    print('Configure manually at http://localhost:8005/admin/token/tokenauth/add/')

# ── 4b. Objecttypes API service ───────────────────────────────────────────────
try:
    from zgw_consumers.models import Service
    from zgw_consumers.constants import APITypes, AuthTypes

    objt_service, created = Service.objects.update_or_create(
        slug='objecttypes-api',
        defaults={
            'label': 'Objecttypes API',
            'api_type': APITypes.orc,
            # NOTE: host.docker.internal:8004 is used (not objecttypes-api:8000) so that
            # the objecttype URLs stored here match what Open Forms sends in the `type` field
            # when registering submissions. Open Forms also uses host.docker.internal:8004
            # because Django's URLValidator rejects single-label hostnames (no dot).
            'api_root': 'http://host.docker.internal:8004/api/v2/',
            'auth_type': AuthTypes.api_key,
            'header_key': 'Authorization',
            'header_value': 'Token ${TOKEN_OBJT_INTERNAL}',
        }
    )
    print(f'Objecttypes service: {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'Objecttypes service: {e} — configure manually at /admin/')
# ── 4c. Open Notificaties service + NotificationsConfig ──────────────────────
try:
    nrc_svc, created = Service.objects.update_or_create(
        slug='open-notificaties',
        defaults={
            'label': 'Open Notificaties',
            'api_type': APITypes.nrc,
            'api_root': 'http://open-notificaties:8000/api/v1/',
            'auth_type': AuthTypes.zgw,
            'client_id': '${OBJ_CLIENT_ID}',
            'secret': '${OBJ_TO_OZ_SECRET}',
            'user_id': '${OBJ_CLIENT_ID}',
            'user_representation': 'Objects API',
        }
    )
    print(f'NRC service: {\"created\" if created else \"updated\"} -> {nrc_svc.api_root}')
    from notifications_api_common.models import NotificationsConfig
    cfg = NotificationsConfig.get_solo()
    cfg.notifications_api_service = nrc_svc
    cfg.save()
    print('NotificationsConfig: linked to Open Notificaties')
except Exception as e:
    print(f'NotificationsConfig: {e}')
" || warn "Objects API setup had issues — see output above"
# Also update Objecttypes API token permissions to include all objecttypes (after objects-api entry exists)
run_python_root "objecttypes-api" "from objecttypes.token.models import TokenAuth; [print(t.identifier, t.token) for t in TokenAuth.objects.all()]" 2>/dev/null | grep -v Runtime || true
success "Objects API configured."

# =============================================================================
# 4b. Create Laravel Passport client for Open Forms API (needed in Step 5c)
# =============================================================================
echo ""
echo ""
info "Step 4b: Creating Laravel Passport client for Open Forms API..."

# Revoke any existing open-forms-api clients to avoid accumulation across re-runs
${COMPOSE_CMD} exec -T laravel.test php artisan tinker --execute="
use Laravel\Passport\Client;
\$old = Client::where('name', 'open-forms-api')->whereNull('owner_id')->get();
foreach (\$old as \$c) { \$c->revoked = true; \$c->save(); echo 'Revoked: '.\$c->id.PHP_EOL; }
echo 'Revoked '.\$old->count().' old open-forms-api client(s)'.PHP_EOL;
" 2>&1 | grep -v "Psy Shell\|Copyright\|Type help\|^$" || true

OF_API_PASSPORT_OUTPUT=$(${COMPOSE_CMD} exec -T laravel.test php artisan passport:client \
    --client \
    --name="open-forms-api" \
    --no-interaction 2>&1) || true

echo "${OF_API_PASSPORT_OUTPUT}"

OF_API_CLIENT_ID=$(echo "${OF_API_PASSPORT_OUTPUT}"     | grep -E "Client ID"     | grep -oE '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}' | tail -1 || echo "unknown")
OF_API_CLIENT_SECRET=$(echo "${OF_API_PASSPORT_OUTPUT}" | grep -iE "Client Secret" | grep '\.\.' | awk '{print $NF}' | tr -d ' ' | head -1 || echo "unknown")

if [ "${OF_API_CLIENT_ID}" != "unknown" ] && [ "${OF_API_CLIENT_SECRET}" != "unknown" ]; then
    success "Passport client 'open-forms-api' created:"
    echo "  Client ID:     ${OF_API_CLIENT_ID}"
    echo "  Client Secret: ${OF_API_CLIENT_SECRET}"

    # Request a client credentials token from Laravel for use in the Open Forms service config.
    # NOTE: This token expires (default Passport expiry). Re-run the script or update
    #       the service header manually at http://localhost:8003/admin/zgw_consumers/service/
    #       if the token expires during development.
    OF_API_TOKEN=$(curl -sf -X POST "http://localhost/oauth/token" \
        -H "Content-Type: application/json" \
        -d "{\"grant_type\":\"client_credentials\",\"client_id\":\"${OF_API_CLIENT_ID}\",\"client_secret\":\"${OF_API_CLIENT_SECRET}\",\"scope\":\"\"}" \
        2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('access_token',''))" \
        2>/dev/null || echo "")

    if [ -n "${OF_API_TOKEN}" ]; then
        success "Client credentials token obtained for Open Forms API service."
    else
        warn "Could not obtain OAuth2 token — Open Forms service will be configured with placeholder. Fix manually."
        OF_API_TOKEN="PLACEHOLDER_REFRESH_VIA_SETUP_SCRIPT"
    fi
else
    warn "Could not parse Passport credentials for open-forms-api. Open Forms service will use placeholder auth."
    OF_API_CLIENT_ID="unknown"
    OF_API_CLIENT_SECRET="unknown"
    OF_API_TOKEN="PLACEHOLDER_REFRESH_VIA_SETUP_SCRIPT"
fi

# =============================================================================
# 5. Configure Open Forms
# =============================================================================
echo ""
info "Step 5: Configuring Open Forms..."

run_python "open-forms" "
from django.db import transaction

# ── 5a. ZGW Services ─────────────────────────────────────────────────────────
try:
    from zgw_consumers.models import Service
    from zgw_consumers.constants import APITypes, AuthTypes

    zgw_services = [
        {
            'slug': 'open-zaak-zrc',
            'label': 'Open Zaak — Zaken API',
            'api_type': APITypes.zrc,
            'api_root': 'http://host.docker.internal:8001/zaken/api/v1/',
            'auth_type': AuthTypes.zgw,
            'client_id': '${OF_CLIENT_ID}',
            'secret': '${OF_TO_OZ_SECRET}',
            'user_id': '${OF_CLIENT_ID}',
        },
        {
            'slug': 'open-zaak-drc',
            'label': 'Open Zaak — Documenten API',
            'api_type': APITypes.drc,
            'api_root': 'http://host.docker.internal:8001/documenten/api/v1/',
            'auth_type': AuthTypes.zgw,
            'client_id': '${OF_CLIENT_ID}',
            'secret': '${OF_TO_OZ_SECRET}',
            'user_id': '${OF_CLIENT_ID}',
        },
        {
            'slug': 'open-zaak-ztc',
            'label': 'Open Zaak — Catalogi API',
            'api_type': APITypes.ztc,
            'api_root': 'http://host.docker.internal:8001/catalogi/api/v1/',
            'auth_type': AuthTypes.zgw,
            'client_id': '${OF_CLIENT_ID}',
            'secret': '${OF_TO_OZ_SECRET}',
            'user_id': '${OF_CLIENT_ID}',
        },
        {
            'slug': 'objects-api',
            'label': 'Objects API',
            'api_type': APITypes.orc,
            # NOTE: host.docker.internal:8005 is used (not objects-api:8000) because
            # Open Zaak validates the `object` URL in zaakobjecten by fetching it via
            # this configured service. Django's URLValidator rejects single-label hostnames.
            'api_root': 'http://host.docker.internal:8005/api/v2/',
            'auth_type': AuthTypes.api_key,
            'header_key': 'Authorization',
            'header_value': 'Token ${TOKEN_OF_TO_OBJ}',
        },
        {
            'slug': 'objecttypes-api',
            'label': 'Objecttypes API',
            'api_type': APITypes.orc,
            'api_root': 'http://host.docker.internal:8004/api/v2/',
            'auth_type': AuthTypes.api_key,
            'header_key': 'Authorization',
            'header_value': 'Token ${TOKEN_OF_TO_OBJT}',
        },
    ]

    for svc in zgw_services:
        api_type  = svc.pop('api_type', None)
        auth_type = svc.pop('auth_type', None)
        slug      = svc['slug']
        svc['api_type']  = api_type
        svc['auth_type'] = auth_type
        s, created = Service.objects.update_or_create(
            slug=slug,
            defaults={k: v for k, v in svc.items() if k != 'slug'}
        )
        print(f'Service \"{s.label}\": {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'Service creation failed: {e}')

# ── 5b. Objects API group ─────────────────────────────────────────────────────
try:
    from zgw_consumers.models import Service
    from openforms.contrib.objects_api.models import ObjectsAPIGroupConfig

    zrc  = Service.objects.get(slug='open-zaak-zrc')
    drc  = Service.objects.get(slug='open-zaak-drc')
    ztc  = Service.objects.get(slug='open-zaak-ztc')
    obj  = Service.objects.get(slug='objects-api')
    objt = Service.objects.get(slug='objecttypes-api')

    group, created = ObjectsAPIGroupConfig.objects.update_or_create(
        name='Eventloket Objects API',
        defaults={
            'identifier': 'Eventloket Objects API',
            'objects_service': obj,
            'objecttypes_service': objt,
            'drc_service': drc,
            'catalogi_service': ztc,
        }
    )
    print(f'Objects API group: {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'Objects API group: {e}')
    print('  -> Configure manually at http://localhost:8003/admin/objects_api/objectsapigroupconfig/')
" || warn "Open Forms setup had issues — see output above"

# Create ZGW API group (needed by form registration backends)
info "Creating ZGW API group in Open Forms..."
ZGW_GROUP_PK=$(run_python "open-forms" "
try:
    from zgw_consumers.models import Service
    from openforms.registrations.contrib.zgw_apis.models import ZGWApiGroupConfig

    zrc = Service.objects.get(slug='open-zaak-zrc')
    drc = Service.objects.get(slug='open-zaak-drc')
    ztc = Service.objects.get(slug='open-zaak-ztc')

    group, created = ZGWApiGroupConfig.objects.update_or_create(
        name='Eventloket ZGW',
        defaults={
            'zrc_service': zrc,
            'drc_service': drc,
            'ztc_service': ztc,
            'organisatie_rsin': '820151130',
        }
    )
    print(f'ZGW_GROUP_PK={group.pk}')
    print(f'ZGW API group: {\"created\" if created else \"updated\"} (pk={group.pk})')
except ImportError as e:
    # Try alternate model path for older/newer Open Forms versions
    try:
        from openforms.registrations.contrib.zgw_apis.models import ZGWApiGroupConfig
        print(f'ZGW_GROUP_PK=IMPORT_ERROR: {e}')
    except Exception as e2:
        print(f'ZGW_GROUP_PK=IMPORT_ERROR: {e} / {e2}')
except Exception as e:
    print(f'ZGW_GROUP_PK=ERROR: {e}')
    print('  -> Configure manually at http://localhost:8003/admin/zgw_apis/zgwapigroupconfig/')
" 2>/dev/null | grep "ZGW_GROUP_PK=" | head -1 | sed 's/ZGW_GROUP_PK=//' || echo "1")

if [[ "${ZGW_GROUP_PK}" =~ ^[0-9]+$ ]]; then
    success "ZGW API group PK: ${ZGW_GROUP_PK}"
else
    warn "ZGW API group PK could not be determined (got: ${ZGW_GROUP_PK}) — defaulting to 1"
    ZGW_GROUP_PK="1"
fi

# ── 5c. Eventloket API service (for form variable fetches) ───────────────────
info "Creating Eventloket API service in Open Forms..."

# Step 1: create/update the service entry (without the Authorization header — token
# is too long to safely embed in a shell -c argument). Get the PK first.
EL_API_SVC_PK=$(run_python "open-forms" "
try:
    from zgw_consumers.models import Service
    from zgw_consumers.constants import APITypes, AuthTypes

    svc, created = Service.objects.update_or_create(
        slug='eventloket-api',
        defaults={
            'label': 'Eventloket API',
            'api_root': 'http://laravel.test/',
            'api_type': APITypes.orc,
            'auth_type': AuthTypes.api_key,
            'header_key': 'Authorization',
            'header_value': '',
        }
    )
    print(f'EL_API_SVC_PK={svc.pk}')
    print(f'Eventloket API service: {\"created\" if created else \"updated\"} (pk={svc.pk})')
except Exception as e:
    print(f'EL_API_SVC_PK=ERROR')
    print(f'Eventloket API service creation failed: {e}')
    print('  -> Configure manually at http://localhost:8003/admin/zgw_consumers/service/')
" 2>/dev/null | grep "EL_API_SVC_PK=" | head -1 | sed 's/EL_API_SVC_PK=//' || echo "ERROR")

# Step 2: write Bearer token to a tmpfile and copy into the container.
# This avoids shell quoting / stdin-pipe issues with long JWT strings.
if [[ "${EL_API_SVC_PK}" =~ ^[0-9]+$ ]] && [ -n "${OF_API_TOKEN}" ] && [ "${OF_API_TOKEN}" != "PLACEHOLDER_REFRESH_VIA_SETUP_SCRIPT" ]; then
    printf '%s' "${OF_API_TOKEN}" > /tmp/el_svc_token.txt
    ${COMPOSE_CMD} cp /tmp/el_svc_token.txt open-forms:/tmp/el_svc_token.txt
    rm -f /tmp/el_svc_token.txt
    run_python "open-forms" "
from django.db import connection
from zgw_consumers.models import Service
# Widen header_value column to TEXT so JWT tokens (>255 chars) fit.
with connection.cursor() as cur:
    cur.execute(\"ALTER TABLE zgw_consumers_service ALTER COLUMN header_value TYPE TEXT\")
with open('/tmp/el_svc_token.txt') as f:
    tok = f.read().strip()
svc = Service.objects.get(pk=${EL_API_SVC_PK})
svc.header_value = 'Bearer ' + tok
svc.save(update_fields=['header_value'])
print('Header value updated for Eventloket API service (pk=%d, len=%d)' % (svc.pk, len(svc.header_value)))
" 2>/dev/null && success "Eventloket API service token set." \
        || warn "Could not set Bearer token on Eventloket API service — update manually at http://localhost:8003/admin/zgw_consumers/service/"
fi

if [[ "${EL_API_SVC_PK}" =~ ^[0-9]+$ ]]; then
    success "Eventloket API service PK: ${EL_API_SVC_PK}"
    EL_API_SVC_URL="http://open-forms:8000/api/v2/services/${EL_API_SVC_PK}"
else
    warn "Eventloket API service PK could not be determined (got: ${EL_API_SVC_PK})"
    warn "Create manually at http://localhost:8003/admin/zgw_consumers/service/ and update formVariables.json service reference."
    EL_API_SVC_URL=""
fi

success "Open Forms configured."

# =============================================================================
# 6. Configure Open Notificaties — Eventloket abonnement
# =============================================================================
echo ""
info "Step 6: Registering Open Notificaties webhook subscriptions..."

run_python "open-notificaties" "
# Widen auth column to TEXT so JWT access tokens (>255 chars) fit
try:
    from django.db import connection
    with connection.cursor() as cur:
        cur.execute(\"ALTER TABLE datamodel_abonnement ALTER COLUMN auth TYPE TEXT\")
    print('datamodel_abonnement.auth column widened to TEXT')
except Exception as e:
    print(f'Column widen skipped (may already be TEXT): {e}')

# Register the autorisaties channel subscription (for cache invalidation)
try:
    from nrc.datamodel.models import Kanaal, Abonnement, Filter, FilterGroup
    from django.db import transaction
    
    with transaction.atomic():
        # Eventloket webhook subscription
        kanalen = ['zaken', 'documenten', 'besluiten', 'autorisaties']
        notif_endpoint = 'http://laravel.test/api/open-notifications/listen'
        auth_value     = 'Bearer PASSPORT_CLIENT_SECRET_PLACEHOLDER'

        abo, abo_created = Abonnement.objects.get_or_create(
            callback_url=notif_endpoint,
            defaults={
                'client_id': 'eventloket',
                'auth': auth_value,
                'send_cloudevents': False,
            }
        )
        print(f'Abonnement: {\"created\" if abo_created else \"exists\"} ({notif_endpoint})')

        for kanaal_naam in kanalen:
            try:
                kanaal = Kanaal.objects.get(naam=kanaal_naam)
                # Kanalen are linked via FilterGroup objects (not M2M)
                fg, created = FilterGroup.objects.get_or_create(abonnement=abo, kanaal=kanaal)
                print(f'FilterGroup {kanaal_naam}: {\"created\" if created else \"exists\"}')
            except Kanaal.DoesNotExist:
                print(f'Kanaal \"{kanaal_naam}\" not found — run register_kanalen first in Open Zaak, then re-run this step')
except Exception as e:
    print(f'Abonnement creation failed: {e}')
    print('Configure manually at http://localhost:8002/admin/datamodel/abonnement/')
    print(f'Callback URL: http://laravel.test/api/open-notifications/listen')
"

success "Open Notificaties webhook subscription registered (placeholder auth). Will be updated with JWT in Step 7."

# =============================================================================
# 7. Configure Laravel — Passport client for Open Notificaties
# =============================================================================
echo ""
info "Step 7: Creating Laravel Passport client for Open Notificaties..."

# Revoke any existing open-notificaties clients to avoid accumulation across re-runs
${COMPOSE_CMD} exec -T laravel.test php artisan tinker --execute="
use Laravel\Passport\Client;
\$old = Client::where('name', 'open-notificaties')->whereNull('owner_id')->get();
foreach (\$old as \$c) { \$c->revoked = true; \$c->save(); echo 'Revoked: '.\$c->id.PHP_EOL; }
echo 'Revoked '.\$old->count().' old open-notificaties client(s)'.PHP_EOL;
" 2>&1 | grep -v "Psy Shell\|Copyright\|Type help\|^$" || true

PASSPORT_OUTPUT=$(${COMPOSE_CMD} exec -T laravel.test php artisan passport:client \
    --client \
    --name="open-notificaties" \
    --no-interaction 2>&1) || true

echo "${PASSPORT_OUTPUT}"

PASSPORT_CLIENT_ID=$(echo "${PASSPORT_OUTPUT}"     | grep -E "Client ID" | grep -oE '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}' | tail -1 || echo "unknown")
PASSPORT_CLIENT_SECRET=$(echo "${PASSPORT_OUTPUT}" | grep -iE "Client Secret" | grep '\.\.' | awk '{print $NF}' | tr -d ' ' | head -1 || echo "unknown")

if [ "${PASSPORT_CLIENT_ID}" != "unknown" ] && [ "${PASSPORT_CLIENT_SECRET}" != "unknown" ]; then
    success "Passport client 'open-notificaties' created:"
    echo "  Client ID:     ${PASSPORT_CLIENT_ID}"
    echo "  Client Secret: ${PASSPORT_CLIENT_SECRET}"

    # Request a JWT access token via client_credentials flow (same as open-forms-api in Step 4b).
    # EnsureClientIsResourceOwner on the Laravel endpoint requires a valid JWT, not the raw secret.
    ON_JWT=$(curl -sf -X POST "http://localhost/oauth/token" \
        -H "Content-Type: application/json" \
        -d "{\"grant_type\":\"client_credentials\",\"client_id\":\"${PASSPORT_CLIENT_ID}\",\"client_secret\":\"${PASSPORT_CLIENT_SECRET}\",\"scope\":\"\"}" \
        2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('access_token',''))" \
        2>/dev/null || echo "")

    if [ -n "${ON_JWT}" ]; then
        success "JWT access token obtained for open-notificaties client."

        # Update Open Notificaties abonnement auth header with JWT Bearer token
        run_python "open-notificaties" "
try:
    from nrc.datamodel.models import Abonnement
    notif_endpoint = 'http://laravel.test/api/open-notifications/listen'
    abo = Abonnement.objects.get(callback_url=notif_endpoint)
    abo.auth = 'Bearer ${ON_JWT}'
    abo.save()
    print(f'Abonnement auth updated with JWT Bearer token')
except Abonnement.DoesNotExist:
    print(f'Abonnement not found — was Step 6 skipped?')
except Exception as e:
    print(f'Could not update Abonnement auth: {e}')
" || true
    else
        warn "Could not obtain JWT from Laravel OAuth token endpoint."
        warn "The abonnement auth is set to a placeholder. Update manually:"
        echo "  1. Request a token: POST http://localhost/oauth/token with client_credentials"
        echo "  2. Update at: http://localhost:8002/admin/datamodel/abonnement/"
    fi
else
    warn "Could not parse Passport credentials from output. Run manually:"
    echo "  docker compose exec laravel.test php artisan passport:client --client --name='open-notificaties'"
fi

# =============================================================================
# 8. Import Open Zaak catalogus (VRZL)
# =============================================================================
echo ""
info "Step 8: Importing Open Zaak catalogus..."

CATALOGUS_EXISTS=$(run_python "open-zaak" "
try:
    from openzaak.components.catalogi.models import Catalogus
    exists = Catalogus.objects.filter(domein='VRZL', rsin='820151130').exists()
    print('EXISTS' if exists else 'NOT_EXISTS')
except Exception as e:
    print(f'CHECK_FAILED: {e}')
" 2>/dev/null | grep -E "EXISTS|NOT_EXISTS|CHECK_FAILED" | head -1 || echo "NOT_EXISTS")

if echo "${CATALOGUS_EXISTS}" | grep -q "^EXISTS$"; then
    success "Catalogus VRZL already exists — skipping import."
else
    info "Catalogus not found — creating ZIP and importing..."

    # Create ZIP inside the container from the mounted volume (using Python — zip not installed)
    ${COMPOSE_CMD} exec -T open-zaak python3 -c "
import zipfile, os
src = '/app/catalogus-import'
files = ['Catalogus.json','ZaakType.json','InformatieObjectType.json','StatusType.json',
         'ResultaatType.json','RolType.json','Eigenschap.json','BesluitType.json',
         'ZaakTypeInformatieObjectType.json']
try:
    with zipfile.ZipFile('/tmp/catalogus-import.zip', 'w', zipfile.ZIP_DEFLATED) as zf:
        for fname in files:
            fp = os.path.join(src, fname)
            if os.path.exists(fp):
                zf.write(fp, fname)
            else:
                print(f'MISSING: {fname}')
    print('ZIP_CREATED')
except Exception as e:
    import sys; print(f'ZIP_FAILED: {e}'); sys.exit(1)
"

    # Run the catalogi import management command (preserves original staging UUIDs)
    if run_manage "open-zaak" import --import-file /tmp/catalogus-import.zip; then
        success "Catalogus import completed."
    else
        warn "Catalogus import failed — check output above. Try manually:"
        echo "  docker compose -f ${COMPOSE_FILE} exec open-zaak python /app/src/manage.py import --import-file /tmp/catalogus-import.zip"
    fi

    # Publish all concept ZaakTypen, InformatieObjectTypen and BesluitTypen
    info "Publishing imported catalog types..."
    run_python "open-zaak" "
try:
    from openzaak.components.catalogi.models import ZaakType, InformatieObjectType, BesluitType

    zt_count = 0
    for zt in ZaakType.objects.filter(concept=True):
        zt.publish()
        zt_count += 1

    iot_count = 0
    for iot in InformatieObjectType.objects.filter(concept=True):
        iot.publish()
        iot_count += 1

    bt_count = 0
    for bt in BesluitType.objects.filter(concept=True):
        bt.publish()
        bt_count += 1

    print(f'Published: {zt_count} zaaktypen, {iot_count} informatieobjecttypen, {bt_count} besluittypen')
except Exception as e:
    print(f'Publish failed: {e}')
    print('Publish manually via http://localhost:8001/admin/')
" || warn "Publish step had issues — see output above"

    # Capture catalogus URL for .env output
    CATALOGUS_URL=$(run_python "open-zaak" "
try:
    from openzaak.components.catalogi.models import Catalogus
    cat = Catalogus.objects.filter(domein='VRZL', rsin='820151130').first()
    if cat:
        print(f'http://open-zaak:8000/catalogi/api/v1/catalogussen/{cat.uuid}')
    else:
        print('')
except Exception as e:
    print('')
" 2>/dev/null | grep -E "^http" | head -1 || echo "")

    if [ -n "${CATALOGUS_URL}" ]; then
        success "Catalogus URL: ${CATALOGUS_URL}"
    else
        warn "Could not determine catalogus URL — check http://localhost:8001/catalogi/api/v1/catalogussen/"
        CATALOGUS_URL="http://open-zaak:8000/catalogi/api/v1/catalogussen/<uuid>"
    fi
fi

# =============================================================================
# 8b. Sync zaaktypen into Eventloket and link to municipalities
# =============================================================================
echo ""
info "Step 8b: Syncing zaaktypen into Eventloket..."

if [ -z "${CATALOGUS_URL:-}" ] || echo "${CATALOGUS_URL}" | grep -q "<uuid>"; then
    warn "Skipping zaaktypen sync — OPENZAAK_CATALOGI_URL is not available (step 8 may have failed)."
else
    # Write OPENZAAK_CATALOGI_URL into .env if not already set correctly
    ENV_FILE="$(pwd)/.env"
    if [ -f "${ENV_FILE}" ]; then
        if grep -q "^OPENZAAK_CATALOGI_URL=" "${ENV_FILE}"; then
            sed -i '' "s|^OPENZAAK_CATALOGI_URL=.*|OPENZAAK_CATALOGI_URL=${CATALOGUS_URL}|" "${ENV_FILE}"
        else
            echo "OPENZAAK_CATALOGI_URL=${CATALOGUS_URL}" >> "${ENV_FILE}"
        fi
        success "OPENZAAK_CATALOGI_URL set in .env: ${CATALOGUS_URL}"
    else
        warn ".env file not found at ${ENV_FILE} — skipping automatic OPENZAAK_CATALOGI_URL write."
        warn "Add manually: OPENZAAK_CATALOGI_URL=${CATALOGUS_URL}"
    fi

    # Sync zaaktypen from Open Zaak into the Eventloket database
    info "Running app:sync-zaaktypen..."
    if docker compose -f "${COMPOSE_FILE}" exec -T laravel.test php artisan app:sync-zaaktypen; then
        success "Zaaktypen synced successfully."
    else
        warn "app:sync-zaaktypen failed — check Laravel logs."
    fi

    # Link each zaaktype to its municipality using the name pattern "gemeente {Name}"
    info "Running app:link-zaaktypen-municipalities..."
    if docker compose -f "${COMPOSE_FILE}" exec -T laravel.test php artisan app:link-zaaktypen-municipalities; then
        success "Zaaktypen linked to municipalities."
    else
        warn "app:link-zaaktypen-municipalities failed — check Laravel logs."
    fi
fi

# =============================================================================
# 9. Import Open Forms formulier
# =============================================================================
echo ""
info "Step 9: Importing Open Forms formulier..."

# Get admin token for Open Forms API auth
OF_ADMIN_TOKEN=$(run_python "open-forms" "
try:
    from rest_framework.authtoken.models import Token
    from django.contrib.auth import get_user_model
    User = get_user_model()
    user = User.objects.filter(is_superuser=True).first()
    if not user:
        print('TOKEN_ERROR: no superuser found')
    else:
        token, _ = Token.objects.get_or_create(user=user)
        print(f'TOKEN={token.key}')
except Exception as e:
    print(f'TOKEN_ERROR: {e}')
" 2>/dev/null | grep "^TOKEN=" | sed 's/TOKEN=//' | head -1 || echo "")

if [ -z "${OF_ADMIN_TOKEN}" ]; then
    warn "Could not obtain Open Forms admin token — skipping formulier import."
    warn "Import manually at http://localhost:8003/admin/ or via the import endpoint."
    OPEN_FORMS_MAIN_FORM_UUID=""
else
    # Check if form already exists (by slug)
    # NOTE: Open Forms 3.x API does not use trailing slashes on collection endpoints
    FORM_SLUG="evenementformulier-poc-kopie-a6efc0"
    FORM_EXISTS=$(curl -sf \
        -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
        "http://localhost:8009/api/v2/forms?slug=${FORM_SLUG}" \
        2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); r=d if isinstance(d,list) else d.get('results',[]); print('EXISTS' if any(f['slug']=='${FORM_SLUG}' for f in r) else 'NOT_EXISTS')" \
        2>/dev/null || echo "NOT_EXISTS")

    if echo "${FORM_EXISTS}" | grep -q "^EXISTS$"; then
        # Get UUID and active status of existing form
        FORM_DATA=$(curl -sf \
            -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
            "http://localhost:8009/api/v2/forms?slug=${FORM_SLUG}" \
            2>/dev/null || echo '[]')
        OPEN_FORMS_MAIN_FORM_UUID=$(echo "${FORM_DATA}" | python3 -c "import sys,json; d=json.load(sys.stdin); r=d if isinstance(d,list) else d.get('results',[]); m=next((f for f in r if f['slug']=='${FORM_SLUG}'),None); print(m['uuid'] if m else '')" 2>/dev/null || echo "")
        # Ensure the form is active (imported forms default to inactive)
        if [ -n "${OPEN_FORMS_MAIN_FORM_UUID}" ]; then
            IS_ACTIVE=$(echo "${FORM_DATA}" | python3 -c "import sys,json; d=json.load(sys.stdin); r=d if isinstance(d,list) else d.get('results',[]); m=next((f for f in r if f['slug']=='${FORM_SLUG}'),None); print(m.get('active',False) if m else False)" 2>/dev/null || echo "False")
            if [ "${IS_ACTIVE}" != "True" ]; then
                curl -sf -X PATCH \
                    -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
                    -H "Content-Type: application/json" \
                    "http://localhost:8003/api/v2/forms/${OPEN_FORMS_MAIN_FORM_UUID}" \
                    --data '{"active": true}' >/dev/null 2>&1 && info "Form activated."
            fi
        fi
        success "Formulier already exists (uuid=${OPEN_FORMS_MAIN_FORM_UUID}) — skipping import."
    else
        info "Formulier not found — patching and importing..."

        # Patch all JSON files inside the container to replace staging references:
        # 1. Objects API group identifiers (registration and prefill)
        # 2. Staging objecttype URL -> local Aanvraag objecttype URL
        # 3. ZGW API group PK (forms.json only)
        # NOTE: host.docker.internal:8004 is used (not objecttypes-api:8000) because
        #       Django's URLValidator rejects single-label hostnames without a dot.
        #       host.docker.internal resolves on both macOS and Windows Docker Desktop.
        ${COMPOSE_CMD} exec -T open-forms python3 -c "
import json, os, shutil

src_dir = '/app/formulier-import'
work_dir = '/tmp/formulier-work'

os.makedirs(work_dir, exist_ok=True)

file_names = ['_meta.json', 'forms.json', 'formDefinitions.json', 'formSteps.json', 'formLogic.json', 'formVariables.json']
for fname in file_names:
    shutil.copy(os.path.join(src_dir, fname), os.path.join(work_dir, fname))

patches = {
    'objecten-groep-vrzl-test': 'Eventloket Objects API',
    'prefill-groep-vrzl-test': 'Eventloket Objects API',
    'https://objecttypes-api.vrzl-test.woweb.app/api/v2/objecttypes/b43dd81e-3b4e-4798-abd2-b8a387cb46c3':
        'http://host.docker.internal:8004/api/v2/objecttypes/550e8400-e29b-41d4-a716-446655440002',
    'http://open-formulieren.vrzl-test.woweb.app/api/v2/services/3':
        'http://localhost:8003/api/v2/services/${EL_API_SVC_PK}',
}

for fname in file_names:
    fpath = os.path.join(work_dir, fname)
    with open(fpath, 'r') as f:
        content = f.read()
    modified = content
    for old, new in patches.items():
        modified = modified.replace(old, new)
    if fname == 'forms.json':
        # ZGW API group PK (only in forms.json, always exported as 1)
        modified = modified.replace('\"zgw_api_group\": 1,', '\"zgw_api_group\": ${ZGW_GROUP_PK},')
        modified = modified.replace('\"zgw_api_group\": 1}', '\"zgw_api_group\": ${ZGW_GROUP_PK}}')
        # objecttype_version: staging uses version 3; local Objecttypes API only has version 1
        import re
        modified = re.sub(r'\"objecttype_version\":\s*\d+', '\"objecttype_version\": 1', modified)
    with open(fpath, 'w') as f:
        f.write(modified)
    if modified != content:
        print(f'PATCHED: {fname}')
    else:
        print(f'unchanged: {fname}')
print('PATCH_OK')
"
        # Create ZIP inside container (using Python — zip not installed)
        ${COMPOSE_CMD} exec -T open-forms python3 -c "
import zipfile, os
work = '/tmp/formulier-work'
files = ['_meta.json','forms.json','formDefinitions.json','formSteps.json','formLogic.json','formVariables.json']
try:
    with zipfile.ZipFile('/tmp/formulier.zip', 'w', zipfile.ZIP_DEFLATED) as zf:
        for fname in files:
            fp = os.path.join(work, fname)
            if os.path.exists(fp):
                zf.write(fp, fname)
            else:
                print(f'MISSING: {fname}')
    print('ZIP_CREATED')
except Exception as e:
    import sys; print(f'ZIP_FAILED: {e}'); sys.exit(1)
"

        # Copy ZIP from container to host for curl upload
        ${COMPOSE_CMD} cp open-forms:/tmp/formulier.zip /tmp/formulier-eventloket.zip

        # Import via REST API (multipart, curl runs on host against localhost:8003)
        # Open Forms 3.x returns 204 No Content on success (empty body, no UUID in response)
        IMPORT_HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
            http://localhost:8003/api/v2/forms-import \
            -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
            -H "Content-Disposition: attachment;filename=formulier.zip" \
            --form "file=@/tmp/formulier-eventloket.zip;type=application/zip" \
            2>/dev/null || echo "000")

        if [ "${IMPORT_HTTP_CODE}" = "204" ] || [ "${IMPORT_HTTP_CODE}" = "200" ]; then
            success "Formulier imported (HTTP ${IMPORT_HTTP_CODE})"
            # Retrieve the UUID of the just-imported form by slug
            OPEN_FORMS_MAIN_FORM_UUID=$(curl -sf \
                -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
                "http://localhost:8009/api/v2/forms?slug=${FORM_SLUG}" \
                2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); r=d if isinstance(d,list) else d.get('results',[]); m=next((f for f in r if f['slug']=='${FORM_SLUG}'),None); print(m['uuid'] if m else '')" \
                2>/dev/null || echo "")
            # Activate the form — imported forms default to inactive
            if [ -n "${OPEN_FORMS_MAIN_FORM_UUID}" ]; then
                curl -sf -X PATCH \
                    -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
                    -H "Content-Type: application/json" \
                    "http://localhost:8003/api/v2/forms/${OPEN_FORMS_MAIN_FORM_UUID}" \
                    --data '{"active": true}' >/dev/null 2>&1 \
                    && success "Formulier activated (uuid=${OPEN_FORMS_MAIN_FORM_UUID})" \
                    || warn "Could not activate form — activate manually at http://localhost:8003/admin/"
            else
                warn "Import succeeded but could not determine form UUID from slug lookup."
            fi
        else
            warn "Formulier import returned HTTP ${IMPORT_HTTP_CODE} — import may have failed."
            warn "Try manually: curl -X POST http://localhost:8003/api/v2/forms-import -H 'Authorization: Token ${OF_ADMIN_TOKEN}' -H 'Content-Disposition: attachment;filename=form.zip' --form 'file=@/tmp/formulier-eventloket.zip'"
            OPEN_FORMS_MAIN_FORM_UUID=""
        fi
    fi
fi

# =============================================================================
# Step 9b: Link ServiceFetchConfiguration objects to FormVariables via Django ORM
# =============================================================================
# The form import resolves service URLs via DRF HyperlinkedRelatedField which can
# fail when the request host doesn't match the embedded URL. To guarantee the
# ServiceFetchConfiguration FKs on each FormVariable are set correctly, we always
# create/update them via ORM directly after import.
info "Step 9b: Linking service fetch configurations to form variables..."
SFC_OUTPUT=$(${COMPOSE_CMD} exec -T open-forms python /app/src/manage.py shell 2>/dev/null << 'PYEOF'
from zgw_consumers.models import Service
from openforms.variables.models import ServiceFetchConfiguration
from openforms.forms.models import FormVariable

FORM_SLUG = 'evenementformulier-poc-kopie-a6efc0'

try:
    svc = Service.objects.get(slug='eventloket-api')
except Service.DoesNotExist:
    print('SFC_ERROR: eventloket-api service not found — run setup script after Step 5c')
    import sys; sys.exit(1)

configs = [
    {
        'variable_key': 'inGemeentenResponse',
        'name': 'Evenloket locationserver',
        'path': 'api/locationserver/check',
        'method': 'POST',
        'headers': {'Accept': 'application/json', 'X-OpenForms-Normalize': 'polygons,line,address,addresses,lines'},
        'query_params': {
            'line':      ['{{ routeVanHetEvenement }}'],
            'lines':     ['{{ routesOpKaart }}'],
            'address':   ['{{ addressToCheck }}'],
            'polygons':  ['{{ locatieSOpKaart }}'],
            'addresses': ['{{ addressesToCheck }}'],
        },
        'body': {'merge': [{'adresses': {'var': 'addressesToCheck'}, 'locaties': {'var': 'adresVanDeGebouwEn'}}]},
        'data_mapping_type': 'JsonLogic',
        'mapping_expression': {'var': 'data'},
        'cache_timeout': None,
    },
    {
        'variable_key': 'eventloketSession',
        'name': 'Eventloket sessie',
        'path': 'api/formsessions',
        'method': 'GET',
        'headers': {},
        'query_params': {'submission_uuid': ['{{ submission_id }}']},
        'body': None,
        'data_mapping_type': 'JsonLogic',
        'mapping_expression': {'var': 'data'},
        'cache_timeout': None,
    },
    {
        'variable_key': 'gemeenteVariabelen',
        'name': 'Eventloket gemeente variabelen',
        'path': 'api/municipality-variables/{{ evenementInGemeente.brk_identification }}',
        'method': 'GET',
        'headers': {},
        'query_params': {'as_key_value': ['true']},
        'body': None,
        'data_mapping_type': 'JsonLogic',
        'mapping_expression': {'var': 'data'},
        'cache_timeout': None,
    },
    {
        'variable_key': 'evenementenInDeGemeente',
        'name': 'Events check Eventloket',
        'path': 'api/events/check',
        'method': 'POST',
        'headers': {},
        'query_params': {
            'end_date':     ['{{EvenementEind}}'],
            'start_date':   ['{{EvenementStart}}'],
            'municipality': ['{{ evenementInGemeente.brk_identification }}'],
        },
        'body': None,
        'data_mapping_type': 'JsonLogic',
        'mapping_expression': {'var': 'data'},
        'cache_timeout': None,
    },
]

ok = 0
for cfg in configs:
    var_key = cfg.pop('variable_key')
    try:
        sfc, created = ServiceFetchConfiguration.objects.update_or_create(
            name=cfg['name'],
            defaults={**cfg, 'service': svc},
        )
        matched = FormVariable.objects.filter(form__slug=FORM_SLUG, key=var_key)
        if not matched.exists():
            print(f"SFC_WARN: FormVariable '{var_key}' not found on form '{FORM_SLUG}'")
            continue
        matched.update(service_fetch_configuration=sfc)
        print(f"SFC_OK: {var_key} -> {cfg['name']} ({'created' if created else 'updated'})")
        ok += 1
    except Exception as e:
        print(f"SFC_ERROR: {var_key}: {e}")

print(f'SFC_DONE: {ok}/4')
PYEOF
)

if echo "${SFC_OUTPUT}" | grep -q "SFC_DONE: 4/4"; then
    success "Service fetch configurations linked (4/4)"
elif echo "${SFC_OUTPUT}" | grep -q "SFC_DONE:"; then
    SFC_COUNT=$(echo "${SFC_OUTPUT}" | grep "SFC_DONE:" | sed 's/SFC_DONE: //')
    warn "Service fetch configurations partially linked (${SFC_COUNT}) — check output:"
    echo "${SFC_OUTPUT}" | grep "SFC_"
else
    warn "Service fetch configurations could not be linked — check DB manually at http://localhost:8003/admin/"
    [ -n "${SFC_OUTPUT}" ] && echo "${SFC_OUTPUT}"
fi

# =============================================================================
# Summary
# =============================================================================
echo ""
echo -e "${CYAN}══════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  Setup Complete!${NC}"
echo -e "${CYAN}══════════════════════════════════════════════════════${NC}"
echo ""
echo "Service URLs:"
echo "  Laravel app:      http://localhost:80"
echo "  Open Zaak:        http://localhost:8001  (admin: admin / admin)"
echo "  Open Notificaties: http://localhost:8002 (admin: admin / admin)"
echo "  Open Forms:       http://localhost:8003  (admin: admin / admin) [via CORS proxy]"
echo "  Open Forms direct: http://localhost:8009 (debugging only, no CORS headers on static files)"
echo "  Objecttypes API:  http://localhost:8004  (admin: admin / admin)"
echo "  Objects API:      http://localhost:8005  (admin: admin / admin)"
echo ""
echo "Add these to your .env (for the full-architecture stack):"
echo ""
echo "  DB_CONNECTION=pgsql"
echo "  DB_HOST=pgsql"
echo "  DB_PORT=5432"
echo "  OPENZAAK_URL=http://open-zaak:8000/"
echo "  OPENZAAK_CLIENT_ID=${EL_CLIENT_ID}"
echo "  OPENZAAK_CLIENT_SECRET=${EL_TO_OZ_SECRET}"
echo "  OBJECTSAPI_URL=http://host.docker.internal:8005/"
echo "  OBJECTSAPI_TOKEN=${TOKEN_EL_TO_OBJ}"
echo "  OBJECTSAPI_OBJECTSTYPE_TAAK_URL=${TAAK_OBJECTTYPE_URL}"
echo "  OBJECTSAPI_OBJECTSTYPE_AANVRAAG_URL=${AANVRAAG_OBJECTTYPE_URL}"
echo "  OPEN_FORMS_BASE_URL=http://open-forms:8000"
if [ -n "${CATALOGUS_URL:-}" ]; then
    echo "  OPENZAAK_CATALOGI_URL=${CATALOGUS_URL}"
fi
if [ "${PASSPORT_CLIENT_ID}" != "unknown" ]; then
    echo ""
    echo "Passport client for Open Notificaties inbound auth:"
    echo "  PASSPORT_CLIENT_ID=${PASSPORT_CLIENT_ID}"
    echo "  PASSPORT_CLIENT_SECRET=${PASSPORT_CLIENT_SECRET}"
    echo ""
    echo -e "${GREEN}NOTE:${NC} Abonnement auth header was updated automatically to 'Bearer ${PASSPORT_CLIENT_SECRET}'"
    echo "  Verify at: http://localhost:8002/admin/datamodel/abonnement/"
fi
if [ "${OF_API_CLIENT_ID}" != "unknown" ]; then
    echo ""
    echo "Passport client for Open Forms API outbound auth (service fetches):"
    echo "  OF_API_CLIENT_ID=${OF_API_CLIENT_ID}"
    echo "  OF_API_CLIENT_SECRET=${OF_API_CLIENT_SECRET}"
    if [ -n "${EL_API_SVC_URL:-}" ]; then
        echo ""
        echo -e "${GREEN}NOTE:${NC} Eventloket API service configured in Open Forms (${EL_API_SVC_URL})"
        echo "  The access token is short-lived. If service fetches fail, re-run this script"
        echo "  or update the Bearer token manually at:"
        echo "  http://localhost:8003/admin/zgw_consumers/service/"
    fi
fi
if [ -n "${OPEN_FORMS_MAIN_FORM_UUID:-}" ]; then
    echo ""
    echo "  OPEN_FORMS_MAIN_FORM_UUID=${OPEN_FORMS_MAIN_FORM_UUID}"
fi
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Verify the notification chain: submit a test form in Open Forms →"
echo "     check Open Zaak for a new Zaak → check Laravel logs for notification processing"
echo ""

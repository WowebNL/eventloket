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
#   1. Waits for all services to be reachable
#   2. Configures Open Zaak  → connects to Open Notificaties, creates applications
#   3. Configures Open Notificaties → connects to Open Zaak AC, registers webhooks
#   4. Configures Objecttypes API → creates API tokens, Taak + Aanvraag objecttypen
#   5. Configures Objects API     → creates API tokens, links to Objecttypes
#   6. Configures Open Forms      → links ZGW services, Objects/Objecttypes APIs, ZGW API group
#   7. Configures Laravel         → creates Passport client for Open Notificaties
#   8. Imports Open Zaak catalogus (VRZL) → zaaktypen, statustypen, informatieobjecttypen etc.
#   9. Imports Open Forms formulier (Evenementformulier) via REST API
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
EL_TO_OZ_SECRET="local-secret-eventloket"

# Objects API → Open Zaak (for notifications)
OBJ_CLIENT_ID="objects-api"
OBJ_TO_OZ_SECRET="local-secret-obj-to-oz"

# Token authentication (Objects ↔ Objecttypes ↔ Open Forms ↔ Laravel)
TOKEN_OBJT_INTERNAL="local-token-obj-to-objt"   # Objects API → Objecttypes API
TOKEN_OF_TO_OBJ="local-token-of-to-obj"         # Open Forms → Objects API
TOKEN_OF_TO_OBJT="local-token-of-to-objt"       # Open Forms → Objecttypes API
TOKEN_EL_TO_OBJ="local-token-eventloket"        # Laravel → Objects API (= OBJECTSAPI_TOKEN)

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
kanalen_names = ['autorisaties', 'besluiten', 'besluittypen', 'documenten', 'informatieobjecttypen', 'zaaktypen', 'zaken']
for naam in kanalen_names:
    k, created = Kanaal.objects.get_or_create(naam=naam)
    print(f'Kanaal {naam}: {\"created\" if created else \"exists\"}')
print('Kanalen configured in Open Notificaties.')
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
            'api_root': 'http://objecttypes-api:8000/api/v2/',
            'auth_type': AuthTypes.api_key,
            'header_key': 'Authorization',
            'header_value': 'Token ${TOKEN_OBJT_INTERNAL}',
        }
    )
    print(f'Objecttypes service: {\"created\" if created else \"updated\"}')
except Exception as e:
    print(f'Objecttypes service: {e} — configure manually at /admin/')
" || warn "Objects API setup had issues — see output above"
# Also update Objecttypes API token permissions to include all objecttypes (after objects-api entry exists)
run_python_root "objecttypes-api" "from objecttypes.token.models import TokenAuth; [print(t.identifier, t.token) for t in TokenAuth.objects.all()]" 2>/dev/null | grep -v Runtime || true
success "Objects API configured."

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
            'api_root': 'http://objects-api:8000/api/v2/',
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

success "Open Forms configured."

# =============================================================================
# 6. Configure Open Notificaties — Eventloket abonnement
# =============================================================================
echo ""
info "Step 6: Registering Open Notificaties webhook subscriptions..."

run_python "open-notificaties" "
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

success "Open Notificaties webhook subscription registered (placeholder auth). Update with Passport secret from Step 7."

# =============================================================================
# 7. Configure Laravel — Passport client for Open Notificaties
# =============================================================================
echo ""
info "Step 7: Creating Laravel Passport client for Open Notificaties..."

PASSPORT_OUTPUT=$(${COMPOSE_CMD} exec -T laravel.test php artisan passport:client \
    --client \
    --name="open-notificaties" \
    --no-interaction 2>&1) || true

echo "${PASSPORT_OUTPUT}"

PASSPORT_CLIENT_ID=$(echo "${PASSPORT_OUTPUT}"     | grep -E "Client ID" | grep -oE '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}' | tail -1 || echo "unknown")
PASSPORT_CLIENT_SECRET=$(echo "${PASSPORT_OUTPUT}" | grep -iE "Client Secret" | grep '\.\.' | awk '{print $NF}' | tr -d ' ' | head -1 || echo "unknown")

if [ "${PASSPORT_CLIENT_ID}" != "unknown" ] && [ "${PASSPORT_CLIENT_SECRET}" != "unknown" ]; then
    success "Passport client created:"
    echo "  Client ID:     ${PASSPORT_CLIENT_ID}"
    echo "  Client Secret: ${PASSPORT_CLIENT_SECRET}"

    # Update Open Notificaties webhook subscription auth header with actual Passport secret
    run_python "open-notificaties" "
try:
    from nrc.datamodel.models import Abonnement
    notif_endpoint = 'http://laravel.test/api/open-notifications/listen'
    abo = Abonnement.objects.get(callback_url=notif_endpoint)
    abo.auth = 'Bearer ${PASSPORT_CLIENT_SECRET}'
    abo.save()
    print(f'Abonnement auth updated with Passport Bearer token')
except Abonnement.DoesNotExist:
    print(f'Abonnement not found — was Step 6 skipped?')
except Exception as e:
    print(f'Could not update Abonnement auth: {e}')
" || true
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
    FORM_SLUG="evenementformulier-poc-kopie-a6efc0"
    FORM_EXISTS=$(curl -sf \
        -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
        "http://localhost:8003/api/v2/forms/?slug=${FORM_SLUG}" \
        2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print('EXISTS' if d.get('count',0)>0 else 'NOT_EXISTS')" \
        2>/dev/null || echo "NOT_EXISTS")

    if echo "${FORM_EXISTS}" | grep -q "^EXISTS$"; then
        # Get UUID of existing form
        OPEN_FORMS_MAIN_FORM_UUID=$(curl -sf \
            -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
            "http://localhost:8003/api/v2/forms/?slug=${FORM_SLUG}" \
            2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['results'][0]['uuid'] if d.get('count',0)>0 else '')" \
            2>/dev/null || echo "")
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
        IMPORT_RESPONSE=$(curl -sf -X POST \
            http://localhost:8003/api/v2/forms-import \
            -H "Authorization: Token ${OF_ADMIN_TOKEN}" \
            -H "Content-Disposition: attachment;filename=formulier.zip" \
            --form "file=@/tmp/formulier-eventloket.zip;type=application/zip" \
            2>/dev/null || echo '{}')

        # Extract new form UUID from response
        OPEN_FORMS_MAIN_FORM_UUID=$(echo "${IMPORT_RESPONSE}" | \
            python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('uuid',''))" \
            2>/dev/null || echo "")

        if [ -n "${OPEN_FORMS_MAIN_FORM_UUID}" ]; then
            success "Formulier imported (uuid=${OPEN_FORMS_MAIN_FORM_UUID})"
        else
            warn "Formulier import response: ${IMPORT_RESPONSE}"
            warn "Could not determine form UUID — import may have failed."
            warn "Try manually: curl -X POST http://localhost:8003/api/v2/forms-import -H 'Authorization: Token ${OF_ADMIN_TOKEN}' -H 'Content-Disposition: attachment;filename=form.zip' --form 'file=@/tmp/formulier-eventloket.zip'"
            OPEN_FORMS_MAIN_FORM_UUID=""
        fi
    fi
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
echo "  Open Forms:       http://localhost:8003  (admin: admin / admin)"
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
echo "  OBJECTSAPI_URL=http://objects-api:8000"
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
if [ -n "${OPEN_FORMS_MAIN_FORM_UUID:-}" ]; then
    echo ""
    echo "  OPEN_FORMS_MAIN_FORM_UUID=${OPEN_FORMS_MAIN_FORM_UUID}"
fi
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo "  1. Set OPENZAAK_CATALOGI_URL in .env (printed above)"
echo "  2. Run: php artisan openzaak:sync-zaaktypen"
echo "  3. Verify the notification chain: submit a test form in Open Forms →"
echo "     check Open Zaak for a new Zaak → check Laravel logs for notification processing"
echo ""

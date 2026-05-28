#!/usr/bin/env bash
#
# Run Playwright tests via Microsoft's official Playwright Docker image.
#
# Waarom: op de polysynergy-host draait Ubuntu 26.04, wat Playwright
# (vooralsnog) niet als ondersteunde OS uitlevert voor `npx playwright
# install chromium`. Door in plaats daarvan tegen het officiële image
# te runnen — dat een Ubuntu-Noble-base met alle browsers + deps bevat
# — hebben we geen OS-incompatibility meer en is geen sudo nodig.
#
# De container krijgt:
#   - --network=host  → kan eventloket.woweb.home via Tailscale + dnsmasq
#                        bereiken alsof we op de host draaien.
#   - --ipc=host      → voorkomt Chromium's /dev/shm OOM op kleine
#                        shared-memory partitions.
#   - Project-volume gemount op /work, working dir = /work, zodat het
#                        image de project-node_modules + test-files leest
#                        en `test-results/` op de host neerzet.
#   - EF_BASE_URL gezet op de hostname die Traefik routeert; tests
#                        gebruiken die via playwright.config.mjs's
#                        `baseURL: process.env.EF_BASE_URL`.
#
# Gebruik:
#   ./scripts/run-playwright.sh
#       → draait alle specs (config.testMatch)
#   ./scripts/run-playwright.sh tests/Playwright/scenario-map-isolation.spec.mjs
#       → draait alleen die spec
#   ./scripts/run-playwright.sh --reporter=list tests/Playwright/...
#       → extra playwright-flags worden 1-op-1 doorgestuurd
#
# Vereist: de Playwright image moet eenmalig getrokken zijn:
#   docker pull mcr.microsoft.com/playwright:v1.59.1-noble
# Versie matcht @playwright/test in package.json (^1.59.1).

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLAYWRIGHT_IMAGE="mcr.microsoft.com/playwright:v1.59.1-noble"
BASE_URL="${EF_BASE_URL:-http://eventloket.woweb.home}"

cd "${PROJECT_DIR}"

# Draft-DB cleanup gebeurt per test via een HTTP-endpoint
# (POST /_test/reset-draft, alleen in local/testing env). De
# Playwright-container kan dat endpoint via --network=host bereiken,
# dus we hoeven hier geen pre-flight sail-call meer te doen.

# --user $(id -u):$(id -g) zorgt dat artifacts (screenshots/traces/reports)
# als de host-user worden weggeschreven, niet als root.
docker run --rm \
    --network=host \
    --ipc=host \
    --user "$(id -u):$(id -g)" \
    -v "${PROJECT_DIR}:/work" \
    -w /work \
    -e "EF_BASE_URL=${BASE_URL}" \
    -e "HOME=/tmp" \
    "${PLAYWRIGHT_IMAGE}" \
    npx playwright test --workers=1 "$@"

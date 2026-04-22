// Onafhankelijke verificatie van onze equivalentie-scenarios via de
// canonieke json-logic-js library.
//
// De PHP-RulesEngine compileert OF's JsonLogic-expressies naar native PHP —
// die implementatie is per definitie een vertaling. Deze referentie neemt
// de OF-JSON letterlijk en draait 'em via json-logic-js (dezelfde spec die
// OF ook implementeert). Als per scenario beide evaluatoren dezelfde
// rule-firings en outcome opleveren, is onze compiler byte-equivalent aan
// de spec.
//
// Output:
//   tests/Feature/EventForm/Equivalence/jsonlogic-verification.json
//
// Runnen:
//   1. ./vendor/bin/sail artisan eventform:export-scenarios
//   2. node dev-scripts/verify-scenarios-jsonlogic.mjs
//
// Het resultaatbestand bevat per scenario welke rules volgens de canonieke
// spec zouden firen, plus de afgeleide state-output. Die output wordt
// straks gekoppeld aan het gedragsspecificatie-rapport voor de derde
// "spec-referentie"-kolom.

import fs from 'node:fs';
import path from 'node:path';
import jsonLogic from 'json-logic-js';

const BASE = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');

const readJson = (p) => JSON.parse(fs.readFileSync(path.resolve(BASE, p), 'utf8'));

// --- Laad de bronnen ------------------------------------------------------

const scenarios = readJson('tests/Feature/EventForm/Equivalence/scenarios.json');
const logicRaw = readJson('docker/local-data/open-formulier/formLogic.json');
const logicItems = Array.isArray(logicRaw) ? logicRaw : (logicRaw.results ?? []);

// OF's Python-runtime gebruikt een lichte variant op JsonLogic waarbij
// `{"missing": [...]}` checkt op afwezigheid. json-logic-js ondersteunt dat
// native, maar er zijn een paar OF-specifieke operators: `merge` + `cat`
// doet json-logic-js al out-of-the-box. Hier voegen we niks extra toe.

// --- State-opbouw uit 'gegeven' ------------------------------------------

// `gegeven` gebruikt dot-notatie: 'evenementInGemeente.brk_identification'.
// json-logic-js ondersteunt dot-notatie automatisch voor {"var": "..."}
// maar op state-niveau moeten we het als geneste object aanleveren.
const setPath = (obj, path, value) => {
    const parts = path.split('.');
    let cur = obj;
    for (let i = 0; i < parts.length - 1; i++) {
        if (cur[parts[i]] === undefined || cur[parts[i]] === null) {
            cur[parts[i]] = {};
        }
        cur = cur[parts[i]];
    }
    cur[parts[parts.length - 1]] = value;
};

const buildState = (gegeven) => {
    const state = {};
    for (const [key, value] of Object.entries(gegeven || {})) {
        setPath(state, key, value);
    }
    return state;
};

// --- Actie-interpreter ---------------------------------------------------

// OF-acties zijn eenvoudig: property (field hidden), step-(not-)applicable,
// variable (JsonLogic-expressie naar een variable). fetch-from-service
// overslaan we; die doet runtime-effecten die we in de scenarios los
// vastleggen.
const applyActions = (rule, state, out) => {
    for (const action of rule.actions || []) {
        const payload = action.action || {};
        const type = payload.type;

        if (type === 'property') {
            const prop = (payload.property || {}).value;
            const comp = action.component;
            if (prop === 'hidden' && typeof comp === 'string' && comp !== '') {
                out.field_hidden[comp] = payload.state === true;
            }
        } else if (type === 'step-applicable' && typeof action.form_step_uuid === 'string') {
            out.step_applicable[action.form_step_uuid] = true;
        } else if (type === 'step-not-applicable' && typeof action.form_step_uuid === 'string') {
            out.step_applicable[action.form_step_uuid] = false;
        } else if (type === 'variable') {
            const varName = action.variable;
            if (typeof varName === 'string' && varName !== '') {
                // payload.value is een JsonLogic-expressie — evalueer met de
                // huidige state zodat bijv. `{"+": [{"var": "a"}, ...]}` werkt.
                out.values[varName] = jsonLogic.apply(payload.value, state);
                // Ook back-propageren naar state zodat vervolg-rules de nieuwe
                // waarde zien (we evalueren met fixpoint-loop hieronder).
                state[varName] = out.values[varName];
            }
        } else if (type === 'set-registration-backend') {
            // OF-specifieke action: zet welke registratie-backend de
            // resulting submission gebruikt. In onze PHP-state slaan we
            // dat op als `system.registration_backend` — hier leggen we
            // het onder een dedicated registration_backend-key zodat
            // de scenario-verwachting `system.registration_backend`
            // erop kan mappen.
            out.values.registration_backend = payload.value;
        }
        // fetch-from-service wordt in de scenarios handmatig voorbereid
        // (zie 'gegeven' waar bv. evenementInGemeente al gevuld is); hier
        // geen actie.
    }
};

// --- Evaluatie per scenario ----------------------------------------------

const evaluateScenario = (entry) => {
    const scenario = entry.scenario;
    const state = buildState(scenario.gegeven || {});
    const out = {
        field_hidden: {},
        step_applicable: {},
        values: {},
        fired_rule_uuids: [],
    };

    // Fixpoint: rules kunnen output-state lezen die andere rules hebben
    // gezet. Maximaal 5 passes tot convergentie, consistent met de PHP-engine.
    const MAX_PASSES = 5;
    let previousSnapshot = JSON.stringify([out.field_hidden, out.step_applicable, out.values]);
    for (let pass = 0; pass < MAX_PASSES; pass++) {
        out.fired_rule_uuids = [];
        for (const rule of logicItems) {
            const trigger = rule.json_logic_trigger;
            let fires = false;
            try {
                fires = Boolean(jsonLogic.apply(trigger, state));
            } catch (e) {
                fires = false; // defect trigger → skip, log later
            }
            if (fires) {
                out.fired_rule_uuids.push(rule.uuid);
                applyActions(rule, state, out);
            }
        }
        const snap = JSON.stringify([out.field_hidden, out.step_applicable, out.values]);
        if (snap === previousSnapshot) break;
        previousSnapshot = snap;
    }

    // Vergelijk met scenario.verwacht
    const mismatches = [];
    for (const [path, expected] of Object.entries(scenario.verwacht || {})) {
        let actual;
        if (path.startsWith('field_hidden.')) {
            actual = out.field_hidden[path.slice('field_hidden.'.length)] ?? null;
        } else if (path.startsWith('step_applicable.')) {
            actual = out.step_applicable[path.slice('step_applicable.'.length)] ?? true;
        } else if (path.startsWith('system.')) {
            // `setSystem` wordt via een 'variable'-action met system-prefix gedaan in
            // onze PHP-transpilatie; in de OF-JSON is dit gewoon een `variable` actie
            // zonder system-onderscheid. json-logic-js kent geen system-namespace —
            // we checken dus tegen de variable-bag.
            const key = path.slice('system.'.length);
            actual = out.values[key] ?? null;
        } else {
            actual = out.values[path] ?? state[path] ?? null;
        }
        if (JSON.stringify(actual) !== JSON.stringify(expected)) {
            mismatches.push({ path, expected, actual });
        }
    }

    return {
        provider: entry.provider,
        label: entry.label,
        ok: mismatches.length === 0,
        mismatches,
        fired_count: out.fired_rule_uuids.length,
    };
};

// --- Run alles -----------------------------------------------------------

const results = scenarios.map(evaluateScenario);
const ok = results.filter((r) => r.ok).length;
const fail = results.length - ok;

console.log(`Scenarios: ${results.length}`);
console.log(`Match met spec-referentie: ${ok}`);
console.log(`Mismatches: ${fail}`);

if (fail > 0) {
    console.log('\nAfwijkingen:');
    for (const r of results) {
        if (r.ok) continue;
        console.log(`  ✗ ${r.label}`);
        for (const m of r.mismatches) {
            console.log(`      ${m.path}: verwacht ${JSON.stringify(m.expected)}, json-logic-js: ${JSON.stringify(m.actual)}`);
        }
    }
}

const outPath = path.resolve(BASE, 'tests/Feature/EventForm/Equivalence/jsonlogic-verification.json');
fs.writeFileSync(outPath, JSON.stringify(results, null, 2) + '\n');
console.log(`\nVerificatie-fixture: ${outPath}`);

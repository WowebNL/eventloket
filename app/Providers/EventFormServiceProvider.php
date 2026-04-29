<?php

declare(strict_types=1);

namespace App\Providers;

use App\EventForm\Rules\Rule;
use App\EventForm\Rules\RuleRegistry;
use App\EventForm\Rules\RulesEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Registreert de RulesEngine met alle gegenereerde Rule-klassen uit
 * `App\EventForm\Rules\RuleRegistry::all()`.
 *
 * RuleRegistry wordt door `transpile:event-form` (her)geschreven en bevat
 * expliciete `::class`-references — dat houdt static-analysis (PhpStorm,
 * PHPStan) blij én maakt de boot-volgorde van rules deterministisch.
 */
class EventFormServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RulesEngine::class, function (): RulesEngine {
            return new RulesEngine($this->discoverRules());
        });
    }

    /**
     * @return list<Rule>
     */
    private function discoverRules(): array
    {
        // Voor een fresh project waar `transpile:event-form` nog niet is gedraaid
        // bestaat RuleRegistry nog niet. In dat geval starten we met een lege
        // RulesEngine i.p.v. een hard fail.
        if (! class_exists(RuleRegistry::class)) {
            return [];
        }

        $rules = [];
        foreach (RuleRegistry::all() as $fqcn) {
            $shortName = (string) (new \ReflectionClass($fqcn))->getShortName();
            if (in_array($shortName, self::GEMIGREERDE_RULES, true)) {
                // Pure-functioneel gedekt door FormDerivedState,
                // FormFieldVisibility of FormStepApplicability — engine
                // hoeft 'm niet meer te draaien.
                continue;
            }
            $rules[] = $this->app->make($fqcn);
        }

        return $rules;
    }

    /**
     * Rules die volledig gemigreerd zijn naar pure-functionele logic in
     * de FormDerivedState / FormFieldVisibility / FormStepApplicability
     * classes. Engine slaat ze over om dubbel werk te voorkomen.
     *
     * Lijst is gegenereerd via `dev-scripts/find-dead-rules.php` — bij
     * elke verdere migratie hier aanvullen, of helemaal vervangen
     * wanneer engine + transpiler weg zijn.
     *
     * @var list<string>
     */
    private const GEMIGREERDE_RULES = [
        'AlsBool',
        'AlsBool00876823',
        'AlsBoolEnBoolWatisdebelangrijksteleeftijdscatego',
        'AlsBoolEnIsNietGelijkAanNone580a3ef8',
        'AlsBoolEnReductieVan1Accumul',
        'AlsIsGelijkAan',
        'AlsIsGelijkAanBOfIsGelijkAanC',
        'AlsIsGelijkAanJa',
        'AlsIsGelijkAanJaEnBool',
        'AlsIsGelijkAanJaEnBool172fe1ad',
        'AlsIsGelijkAanJaEnBool4e042329',
        'AlsIsGelijkAanJaEnBoolC7431a0c',
        'AlsIsGelijkAanJaEnBoolGemeentevar',
        'AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti',
        'AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti981e2b88',
        'AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiB741d925',
        'AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiEa096e0f',
        'AlsIsGelijkAanJaOfVindendeactivitei',
        'AlsIsGelijkAanNeeEnTrueAlsOntbrek',
        'AlsIsGelijkAanNeeOfVindendeactivite',
        'AlsReductieVan1BeginnendBij0IsGelijkA',
        'AlsReductieVan1BeginnendBij0IsGroterD',
        'Rule03a87183',
        'Rule0a5531ff',
        'Rule0ab47106',
        'Rule0c026fb1',
        'Rule145ceec2',
        'Rule199313af',
        'Rule21e363f3',
        'Rule2a01382c',
        'Rule2bbecc17',
        'Rule2d10885d',
        'Rule2e67feb4',
        'Rule32f9bd89',
        'Rule35501489',
        'Rule3a1ac5f3',
        'Rule3d9f1e6c',
        'Rule457c34ac',
        'Rule4a05099f',
        'Rule4e724924',
        'Rule565bccec',
        'Rule5e689e7d',
        'Rule615d524a',
        'Rule6b2aeed1',
        'Rule6cda93b8',
        'Rule6f1046a6',
        'Rule72e81725',
        'Rule79be7168',
        'Rule7b13e485',
        'Rule7b285070',
        'Rule8893efa1',
        'Rule889aed1d',
        'Rule8aa421de',
        'Rule8e1a11b9',
        'Rule8f418d89',
        'Rule935dc38c',
        'Rule945f1606',
        'Rule9ac0b4c7',
        'Rule9b066ee5',
        'RuleAcc04d68',
        'RuleAd564ba5',
        'RuleAd8eb74d',
        'RuleB0b1b8ed',
        'RuleB4fefcd8',
        'RuleB782fae6',
        'RuleB92d2e5a',
        'RuleBf2ee2f8',
        'RuleC1117aff',
        'RuleD138e53e',
        'RuleD566bba6',
        'RuleD5681327',
        'RuleD8d28395',
        'RuleDcd1e4b3',
        'RuleE0d010cd',
        'RuleE21a3eae',
        'RuleE8e0f322',
        'RuleE9cf76d6',
        'RuleF494443a',
        'RuleF5363d0b',
        'RuleFaa5fae6',
    ];
}

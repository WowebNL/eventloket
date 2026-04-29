<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

/**
 * Auto-gegenereerd door `php artisan transpile:event-form`. Niet handmatig
 * aanpassen — wijzigingen worden bij de volgende transpile-run overschreven.
 *
 * Dit registry maakt twee dingen mogelijk:
 *  - PhpStorm en andere static-analysis tools zien expliciete
 *    `::class`-references naar elke Rule, zodat ze niet als "no usages"
 *    gemarkeerd worden.
 *  - `EventFormServiceProvider` bouwt z'n RulesEngine vanuit deze
 *    deterministische lijst i.p.v. een filesystem-scan.
 *
 * De lijst bevat zowel de getranspileerde JsonLogic-rules als een aantal
 * handgeschreven aanvullingen voor gedrag dat in OF in de form-config
 * zat (niet in de logic-rules) — zie de `HANDGESCHREVEN_RULES`-constante
 * in `TranspileEventForm`.
 */
final class RuleRegistry
{
    /** @return list<class-string<Rule>> */
    public static function all(): array
    {
        return [
            AlsBool::class,
            AlsBool00876823::class,
            AlsBool47620576::class,
            AlsBoolEn::class,
            AlsBoolEnBoolEnBoolEvenementingemeenteBrkIdentificat::class,
            AlsBoolEnBoolWatisdebelangrijksteleeftijdscatego::class,
            AlsBoolEnIsN::class,
            AlsBoolEnIsN0f284f5c::class,
            AlsBoolEnIsNie::class,
            AlsBoolEnIsNietGeli::class,
            AlsBoolEnIsNietGelijkAanNone::class,
            AlsBoolEnIsNietGelijkAanNone580a3ef8::class,
            AlsBoolEnIsNietGelijkAanNone599a6cfd::class,
            AlsBoolEnIsNietGelijkAanNone99b8a502::class,
            AlsBoolEnIsNietGelijkAanNoneBd328413::class,
            AlsBoolEnReductieVan1Accumul::class,
            AlsIsGelijkAan::class,
            AlsIsGelijkAanBOfIsGelijkAanC::class,
            AlsIsGelijkAanGm0882En::class,
            AlsIsGelijkAanGm0882EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0882EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0888En::class,
            AlsIsGelijkAanGm0888EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0888EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0899En::class,
            AlsIsGelijkAanGm0899EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0899EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0917EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0917EnWaarvoorwiltueventloke396c72d1::class,
            AlsIsGelijkAanGm0917EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0928En::class,
            AlsIsGelijkAanGm0928EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0928EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0938En::class,
            AlsIsGelijkAanGm0938EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0938EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0965En::class,
            AlsIsGelijkAanGm0965EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0965EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0971En::class,
            AlsIsGelijkAanGm0971EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0971EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0981En::class,
            AlsIsGelijkAanGm0981EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0981EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0986En::class,
            AlsIsGelijkAanGm0986EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0986EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm0994En::class,
            AlsIsGelijkAanGm0994EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm0994EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm1729En::class,
            AlsIsGelijkAanGm1729EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm1729EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm1883En::class,
            AlsIsGelijkAanGm1883EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm1883EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm1903En::class,
            AlsIsGelijkAanGm1903EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm1903EnWordenergebiedsontslui::class,
            AlsIsGelijkAanGm1954En::class,
            AlsIsGelijkAanGm1954EnWaarvoorwiltueventloke::class,
            AlsIsGelijkAanGm1954EnWordenergebiedsontslui::class,
            AlsIsGelijkAanJa::class,
            AlsIsGelijkAanJaEnBool::class,
            AlsIsGelijkAanJaEnBool172fe1ad::class,
            AlsIsGelijkAanJaEnBool4e042329::class,
            AlsIsGelijkAanJaEnBoolC7431a0c::class,
            AlsIsGelijkAanJaEnBoolGemeentevar::class,
            AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti::class,
            AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuesti981e2b88::class,
            AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiB741d925::class,
            AlsIsGelijkAanJaEnTrueAlsOntbrekendGemeentevariabelenReportQuestiEa096e0f::class,
            AlsIsGelijkAanJaOfVindendeactivitei::class,
            AlsIsGelijkAanNeeEnTrueAlsOntbrek::class,
            AlsIsGelijkAanNeeOfVindendeactivite::class,
            AlsIsGelijkAanNone::class,
            AlsIsGelijkAanTrueEnReductieVanEvenemen::class,
            AlsIsNietGelijkAanEnIsGelijkAanFa::class,
            AlsIsNietGelijkAanEnIsNietGe::class,
            AlsIsNietGelijkAanNone::class,
            AlsIsNietGelijkAanPostcodeHouseletterHousenumberHo::class,
            AlsReductieVan1BeginnendBij0IsGelijkA::class,
            AlsReductieVan1BeginnendBij0IsGroterD::class,
            Rule03a87183::class,
            Rule0a5531ff::class,
            Rule0ab47106::class,
            Rule0c026fb1::class,
            Rule145ceec2::class,
            Rule199313af::class,
            Rule2057ca5a::class,
            Rule21e363f3::class,
            Rule2a01382c::class,
            Rule2bbecc17::class,
            Rule2d10885d::class,
            Rule2e67feb4::class,
            Rule32f9bd89::class,
            Rule35501489::class,
            Rule3a1ac5f3::class,
            Rule3d9f1e6c::class,
            Rule457c34ac::class,
            Rule4a05099f::class,
            Rule4e724924::class,
            Rule565bccec::class,
            Rule5e689e7d::class,
            Rule615d524a::class,
            Rule6b2aeed1::class,
            Rule6cda93b8::class,
            Rule6f1046a6::class,
            Rule72e81725::class,
            Rule79be7168::class,
            Rule7b13e485::class,
            Rule7b285070::class,
            Rule8893efa1::class,
            Rule889aed1d::class,
            Rule8aa421de::class,
            Rule8e1a11b9::class,
            Rule8f418d89::class,
            Rule935dc38c::class,
            Rule945f1606::class,
            Rule9ac0b4c7::class,
            Rule9b066ee5::class,
            RuleAcc04d68::class,
            RuleAd564ba5::class,
            RuleAd8eb74d::class,
            RuleB0b1b8ed::class,
            RuleB4fefcd8::class,
            RuleB782fae6::class,
            RuleB92d2e5a::class,
            RuleBf2ee2f8::class,
            RuleC1117aff::class,
            RuleD138e53e::class,
            RuleD566bba6::class,
            RuleD5681327::class,
            RuleD8d28395::class,
            RuleDcd1e4b3::class,
            RuleE0d010cd::class,
            RuleE21a3eae::class,
            RuleE8e0f322::class,
            RuleE9cf76d6::class,
            RuleF494443a::class,
            RuleF5363d0b::class,
            RuleF56a54dd::class,
            RuleFaa5fae6::class,
            VergunningSchakeltMeldingUit::class,
            MeldingSchakeltVergunningstappenUit::class,
        ];
    }

    public static function count(): int
    {
        return 146;
    }
}

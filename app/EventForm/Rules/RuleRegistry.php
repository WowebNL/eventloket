<?php

declare(strict_types=1);

namespace App\EventForm\Rules;

/**
 * Lijst van rules die de RulesEngine moet draaien. Sinds de
 * pure-functionele migratie (FormDerivedState / FormFieldVisibility /
 * FormStepApplicability / FormSystemDerivedState) zijn 125 oude
 * transpiled rules verwijderd; alleen nog rules met side-effects of
 * prefill-werk dat niet nuttig in een pure-functionele methode past.
 *
 * Wordt niet meer gegenereerd — wijzig deze file handmatig wanneer een
 * rule (de)gemigreerd wordt.
 */
final class RuleRegistry
{
    /** @return list<class-string<Rule>> */
    public static function all(): array
    {
        return [
            // Fetch-from-service (HTTP-zijwerking).
            AlsBool47620576::class,
            AlsBoolEnBoolEnBoolEvenementingemeenteBrkIdentificat::class,
            AlsBoolEnIsNietGelijkAanNone::class,
            AlsBoolEnIsNietGelijkAanNone599a6cfd::class,
            AlsBoolEnIsNietGelijkAanNone99b8a502::class,
            AlsBoolEnIsNietGelijkAanNoneBd328413::class,
            Rule2057ca5a::class,

            // Prefill-rules (kopieer eventloketSession-data naar user-fields).
            AlsBoolEn::class,
            AlsBoolEnIsN::class,
            AlsBoolEnIsN0f284f5c::class,
            AlsBoolEnIsNie::class,
            AlsBoolEnIsNietGeli::class,
            RuleF56a54dd::class,

            // Prefill van zaak-snapshot bij "nieuwe aanvraag met deze
            // gegevens"-flow.
            AlsIsNietGelijkAanEnIsGelijkAanFa::class,

            // addressToCheck / addressesToCheck — edge-cases uit oudere OF-versie.
            AlsIsGelijkAanNone::class,
            AlsIsNietGelijkAanEnIsNietGe::class,
            AlsIsNietGelijkAanNone::class,
            AlsIsNietGelijkAanPostcodeHouseletterHousenumberHo::class,

            // Edge-case: clear userSelectGemeente onder bepaalde conditie.
            AlsIsGelijkAanTrueEnReductieVanEvenemen::class,

            // Hand-geschreven aanvullingen voor logica die OF in de
            // form-config had staan (niet in JsonLogic-rules).
            VergunningSchakeltMeldingUit::class,
            MeldingSchakeltVergunningstappenUit::class,
        ];
    }

    public static function count(): int
    {
        return count(self::all());
    }
}

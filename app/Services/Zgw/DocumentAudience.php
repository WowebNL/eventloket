<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Enums\DocumentVertrouwelijkheden;
use App\Enums\Role;

/**
 * Answers "who really gets to see a document at this vertrouwelijkheid level on
 * this connection", for the upload choice in the interface.
 *
 * The answer is read from the connection's vertrouwelijkheid map through
 * {@see ZgwConnectionConfig::documentVisibilityForRole()} (which falls back to
 * the hardcoded per-role defaults), so the choice offered at upload can never
 * promise an audience the filtering does not honour.
 */
class DocumentAudience
{
    /**
     * The role groups whose document visibility is configurable per connection,
     * ordered from the broadest to the narrowest default audience.
     *
     * The first role of a group is the canonical one: the connection form binds
     * to it and fans its value out over the other roles of the group on save
     * (see MunicipalityZgwConnectionResource::pruneVertrouwelijkheidMap()), so
     * the canonical role's visibility describes the whole group. Roles outside
     * these groups (platform admin, koppeling beheerder) are not configurable
     * and always see everything, which is why they carry no audience label.
     *
     * @return array<int, array{label: string, audience: string, roles: array<int, Role>}>
     */
    public static function groups(): array
    {
        return [
            [
                'label' => __('municipality/resources/zgw_connection.vertrouwelijkheid_groups.gemeente'),
                'audience' => __('municipality/resources/zgw_connection.vertrouwelijkheid_groups.gemeente_audience'),
                'roles' => [
                    Role::Reviewer,
                    Role::Coordinator,
                    Role::MunicipalityAdmin,
                    Role::ReviewerMunicipalityAdmin,
                ],
            ],
            [
                'label' => Role::Advisor->getLabel(),
                'audience' => Role::Advisor->getLabel(),
                'roles' => [Role::Advisor],
            ],
            [
                'label' => Role::Organiser->getLabel(),
                'audience' => Role::Organiser->getLabel(),
                'roles' => [Role::Organiser],
            ],
        ];
    }

    /**
     * The vertrouwelijkheid levels a user of the given role may pick when
     * uploading a document, each labelled with the role groups that may see that
     * level on this connection.
     *
     * The levels on offer are derived from the connection itself: the default
     * connection (and any connection without its own map) keeps the fixed
     * {@see DocumentVertrouwelijkheden::uploadChoices()}, while a connection with
     * its own vertrouwelijkheid map offers the maxima that map configures, so the
     * ladder follows the settings rather than a hardcoded list. Levels reaching
     * the same audience are collapsed into a single rung.
     *
     * Returns an empty array when the choice would be an illusion: nothing left
     * to pick, or every offered level reaching exactly the same audience (the
     * situation VRZL reported, where a connection map gives the municipal roles
     * every level and the other groups none of them). The caller then leaves the
     * select out and the connection's upload default applies.
     *
     * @return array<string, string>
     */
    public static function uploadOptions(string $connectionName, Role $role): array
    {
        $visibleToUploader = ZgwConnectionConfig::documentVisibilityForRole($connectionName, $role);

        $audiences = [];

        foreach (self::candidateLevels($connectionName) as $level) {
            if (! in_array($level, $visibleToUploader, true)) {
                continue;
            }

            $audiences[$level] = self::audienceFor($connectionName, $level);
        }

        $rungs = self::distinctRungs($audiences);

        // A single rung (or none) is no real choice: the caller then leaves the
        // select out and the connection's upload default applies.
        if (count($rungs) < 2) {
            return [];
        }

        return array_map(
            static fn (array $audience): string => $audience === []
                ? __('Geen van deze rolgroepen')
                : implode(', ', $audience),
            $rungs,
        );
    }

    /**
     * The vertrouwelijkheid levels to consider for the upload choice on this
     * connection.
     *
     * The default connection, and any connection without its own visibility map,
     * keeps the fixed {@see DocumentVertrouwelijkheden::uploadChoices()}, so its
     * behaviour is unchanged. A connection with its own map offers the maxima it
     * configures per role group, ordered from the least to the most confidential.
     *
     * Only the maxima can be rungs. A level below a group's maximum is seen by
     * exactly the role groups that see that maximum, so it reaches the same
     * audience and collapses into the same rung; taking the maxima directly says
     * that in one step.
     *
     * @return array<int, string>
     */
    private static function candidateLevels(string $connectionName): array
    {
        if (ZgwConnectionConfig::isDefaultConnection($connectionName)) {
            return DocumentVertrouwelijkheden::uploadChoices();
        }

        $configured = ZgwConnectionConfig::configuredVisibilityMaxLevels($connectionName);

        return $configured === [] ? DocumentVertrouwelijkheden::uploadChoices() : $configured;
    }

    /**
     * Collapse the level => audience map to one rung per distinct audience,
     * keeping the most confidential level that reaches it (so a rung stays as
     * restrictive as its audience allows, and the default connection reproduces
     * its {zaakvertrouwelijk, vertrouwelijk, confidentieel} ladder exactly), and
     * order the rungs from the broadest to the narrowest audience.
     *
     * @param  array<string, array<int, string>>  $audiences  level => audience labels, least confidential first
     * @return array<string, array<int, string>> representative level => audience labels
     */
    private static function distinctRungs(array $audiences): array
    {
        $byAudience = [];

        // $audiences runs from the least to the most confidential level, so the
        // last level written for a given audience is the most confidential one
        // reaching it.
        foreach ($audiences as $level => $audience) {
            $byAudience[implode('|', $audience)] = ['level' => $level, 'audience' => $audience];
        }

        // Broadest audience first. Nested visibility makes the audiences a chain
        // by inclusion, so their sizes order them unambiguously.
        usort(
            $byAudience,
            static fn (array $a, array $b): int => count($b['audience']) <=> count($a['audience']),
        );

        $rungs = [];

        foreach ($byAudience as $rung) {
            $rungs[$rung['level']] = $rung['audience'];
        }

        return $rungs;
    }

    /**
     * The labels of the role groups that may see a document at this level on
     * this connection, broadest audience first.
     *
     * @return array<int, string>
     */
    public static function audienceFor(string $connectionName, string $level): array
    {
        $labels = [];

        foreach (self::groups() as $group) {
            $visibility = ZgwConnectionConfig::documentVisibilityForRole($connectionName, $group['roles'][0]);

            if (in_array($level, $visibility, true)) {
                $labels[] = $group['audience'];
            }
        }

        return $labels;
    }
}

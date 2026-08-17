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

        foreach (DocumentVertrouwelijkheden::uploadChoices() as $level) {
            if (! in_array($level, $visibleToUploader, true)) {
                continue;
            }

            $audiences[$level] = self::audienceFor($connectionName, $level);
        }

        if (! self::audiencesDiffer($audiences)) {
            return [];
        }

        return array_map(
            static fn (array $audience): string => $audience === []
                ? __('Geen van deze rolgroepen')
                : implode(', ', $audience),
            $audiences,
        );
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

    /**
     * Whether the offered levels tell the role groups apart at all. Two levels
     * reaching the same audience are the same choice under a different name, and
     * a single level (or none) is no choice to begin with.
     *
     * @param  array<string, array<int, string>>  $audiences
     */
    private static function audiencesDiffer(array $audiences): bool
    {
        $distinct = array_unique(array_map(
            static fn (array $audience): string => implode('|', $audience),
            $audiences,
        ));

        return count($distinct) > 1;
    }
}

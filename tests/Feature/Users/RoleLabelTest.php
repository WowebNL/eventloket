<?php

declare(strict_types=1);

/**
 * The four municipality roles are named consistently across the whole
 * application: every screen resolves them through Role::getLabel(), so
 * pinning the labels here catches a rename in one panel that was not
 * carried through to the others.
 */

use App\Enums\Role;

test('the municipality roles carry their agreed labels', function (Role $role, string $label) {
    expect($role->getLabel())->toBe($label);
})->with([
    'behandelaar' => [Role::Reviewer, 'Behandelaar'],
    'coördinator' => [Role::Coordinator, 'Coördinator (+behandelaar)'],
    'gemeentelijk beheerder' => [Role::MunicipalityAdmin, 'Gemeentelijk beheerder'],
    'gemeentelijk beheerder met behandelaarsrol' => [Role::ReviewerMunicipalityAdmin, 'Gemeentelijk beheerder (+behandelaar)'],
]);

test('every role resolves a label instead of falling back to its translation key', function () {
    foreach (Role::cases() as $role) {
        expect($role->getLabel())
            ->not->toContain('enums/role')
            ->and($role->getLabel())->not->toBe('');
    }
});

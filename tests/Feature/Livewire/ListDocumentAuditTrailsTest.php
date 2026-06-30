<?php

use App\Livewire\Zaken\ListDocumentAuditTrails;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

use function Pest\Livewire\livewire;

it('locks the audittrail property against client tampering', function () {
    $audittrail = [
        ['aanmaakdatum' => now()->toDateTimeString(), 'actieWeergave' => 'create', 'applicatieWeergave' => 'App', 'gebruikersWeergave' => 'User'],
    ];

    livewire(ListDocumentAuditTrails::class, ['audittrail' => $audittrail])
        ->set('audittrail', [['actieWeergave' => 'forged']]);
})->throws(CannotUpdateLockedPropertyException::class);

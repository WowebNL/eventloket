<?php

namespace App\Livewire;

use App\Services\TableStatePersister;
use Livewire\ComponentHook;

/**
 * Global Livewire hook that wires database persistence into every Filament
 * table component automatically - no trait or base class required.
 *
 * Lifecycle ordering:
 *   - boot()      fires before the component's own `bootedInteractsWithTable()`
 *                  reads the session, so we seed database -> session here and
 *                  Filament then restores from it.
 *   - dehydrate() fires at the end of the request, after Filament has written
 *                  the latest state to the session. We snapshot session -> database.
 */
class PersistTableStateHook extends ComponentHook
{
    public function boot(): void
    {
        $this->persister()->seed($this->component);
    }

    public function dehydrate(): void
    {
        $this->persister()->snapshot($this->component);
    }

    protected function persister(): TableStatePersister
    {
        return app(TableStatePersister::class);
    }
}

<?php

declare(strict_types=1);

namespace App\EventForm\Persistence;

/**
 * Gegooid door DraftStore::create() wanneer de gebruiker het maximum
 * aantal concepten (DraftStore::MAX_DRAFTS) al heeft bereikt.
 */
class DraftLimitReached extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(sprintf(
            'Maximum van %d concepten bereikt.',
            DraftStore::MAX_DRAFTS,
        ));
    }
}

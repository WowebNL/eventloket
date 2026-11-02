<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

/**
 * The outcome of registering an Open Notificaties abonnement for one connection,
 * so both the console command and the UI action can report what happened.
 */
enum AbonnementRegistrationOutcome
{
    case Created;
    case Updated;
    case SkippedNoNotificatiesUrl;
    /**
     * Not registered: another connection already holds the abonnement on this
     * connection's Notificaties API (see {@see SharedNotificatiesInstance}).
     */
    case RefusedSharedInstance;
    case DryRun;
}

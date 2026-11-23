<?php

declare(strict_types=1);

namespace App\Services\Notificaties;

use RuntimeException;

/**
 * A connection's webhook_auth setting holds a value that is not a known
 * credential type. Raised before anything is issued.
 */
final class InvalidWebhookAuthException extends RuntimeException {}

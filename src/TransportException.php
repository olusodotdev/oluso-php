<?php

declare(strict_types=1);

namespace Oluso;

/**
 * Raised when a report fails to send. Carries no special data -- callers
 * only need to know it failed, so it can be queued for retry.
 */
final class TransportException extends \RuntimeException
{
}

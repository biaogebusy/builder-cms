<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/**
 * A custom-platform job has no stored credentials (never stored, expired or already cleaned up).
 *
 * Mapped to the error code `unauthorized`: retrying cannot bring the key back,
 * the user has to submit the job again.
 */
final class MissingCredentialsException extends \RuntimeException {}

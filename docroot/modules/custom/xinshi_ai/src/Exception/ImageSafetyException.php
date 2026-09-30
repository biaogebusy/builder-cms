<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/**
 * A rejected image or remote destination; messages never include the input.
 */
class ImageSafetyException extends \DomainException {}

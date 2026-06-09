<?php

namespace Drupal\ai\Exception;

/**
 * Error for when some moderation kicks in.
 */
class AiUnsafePromptException extends \Exception implements AiExceptionInterface {
}

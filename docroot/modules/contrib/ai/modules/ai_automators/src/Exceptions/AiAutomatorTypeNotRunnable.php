<?php

namespace Drupal\ai_automators\Exceptions;

use Drupal\ai\Exception\AiExceptionInterface;

/**
 * Error when not being able to run.
 */
class AiAutomatorTypeNotRunnable extends \Exception implements AiExceptionInterface {
}

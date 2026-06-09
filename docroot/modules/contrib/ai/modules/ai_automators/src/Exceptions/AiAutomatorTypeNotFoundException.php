<?php

namespace Drupal\ai_automators\Exceptions;

use Drupal\ai\Exception\AiExceptionInterface;

/**
 * Error when not finding a type.
 */
class AiAutomatorTypeNotFoundException extends \Exception implements AiExceptionInterface {}

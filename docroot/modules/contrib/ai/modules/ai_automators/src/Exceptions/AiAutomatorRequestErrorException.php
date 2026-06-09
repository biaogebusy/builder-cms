<?php

namespace Drupal\ai_automators\Exceptions;

use Drupal\ai\Exception\AiExceptionInterface;

/**
 * Error for when an API response wasn't correct.
 */
class AiAutomatorRequestErrorException extends \Exception implements AiExceptionInterface {}

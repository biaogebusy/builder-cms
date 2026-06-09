<?php

namespace Drupal\ai_automators\Exceptions;

use Drupal\ai\Exception\AiExceptionInterface;

/**
 * Error when not finding a rule.
 */
class AiAutomatorRuleNotFoundException extends \Exception implements AiExceptionInterface {}

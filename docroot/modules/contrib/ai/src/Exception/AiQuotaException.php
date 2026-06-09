<?php

namespace Drupal\ai\Exception;

/**
 * Error for when you run out of credits.
 */
class AiQuotaException extends \Exception implements AiExceptionInterface {
}

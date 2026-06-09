<?php

namespace Drupal\ai\Exception;

/**
 * Error for when you run into rate limits.
 */
class AiRateLimitException extends \Exception implements AiExceptionInterface {
}

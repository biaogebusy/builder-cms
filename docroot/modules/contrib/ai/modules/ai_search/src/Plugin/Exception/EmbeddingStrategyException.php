<?php

namespace Drupal\ai_search\Plugin\Exception;

use Drupal\Component\Plugin\Exception\ExceptionInterface;

/**
 * An exception class to be thrown for embedding strategy exceptions.
 */
class EmbeddingStrategyException extends \Exception implements ExceptionInterface {}

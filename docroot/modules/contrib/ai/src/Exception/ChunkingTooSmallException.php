<?php

namespace Drupal\ai\Exception;

/**
 * Error for when the expected chunk is smaller than constraints.
 */
class ChunkingTooSmallException extends \Exception implements AiExceptionInterface {
}

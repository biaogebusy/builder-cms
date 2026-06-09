<?php

namespace Drupal\ai\Exception;

/**
 * Error for when the client or a model denies access, even when setup.
 */
class AiAccessDeniedException extends \Exception implements AiExceptionInterface {
}

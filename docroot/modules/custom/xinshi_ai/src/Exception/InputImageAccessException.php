<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/**
 * An unavailable or unauthorized input, without disclosing which one it is.
 */
class InputImageAccessException extends \DomainException {

  public function __construct() {
    parent::__construct('Input image is unavailable or access is denied.');
  }

}

<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Exception;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Access denied exception definition.
 *
 * @package Drupal\forms_steps\Exception
 */
class AccessDeniedException extends AccessDeniedHttpException {

}

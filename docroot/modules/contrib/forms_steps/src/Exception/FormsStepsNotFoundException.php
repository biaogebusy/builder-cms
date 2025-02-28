<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Exception definition for Forms Steps not found.
 *
 * @package Drupal\forms_steps\Exception
 */
class FormsStepsNotFoundException extends NotFoundHttpException {

}

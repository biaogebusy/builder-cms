<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Service;

use Drupal\forms_steps\Step;

/**
 * Helper service for the steps routes.
 *
 * @package Drupal\forms_steps\Service
 */
class RouteHelper {

  /**
   * Return the internal URL.
   *
   * @param \Drupal\forms_steps\Step $step
   *   The step in question.
   * @param string $instance_id
   *   The instance of the step to target.
   *
   * @return string
   *   The internal URL.
   */
  public static function getStepUrl(Step $step, string $instance_id): string {
    return $step->url() . "/$instance_id";
  }

}

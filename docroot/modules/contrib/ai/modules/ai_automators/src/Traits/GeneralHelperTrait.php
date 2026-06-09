<?php

namespace Drupal\ai_automators\Traits;

/**
 * Trait to add the possibility to store output base64 encoded strings.
 *
 * @package Drupal\ai_automators\Traits
 */
trait GeneralHelperTrait {

  /**
   * Gets the general helper.
   *
   * @return \Drupal\ai_automators\Rulehelpers\GeneralHelper
   *   The general helper.
   */
  public function getGeneralHelper() {
    return \Drupal::service('ai_automator.rule_helper.general');
  }

}

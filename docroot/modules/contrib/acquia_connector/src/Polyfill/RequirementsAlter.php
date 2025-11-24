<?php

declare(strict_types=1);

namespace Drupal\acquia_connector\Polyfill;

use Drupal\system\SystemManager;

/**
 * Decorates the SystemManager to add `hook_requirements_alter` polyfill.
 */
final class RequirementsAlter extends SystemManager {

  /**
   * {@inheritdoc}
   */
  public function listRequirements(): array {
    $requirements = parent::listRequirements();
    // The `requirements_alter` hook was introduced in 9.5.0. We add a polyfill
    // for previous versions of Drupal.
    if (version_compare(\Drupal::VERSION, '9.5.0', '<')) {
      $this->moduleHandler->alter('requirements', $requirements);
    }
    return $requirements;
  }

}

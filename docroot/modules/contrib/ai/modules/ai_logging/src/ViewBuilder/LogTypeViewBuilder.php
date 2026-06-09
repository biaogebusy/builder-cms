<?php

namespace Drupal\ai_logging\ViewBuilder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Render the Log Type.
 */
class LogTypeViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  public function build(array $build) {
    $build = parent::build($build);

    // Get the entity from the build array.
    if (isset($build['#ai_log_type']) && $build['#ai_log_type'] instanceof ConfigEntityInterface) {
      $build['label'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $build['#ai_log_type']->label(),
      ];
    }

    return $build;
  }

}

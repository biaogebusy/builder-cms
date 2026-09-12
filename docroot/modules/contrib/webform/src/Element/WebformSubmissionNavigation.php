<?php

namespace Drupal\webform\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a render element to display webform submission navigation.
 *
 * @RenderElement("webform_submission_navigation")
 */
class WebformSubmissionNavigation extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#theme' => 'webform_submission_navigation',
      '#webform_submission' => NULL,
    ];
  }

}

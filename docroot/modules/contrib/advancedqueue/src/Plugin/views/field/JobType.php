<?php

namespace Drupal\advancedqueue\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to render a human readable job type label.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("advancedqueue_job_type")
 */
class JobType extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $type = $this->getValue($values);
    $job_type_options = self::getOptions();
    if (isset($job_type_options[$type])) {
      return $job_type_options[$type];
    }
    // At least render the machine name, it can't hurt.
    return parent::render($values);
  }

  /**
   * Gets the available job type options.
   *
   * @return array
   *   The job type labels, keyed by ID.
   */
  public static function getOptions() {
    /** @var \Drupal\advancedqueue\JobTypeManager $job_type_manager */
    $job_type_manager = \Drupal::service('plugin.manager.advancedqueue_job_type');
    return array_map(function ($definition) {
      return $definition['label'];
    }, $job_type_manager->getDefinitions());
  }

}

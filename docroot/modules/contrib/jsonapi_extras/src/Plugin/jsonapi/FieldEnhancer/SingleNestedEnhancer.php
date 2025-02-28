<?php

namespace Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer;

use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Shaper\Util\Context;

/**
 * Perform additional manipulations to date fields.
 *
 * @ResourceFieldEnhancer(
 *   id = "nested",
 *   label = @Translation("Single Nested Property"),
 *   description = @Translation("Extracts or wraps nested properties from an object.")
 * )
 */
class SingleNestedEnhancer extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'path' => 'value',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $output = $data;
    $configuration = $this->getConfiguration();
    $path = $configuration['path'];
    $path_parts = explode('.', $path);
    // Start drilling down until there are no more path parts.
    while ($output && ($path_part = array_shift($path_parts))) {
      $output = empty($output[$path_part])
        ? NULL
        : $output[$path_part];
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context) {
    $input = $data;
    $configuration = $this->getConfiguration();
    $path = $configuration['path'];
    $path_parts = explode('.', $path);
    // Start wrapping up until there are no more path parts.
    while ($path_part = array_pop($path_parts)) {
      $input = [$path_part => $input];
    }
    return $input;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'oneOf' => [
        ['type' => 'string'],
        ['type' => 'null'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsForm(array $resource_field_info) {
    $settings = empty($resource_field_info['enhancer']['settings'])
      ? $this->getConfiguration()
      : $resource_field_info['enhancer']['settings'];

    return [
      'path' => [
        '#type' => 'textfield',
        '#title' => $this->t('Path'),
        '#description' => $this->t('A dot separated path to extract the sub-property.'),
        '#default_value' => $settings['path'],
      ],
    ];
  }

}

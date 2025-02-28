<?php

namespace Drupal\jsonapi_extras\Plugin;

/**
 * Base class for date and time based resourceFieldEnhancer plugins.
 */
abstract class DateTimeEnhancerBase extends ResourceFieldEnhancerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'dateTimeFormat' => \DateTime::ISO8601,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'string',
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
      'dateTimeFormat' => [
        '#type' => 'textfield',
        '#title' => $this->t('Format'),
        '#description' => $this->t('Use a valid date format.'),
        '#default_value' => $settings['dateTimeFormat'],
      ],
    ];
  }

}

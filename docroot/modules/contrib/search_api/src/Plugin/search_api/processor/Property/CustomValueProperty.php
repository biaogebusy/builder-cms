<?php

namespace Drupal\search_api\Plugin\search_api\processor\Property;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ConfigurablePropertyBase;

/**
 * Defines a "custom value" property.
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\CustomValue
 */
class CustomValueProperty extends ConfigurablePropertyBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'value' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(FieldInterface $field, array $form, FormStateInterface $form_state) {
    $configuration = $field->getConfiguration();

    $form['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field value'),
      '#description' => $this->t('Use this field to set the data to be sent to the index. You can use replacement tokens depending on the type of item being indexed.'),
      '#default_value' => $configuration['value'] ?? '',
    ];

    return $form;
  }

}

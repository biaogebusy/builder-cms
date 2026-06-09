<?php

namespace Drupal\field_widget_actions\Traits;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a trait for Alt Image Text Action Button.
 */
trait ImageAltTextActionButtonTrait {

  /**
   * {@inheritdoc}
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    parent::actionButton($form, $form_state, $context);
    $fieldName = $context['items']->getFieldDefinition()->getName();
    $multiple = $context['items']->getFieldDefinition()->getFieldStorageDefinition()->getCardinality() !== 1;
    if (!empty($context['action_id'])) {
      $widgetId = $context['action_id'];
    }
    else {
      $widgetId = $fieldName . '_field_widget_action_' . $this->getPluginId();
    }
    if (!empty($context['delta'])) {
      $widgetId .= '_' . $context['delta'];
    }
    $name = 'files[' . $fieldName . '_' . $context['delta'] . '][]';
    if (!$multiple) {
      $name = 'files[' . $fieldName . '_' . $context['delta'] . ']';
    }
    // Only show if an image is uploaded.
    $form[$widgetId]['#states'] = [
      'visible' => [
        ':input[name="' . $name . '"]' => ['filled' => TRUE],
      ],
    ];

  }

}

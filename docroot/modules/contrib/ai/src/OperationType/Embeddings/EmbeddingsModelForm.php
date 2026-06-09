<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\OperationType\GenericType\AbstractModelFormBase;

/**
 * Chat model form.
 */
class EmbeddingsModelForm extends AbstractModelFormBase {

  /**
   * {@inheritdoc}
   */
  public static function form(&$form, FormStateInterface $form_state, array $config = [], string|NULL $operation_type = NULL): array {
    parent::form($form, $form_state, $config, $operation_type);

    $form['model_data']['dimensions'] = [
      '#type' => 'number',
      '#title' => t('Dimensions'),
      '#description' => t('The dimensions this embeddings model supports.'),
      '#default_value' => $config['dimensions'] ?? 0,
      '#weight' => 25,
      '#required' => TRUE,
      '#disabled' => !empty($config['has_predefined_models']) && empty($config['has_overriden_settings']),
    ];

    return $form;
  }

}

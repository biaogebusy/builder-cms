<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\OperationType\GenericType\AbstractModelFormBase;

/**
 * Chat model form.
 */
class ChatModelForm extends AbstractModelFormBase {

  /**
   * {@inheritdoc}
   */
  public static function form(&$form, FormStateInterface $form_state, array $config = [], string|NULL $operation_type = NULL): array {
    parent::form($form, $form_state, $config, $operation_type);

    foreach (AiModelCapability::cases() as $capability) {
      if ($capability->getBaseOperationType() !== 'chat') {
        continue;
      }
      $key = $capability->value;
      $form['model_data'][$key] = [
        '#type' => 'checkbox',
        '#title' => $capability->getTitle(),
        '#description' => $capability->getDescription(),
        '#default_value' => $config[$key] ?? FALSE,
        '#weight' => 20,
        '#disabled' => !empty($config['has_predefined_models']) && empty($config['has_overriden_settings']),
      ];
    }

    $form['model_data']['max_input_tokens'] = [
      '#type' => 'number',
      '#title' => t('Max Input Tokens'),
      '#description' => t('The maximum number of tokens to input.'),
      '#default_value' => $config['max_input_tokens'] ?? 0,
      '#weight' => 25,
      '#disabled' => !empty($config['has_predefined_models']) && empty($config['has_overriden_settings']),
    ];

    $form['model_data']['max_output_tokens'] = [
      '#type' => 'number',
      '#title' => t('Max Output Tokens'),
      '#description' => t('The maximum number of tokens to output.'),
      '#default_value' => $config['max_output_tokens'] ?? 0,
      '#weight' => 25,
      '#disabled' => !empty($config['has_predefined_models']) && empty($config['has_overriden_settings']),
    ];

    return $form;
  }

}

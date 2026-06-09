<?php

namespace Drupal\ai\OperationType\GenericType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\OperationType\ModelFormInterface;
use Drupal\ai\Utility\PredefinedModels;

/**
 * Abstract model form base.
 */
abstract class AbstractModelFormBase implements ModelFormInterface {

  /**
   * {@inheritdoc}
   */
  public static function form(&$form, FormStateInterface $form_state, array $config = [], string|NULL $operation_type = NULL): array {
    if (!empty($config['has_predefined_models'])) {
      $form['override'] = [
        '#type' => 'markup',
        '#markup' => t('Because this provider is predefined, it cannot be overriden or added to by default. Please add <strong>$settings["ai_override_models"] = TRUE;</strong> to settings.php to be able to override this.'),
        '#weight' => -10,
      ];
    }

    // If its new, give suggestions about models.
    if (empty($config['has_predefined_models']) && !empty($config['new_model'])) {
      $templates = PredefinedModels::getPredefinedModelsAsOptions($operation_type);
      if (count($templates)) {
        $form['base_on'] = [
          '#type' => 'select',
          '#title' => t('Use Model Template'),
          '#description' => t('If you are going to add a common model, you can select it here, so you do not have to fill in all metadata yourself.'),
          '#options' => $templates,
          '#weight' => -5,
          '#empty_option' => t('-- Choose to fill out --'),
          '#ajax' => [
            'callback' => [static::class, 'updateModelForm'],
            'wrapper' => 'model-form',
          ],
        ];
      }
    }

    $form['model_data'] = [
      '#type' => 'fieldset',
      '#title' => t('Model Data'),
      '#weight' => -4,
      '#attributes' => [
        'id' => 'model-form',
      ],
    ];

    $form['model_data']['operation_type'] = [
      '#type' => 'hidden',
      '#value' => $operation_type,
    ];

    $form['model_data']['model_id'] = [
      '#type' => 'textfield',
      '#title' => t('Model ID'),
      '#description' => t('The model ID of the model you are using that you are using. This is for Drupal’s internals only and is not used with the provider. If no extra label is provided this will be the label.'),
      '#default_value' => $config['model_id'] ?? '',
      '#required' => TRUE,
      '#disabled' => !empty($config['has_predefined_models']) || (empty($config['new_model'])&& empty($config['has_overriden_settings'])),
      '#weight' => 0,
    ];

    $form['model_data']['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#description' => t('The label for the model. Will use the model ID if not set.'),
      '#default_value' => $config['label'] ?? '',
      '#disabled' => !empty($config['has_predefined_models']) && empty($config['has_overriden_settings']),
      '#weight' => 3,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $config['new_model'] ? t('Create Model') : t('Save Model'),
      '#weight' => 50,
      '#disabled' => !empty($config['has_predefined_models']) && empty($config['has_overriden_settings']),
      '#attributes' => [
        'class' => ['button--primary'],
      ],
    ];

    if (!$config['new_model'] && empty($config['has_predefined_models'])) {
      $form['action']['delete'] = [
        '#type' => 'submit',
        '#value' => t('Delete Model'),
        '#weight' => 51,
        '#attributes' => [
          'class' => ['button--danger'],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function updateModelForm(&$form, FormStateInterface $form_state) {
    $operation_type = $form_state->getValue('operation_type');
    $model = PredefinedModels::getPredefinedModel($operation_type, $form_state->getValue('base_on'));
    $form_state->setRebuild();
    $form['model_data']['label']['#value'] = $model['label'] ?? '';
    $form['model_data']['model_id']['#value'] = $form_state->getValue('base_on');
    // Unset all checkboxes before setting them.
    foreach ($form['model_data'] as $key => $element) {
      if (is_array($element) && $element['#type'] === 'checkbox') {
        $form['model_data'][$key]['#checked'] = FALSE;
      }
    }
    foreach ($model['capabilities'] as $capability) {
      if (isset($form['model_data'][$capability])) {
        $form['model_data'][$capability]['#checked'] = TRUE;
      }
    }
    foreach ($model['metadata'] as $key => $value) {
      if (isset($form['model_data'][$key])) {
        $form['model_data'][$key]['#value'] = $value;
      }
    }
    return $form['model_data'];
  }

}

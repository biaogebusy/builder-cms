<?php

namespace Drupal\xinshi_editor\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * {@inheritdoc}
 */
class PanelizerSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xinshi_editor_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'xinshi_editor_panelizer.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->get('xinshi_editor.panelizer_settings');

    $blockManager = \Drupal::service('plugin.manager.block');
    $definitions = $blockManager->getDefinitionsForContexts([]);
    $keys = [];
    foreach ($definitions as $definition) {
      $keys [] = $definition['provider'];
    }
    $options = [];
    $keys = array_unique($keys);
    foreach ($keys as $key) {
      $options[$key] = $key;
    }

    if (\Drupal::moduleHandler()->moduleExists('entity_print')) {
      $options['entity_print'] = 'Entity Print';
    }
    $form['disable_blocks'] = [
      '#type' => 'checkboxes',
      '#title' => t('Disable blocks for panelizer'),
      '#options' => $options,
      '#default_value' => $config->get('disable_blocks') ?: [],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => [
        'class' => ['clearfix'],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $config = $this->configFactory()->getEditable('xinshi_editor.panelizer_settings');
    foreach (['disable_blocks'] as $key) {
      $config->set($key, array_filter(array_values($values[$key])));
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }
}

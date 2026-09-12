<?php

namespace Drupal\footable\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for FooTable.
 */
class FooTableConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'footable_form_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['footable.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('footable.settings');

    $form['config'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-plugin',
    ];

    $form['plugin'] = [
      '#type' => 'details',
      '#title' => $this->t('Plugin'),
      '#group' => 'config',
    ];

    $form['plugin']['plugin_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Type'),
      '#options' => [
        'standalone' => $this->t('Standalone'),
        'bootstrap' => $this->t('Bootstrap'),
      ],
      '#default_value' => $config->get('plugin_type'),
    ];

    $form['plugin']['plugin_compression'] = [
      '#type' => 'radios',
      '#title' => $this->t('Compression level'),
      '#options' => [
        'minified' => $this->t('Production (minified)'),
        'source' => $this->t('Development (uncompressed)'),
      ],
      '#default_value' => $config->get('plugin_compression'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('footable.settings');
    $config->set('plugin_type', $form_state->getValue('plugin_type'));
    $config->set('plugin_compression', $form_state->getValue('plugin_compression'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}

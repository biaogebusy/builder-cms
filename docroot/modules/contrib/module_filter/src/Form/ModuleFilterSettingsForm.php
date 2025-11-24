<?php

namespace Drupal\module_filter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings form for Module Filter.
 */
class ModuleFilterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'module_filter_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('module_filter.settings');
    $form = parent::buildForm($form, $form_state);

    $form['modules'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Extend'),
      '#description' => $this->t('These are settings pertaining to the Extend pages of the site.'),
      '#collapsible' => FALSE,
    ];
    $form['modules']['tabs'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enhance the Extend page with tabs'),
      '#description' => $this->t('Provides many enhancements to the Extend page including the use of tabs for packages.'),
      '#default_value' => $config->get('tabs'),
    ];
    $form['modules']['descriptions_show'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always show the description details'),
      '#description' => $this->t('By default descriptions are hidden, this will always show them.'),
      '#default_value' => $config->get('descriptions_show'),
    ];

    $form['modules']['path'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show module path in modules list'),
      '#description' => $this->t('Defines if the relative path of each module will be display in its row.'),
      '#default_value' => $config->get('path'),
    ];

    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filters'),
      '#description' => $this->t('Enable filters for use around the administration pages.'),
      '#collapsible' => FALSE,
    ];

    $form['filters']['permissions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Permissions'),
      '#description' => $this->t('Enable the filter on the permissions page.'),
      '#default_value' => $config->get('enabled_filters.permissions'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $this->config('module_filter.settings')
      ->set('tabs', $values['tabs'])
      ->set('enabled_filters.permissions', $values['permissions'])
      ->set('path', $values['path'])
      ->set('descriptions_show', $values['descriptions_show'])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['module_filter.settings'];
  }

}

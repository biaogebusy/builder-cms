<?php

namespace Drupal\charts\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Advanced tab on the Charts configuration form.
 */
class ChartsConfigAdvancedForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['charts.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'charts_settings_advanced_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('charts.settings');
    $form['advanced'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['advanced']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Charts Debug'),
      '#description' => $this->t("Show the JSON generated for the chart in a code block below the chart."),
      '#default_value' => $config->get('advanced.debug'),
    ];
    $form['advanced']['requirements'] = [
      '#type' => 'details',
      '#title' => $this->t('Requirement settings'),
      '#description' => $this->t('The below requirements are checked by the <a href=":href">Status report</a>.', [':href' => Url::fromRoute('system.status')->toString()]),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['advanced']['requirements']['cdn'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use a CDN by default for external libraries'),
      '#description' => $this->t('If checked, the module will use a CDN unless a local copy of the library is present. If unchecked, all warnings about missing libraries will be disabled.') . '<br/><br/>' . $this->t('Relying on a CDN (content delivery network) for external libraries can cause unexpected issues with Ajax and BigPipe support. For more information see: <a href=":href">Issue #1988968</a>', [':href' => 'https://www.drupal.org/project/drupal/issues/1988968']),
      '#return_value' => TRUE,
      '#default_value' => $config->get('advanced.requirements.cdn'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $advanced = $form_state->getValue('advanced');

    $config = $this->config('charts.settings');
    $config->set('advanced', $advanced)->save();

    parent::submitForm($form, $form_state);
  }

}

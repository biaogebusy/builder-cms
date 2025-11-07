<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Entity share diff on this site.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'entity_share_diff.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_share_diff_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['#tree'] = TRUE;

    $form['context'] = [
      '#type' => 'details',
      '#title' => $this->t('Output'),
      '#description' => $this->t('Configure the look and feel of the diff.<br />It is preferable to have a bigger number of context lines in order to better display the nested differences.'),
      '#open' => TRUE,
    ];
    $form['context']['lines_leading'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 1000,
      '#size' => 5,
      '#title' => $this->t('Leading lines'),
      '#description' => $this->t('The number of lines of leading context before each difference.'),
      '#default_value' => $config->get('context.lines_leading'),
    ];
    $form['context']['lines_trailing'] = [
      '#type' => 'number',
      '#min' => 1,
      '#max' => 1000,
      '#size' => 5,
      '#title' => $this->t('Trailing lines'),
      '#description' => $this->t('The number of lines of trailing context after each difference.'),
      '#default_value' => $config->get('context.lines_trailing'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->configFactory->getEditable(static::SETTINGS)
      ->set('context', $form_state->getValue('context'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

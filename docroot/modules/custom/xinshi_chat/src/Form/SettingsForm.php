<?php

declare(strict_types=1);

namespace Drupal\xinshi_chat\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/** Controls feature availability without removing stored conversations. */
final class SettingsForm extends ConfigFormBase {

  /** {@inheritdoc} */
  public function getFormId(): string {
    return 'xinshi_chat_settings';
  }

  /** {@inheritdoc} */
  protected function getEditableConfigNames(): array {
    return ['xinshi_chat.settings'];
  }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['enabled'] = ['#type' => 'checkbox', '#title' => $this->t('Enable Chat'),
      '#default_value' => $this->config('xinshi_chat.settings')->get('enabled'),
      '#description' => $this->t('Disabling Chat blocks conversation API requests and retains stored data.')];
    return parent::buildForm($form, $form_state);
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('xinshi_chat.settings')->set('enabled', (bool) $form_state->getValue('enabled'))->save();
    parent::submitForm($form, $form_state);
  }

}

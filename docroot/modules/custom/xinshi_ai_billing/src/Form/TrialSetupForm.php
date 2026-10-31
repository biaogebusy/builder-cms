<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing\Form;

use Drupal\Core\Form\{FormBase, FormStateInterface};
use Drupal\xinshi_ai_billing\TrialSetup;
use Drupal\xinshi_credits\CreditException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** A compact, reviewed setup for free trial credits. */
final class TrialSetupForm extends FormBase {

  protected TrialSetup $setup;

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    $form = new static(); $form->setup = $container->get('xinshi_ai_billing.trial'); return $form;
  }

  /** {@inheritdoc} */
  public function getFormId(): string { return 'xinshi_ai_credit_trial'; }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#cache']['max-age'] = 0;
    $form['intro'] = ['#markup' => '<p>' . $this->t('Keep credit balances and deductions. Set the spending cap at your provider. This setup does not accept payments or enforce a currency budget.') . '</p>'];
    $labels = ['welcome' => $this->t('First visit reward (points)'), 'daily' => $this->t('Daily reward (points)'),
      'chat' => $this->t('Successful UI or text generation (points per operation)'), 'image' => $this->t('Delivered image (points per image)'),
      'chat_model' => $this->t('Trial chat model'), 'image_model' => $this->t('Trial image model'),
      'role' => $this->t('Eligible role'), 'timezone' => $this->t('Reward timezone'), 'expires_at' => $this->t('Credit expiry (UTC)')];
    $form['actions'] = ['#type' => 'actions'];
    if ($draft = $form_state->get('trial_draft')) {
      $rows = [];
      foreach ($draft['input'] as $key => $value) $rows[] = [$labels[$key], $key === 'expires_at'
        ? ($value === NULL ? $this->t('No expiry') : gmdate('Y-m-d H:i', $value)) : $value];
      $form['review'] = ['#type' => 'table', '#header' => [$this->t('Setting'), $this->t('Value')], '#rows' => $rows];
      $form['notice'] = ['#markup' => '<p>' . $this->t('Saving enables rewards and credit spending for this role. Existing balances and accepted tasks keep their original rules. Changing the role does not revoke earlier permissions.') . '</p>'];
      $form['actions']['apply'] = ['#type' => 'submit', '#name' => 'apply', '#value' => $this->t('Save and enable trial credits')];
      $form['actions']['back'] = ['#type' => 'submit', '#name' => 'back', '#value' => $this->t('Back to edit'), '#limit_validation_errors' => []];
      return $form;
    }
    $saved = $this->config('xinshi_ai_billing.trial')->getRawData();
    $enabled = $this->config('xinshi_credits.settings')->get('grants_enabled')
      && $this->config('xinshi_credits.settings')->get('spending_enabled')
      && $this->config('xinshi_rewards.settings')->get('welcome_enabled')
      && $this->config('xinshi_rewards.settings')->get('daily_enabled')
      && $this->config('xinshi_ai_billing.settings')->get('enabled');
    $form['status'] = ['#type' => 'item', '#title' => $this->t('Drupal credit switches'),
      '#markup' => $enabled ? $this->t('All enabled')
        : $this->t('Not all enabled. Check the credit, reward and AI credit billing settings.')];
    $defaults = ['welcome' => '15', 'daily' => '1', 'chat' => '1', 'image' => '1'];
    foreach ($defaults as $key => $value) $form[$key] = ['#type' => 'number', '#title' => $labels[$key], '#required' => TRUE,
      '#min' => 1, '#max' => 1000000, '#step' => 1, '#default_value' => $saved[$key] ?? $value];
    $form['welcome']['#description'] = $this->t('At least 5 UI generations and 10 images at the prices below. Daily rewards are separate.');
    foreach (['chat', 'image'] as $kind) {
      $options = [];
      foreach ($this->setup->models($kind) as $id => $model) $options[$id] = ($model['label'] ?? $id) . ' (' . $id . ')';
      $form[$kind . '_model'] = ['#type' => 'select', '#title' => $labels[$kind . '_model'], '#options' => $options,
        '#empty_option' => $this->t('- Select -'), '#required' => TRUE,
        '#default_value' => $saved[$kind . '_model'] ?? $this->config('xinshi_ai.models')->get('defaults.' . $kind)];
    }
    $form['image_model']['#description'] = $this->t('Use the selected models for the trial. Supported: ordinary UI/text conversations and text-to-image, with registered image sizes and counts. Other workflows require separate profiles.');
    $form['advanced'] = ['#type' => 'details', '#title' => $this->t('Participation and expiry'), '#open' => FALSE];
    $options = [];
    foreach (\Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple() as $role) {
      if ($role->id() !== 'anonymous' && !$role->isAdmin()) $options[$role->id()] = $role->label();
    }
    $form['advanced']['role'] = ['#type' => 'select', '#title' => $labels['role'], '#options' => $options, '#required' => TRUE,
      '#default_value' => $saved['role'] ?? 'authenticated'];
    $form['advanced']['timezone'] = ['#type' => 'select', '#title' => $labels['timezone'], '#required' => TRUE,
      '#options' => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()),
      '#default_value' => $saved['timezone'] ?? ($this->config('xinshi_rewards.settings')->get('business_timezone') ?: $this->config('system.date')->get('timezone.default') ?: 'UTC')];
    $form['advanced']['expires_at'] = ['#type' => 'textfield', '#title' => $labels['expires_at'],
      '#default_value' => empty($saved['expires_at']) ? '' : gmdate('Y-m-d H:i', (int) $saved['expires_at']),
      '#description' => $this->t('Leave empty for no expiry. Otherwise use YYYY-MM-DD HH:MM. Existing credits are unchanged.')];
    $form['provider_cap'] = ['#type' => 'checkbox', '#required' => TRUE,
      '#default_value' => !empty($saved['version']),
      '#title' => $this->t('I have set the spending cap at the provider and enabled enforced metering and Node credit billing.'),
      '#description' => $this->t('This confirms the prerequisites. Saving retains the confirmation. Clearing this checkbox does not disable credits.')];
    $form['actions']['review'] = ['#type' => 'submit', '#name' => 'review', '#value' => $this->t('Review trial settings')];
    return $form;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->get('trial_draft')) return;
    try {
      $input = [];
      foreach (['welcome', 'daily', 'chat', 'image', 'chat_model', 'image_model', 'role', 'timezone'] as $key) $input[$key] = $form_state->getValue($key);
      $text = trim((string) $form_state->getValue('expires_at'));
      $date = $text === '' ? NULL : \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $text, new \DateTimeZone('UTC'));
      if ($text !== '' && (!$date || $date->format('Y-m-d H:i') !== $text)) throw new CreditException('invalid_expiry');
      $input['expires_at'] = $date?->getTimestamp();
      $draft = $this->setup->prepare($input, 'trial-' . \Drupal::service('uuid')->generate(), time());
      $form_state->set('trial_validated', $draft);
    }
    catch (CreditException $error) { $form_state->setErrorByName('', $this->error($error)); }
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $action = $form_state->getTriggeringElement()['#name'];
    if ($action === 'back') $form_state->set('trial_draft', NULL);
    elseif ($action === 'review') $form_state->set('trial_draft', $form_state->get('trial_validated'));
    else {
      try {
        $this->setup->save($form_state->get('trial_draft'));
        $this->messenger()->addStatus($this->t('Trial credits are enabled. Verify a reward and a successful deduction with a trial account.'));
        $form_state->setRedirect('xinshi_ai_billing.trial'); return;
      }
      catch (CreditException $error) { $this->messenger()->addError($this->error($error)); }
      catch (\Throwable) { $this->messenger()->addError($this->t('The setup could not be saved. Retry this reviewed version.')); }
    }
    $form_state->setRebuild();
  }

  private function error(CreditException $error): \Drupal\Core\StringTranslation\TranslatableMarkup {
    return match ($error->reason) {
      'trial_experience_too_small' => $this->t('The first reward must cover 5 UI generations and 10 images.'),
      'image_metering_unavailable', 'trial_site_mismatch' => $this->t('Configure enforced image metering and the matching site identifier before enabling trial credits.'),
      'trial_existing_budgeted_credits', 'trial_existing_budgeted_prices', 'publication_period_overlap' => $this->t('This site already has budgeted credit records or policies. Keep the existing mode and review the transition separately.'),
      'trial_configuration_changed' => $this->t('The configuration changed. Return to edit and review the current settings.'),
      'trial_models_unavailable' => $this->t('Configure enabled chat, classifier, critic and image models in the AI model catalog.'),
      'trial_rewards_required' => $this->t('Enable the Xinshi Rewards module before using this setup.'),
      default => $this->t('Check the values, expiry, timezone and administrator permissions. Error: @reason', ['@reason' => $error->reason]),
    };
  }

}

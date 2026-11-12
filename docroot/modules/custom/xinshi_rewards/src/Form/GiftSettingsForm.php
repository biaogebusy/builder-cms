<?php

declare(strict_types=1);

namespace Drupal\xinshi_rewards\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\{FormBase, FormStateInterface};
use Drupal\xinshi_credits\{CreditException, CreditValue};
use Drupal\xinshi_rewards\GiftSetup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Review explicit administrator limits before publishing a new allocation. */
final class GiftSettingsForm extends FormBase {

  public function __construct(protected GiftSetup $setup, protected UuidInterface $uuid) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_rewards.gift_setup'), $container->get('uuid'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string { return 'xinshi_rewards_gift_settings'; }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#cache']['max-age'] = 0;
    $form['#attributes']['class'][] = 'xinshi-rewards-form';
    $form['#attached']['library'][] = 'xinshi_rewards/gift';
    $form['intro'] = ['#markup' => '<p>' . $this->t('Set each limit to a specific amount or unlimited. The total is shared by all administrators using this allocation. Saving a rule does not give credits to a user.') . '</p>'];
    if ($draft = $form_state->get('gift_draft')) {
      $form['review'] = ['#type' => 'table', '#caption' => $this->t('Review administrator gift settings'),
        '#header' => [$this->t('Setting'), $this->t('Value')], '#rows' => $this->summary($draft['input'])];
      $form['notice'] = ['#markup' => '<p>' . $this->t('Publishing enables a new allocation with its own issued total. Previous gifts and balances remain unchanged. Use pause or resume to keep the current allocation and its issued total.') . '</p>'];
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['publish'] = ['#type' => 'submit', '#name' => 'publish', '#value' => $this->t('Publish and enable administrator gifts')];
      $form['actions']['back'] = ['#type' => 'submit', '#name' => 'back', '#value' => $this->t('Back to edit'), '#limit_validation_errors' => []];
      return $form;
    }
    try { $current = $this->setup->current(); }
    catch (CreditException $error) {
      $form['unavailable'] = ['#plain_text' => $this->message($error)];
      return $form;
    }
    $pool = $current['pool'];
    $policy = $pool ? json_decode($pool['grant_policy_json'], TRUE, 16, JSON_THROW_ON_ERROR) : NULL;
    $form_state->set('current_pool_id', $pool['id'] ?? '');
    $form['status'] = ['#type' => 'item', '#title' => $this->t('Administrator gifts'),
      '#plain_text' => !$pool ? $this->t('Not configured') : ($current['enabled'] ? $this->t('Enabled') : $this->t('Paused'))];
    if (!$this->config('xinshi_credits.settings')->get('grants_enabled')) {
      $form['grants_paused'] = ['#markup' => '<p>' . $this->t('Site credit grants are paused. An administrator must enable site grants before users can receive gifts.') . '</p>'];
    }
    if ($pool) {
      $form['current'] = ['#type' => 'details', '#title' => $this->t('Current allocation'), '#open' => TRUE];
      $form['current']['summary'] = ['#type' => 'table', '#header' => [$this->t('Setting'), $this->t('Value')],
        '#rows' => $this->summary($pool + $policy + ['scope' => json_decode($pool['scope_json'], TRUE)])];
      $form['current']['toggle'] = ['#type' => 'submit', '#name' => $current['enabled'] ? 'pause' : 'resume',
        '#value' => $current['enabled'] ? $this->t('Pause administrator gifts') : $this->t('Resume administrator gifts'),
        '#limit_validation_errors' => []];
    }
    $form['new'] = ['#type' => 'fieldset', '#title' => $this->t('New administrator gift allocation')];
    foreach (['grant_limit' => $this->t('Per-gift limit'), 'cap' => $this->t('Total allocation limit')] as $key => $label) {
      $value = $key === 'cap' ? ($pool['cap_atoms'] ?? NULL) : ($policy['grant_limit_atoms'] ?? NULL);
      $form['new'][$key . '_mode'] = ['#type' => 'select', '#title' => $label, '#required' => TRUE,
        '#empty_option' => $this->t('- Select -'), '#options' => ['limited' => $this->t('Specific amount'), 'unlimited' => $this->t('Unlimited')],
        '#default_value' => $pool ? ($value === NULL ? 'unlimited' : 'limited') : ''];
      $form['new'][$key] = ['#type' => 'textfield', '#title' => $this->t('@limit (credits)', ['@limit' => $label]),
        '#default_value' => $value === NULL ? '' : CreditValue::display((string) $value, 3),
        '#description' => $this->t('For a specific amount, enter a positive number with at most 3 decimal places.'),
        '#states' => ['visible' => [':input[name="' . $key . '_mode"]' => ['value' => 'limited']],
          'required' => [':input[name="' . $key . '_mode"]' => ['value' => 'limited']]]];
    }
    $source = $current['default_policy'];
    $form['new']['advanced'] = ['#type' => 'details', '#title' => $this->t('Scope and dates'), '#open' => FALSE];
    $form['new']['advanced']['scope'] = ['#type' => 'textfield', '#title' => $this->t('Scope'), '#required' => TRUE,
      '#default_value' => $source ? implode(', ', json_decode($source['scope_json'], TRUE)) : '',
      '#description' => $this->t('Use comma-separated service categories from the site credit rules.')];
    $form['new']['advanced']['period_end'] = ['#type' => 'textfield', '#title' => $this->t('Issuance ends (UTC)'), '#required' => TRUE,
      '#default_value' => $source ? gmdate('Y-m-d H:i', (int) $source['period_end']) : '',
      '#description' => $this->t('Use YYYY-MM-DD HH:MM. This stops new gifts without expiring credits already given.')];
    $form['new']['advanced']['expires_at'] = ['#type' => 'textfield', '#title' => $this->t('Latest grant expiry (UTC)'),
      '#default_value' => ($policy['expires_at'] ?? NULL) === NULL ? '' : gmdate('Y-m-d H:i', $policy['expires_at']),
      '#description' => $this->t('Leave empty for no expiry. Otherwise use YYYY-MM-DD HH:MM.')];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['review'] = ['#type' => 'submit', '#name' => 'review', '#value' => $this->t('Review administrator gift settings')];
    return $form;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getTriggeringElement()['#name'] ?? '') !== 'review') return;
    try {
      $input = [];
      foreach (['cap', 'grant_limit'] as $key) {
        $mode = $form_state->getValue($key . '_mode');
        if (!in_array($mode, ['limited', 'unlimited'], TRUE)) throw new CreditException('invalid_amount');
        $input[$key . '_atoms'] = $mode === 'unlimited' ? NULL : CreditValue::decimal($form_state->getValue($key), 3);
      }
      $input['scope'] = array_map('trim', explode(',', (string) $form_state->getValue('scope')));
      $input['period_end'] = $this->date((string) $form_state->getValue('period_end'));
      $input['expires_at'] = $form_state->getValue('expires_at') === '' ? NULL : $this->date((string) $form_state->getValue('expires_at'));
      $form_state->set('gift_validated', $this->setup->prepare($input, 'gift-' . $this->uuid->generate(), time()));
    }
    catch (CreditException $error) { $form_state->setErrorByName('', $this->message($error)); }
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $action = $form_state->getTriggeringElement()['#name'];
    // Drupal can select the first button when a completed cached form is posted again.
    if (!array_key_exists($action, $form_state->getUserInput())) {
      $form_state->setRebuild();
      return;
    }
    if ($action === 'review') $form_state->set('gift_draft', $form_state->get('gift_validated'));
    elseif ($action === 'back') $form_state->set('gift_draft', NULL);
    else {
      try {
        if ($action === 'publish') $this->setup->save($form_state->get('gift_draft'));
        else $this->setup->setEnabled($action === 'resume', $form_state->get('current_pool_id'));
        $this->messenger()->addStatus($this->t('Administrator gift settings saved. No credits were issued.'));
        $form_state->setRedirect('xinshi_rewards.gift_settings');
        return;
      }
      catch (CreditException $error) { $this->messenger()->addError($this->message($error)); }
      catch (\Throwable) { $this->messenger()->addError($this->t('The settings could not be saved. Retry the same reviewed configuration.')); }
    }
    $form_state->setRebuild();
  }

  private function date(string $value): int {
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, new \DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d H:i') !== $value) throw new CreditException('invalid_period');
    return $date->getTimestamp();
  }

  private function summary(array $value): array {
    $rows = [];
    foreach (['grant_limit_atoms' => $this->t('Per-gift limit'), 'cap_atoms' => $this->t('Total allocation limit')] as $key => $label) {
      $rows[] = [$label, $value[$key] === NULL ? $this->t('Unlimited') : $this->t('@amount credits', ['@amount' => CreditValue::display((string) $value[$key], 3)])];
    }
    if (isset($value['issued_atoms'])) $rows[] = [$this->t('Credits already given'), CreditValue::display((string) $value['issued_atoms'], 3)];
    $rows[] = [$this->t('Scope'), implode(', ', $value['scope'])];
    $rows[] = [$this->t('Issuance ends (UTC)'), gmdate('Y-m-d H:i', (int) $value['period_end'])];
    $rows[] = [$this->t('Latest grant expiry (UTC)'), $value['expires_at'] === NULL ? $this->t('No expiry') : gmdate('Y-m-d H:i', $value['expires_at'])];
    return array_map(static fn(array $row): array => array_map(static fn(mixed $cell): array => ['data' => ['#plain_text' => $cell]], $row), $rows);
  }

  private function message(CreditException $error): mixed {
    return match ($error->reason) {
      'invalid_amount', 'invalid_grant_policy' => $this->t('Select unlimited or enter a positive credit amount for each limit.'),
      'invalid_period' => $this->t('Check the UTC dates. Issuance must end in the future, and a specified expiry must also be in the future.'),
      'gift_configuration_changed' => $this->t('Administrator gift settings changed. Reload this page and review the current rule.'),
      'gift_setup_requires_points', 'publication_period_overlap' => $this->t('This site has budgeted credit records or rules. Configure administrator gifts through the existing budget publication process.'),
      default => $this->t('Check the gift settings and administrator permissions. Error: @reason', ['@reason' => $error->reason]),
    };
  }

}

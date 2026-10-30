<?php

declare(strict_types=1);

namespace Drupal\xinshi_rewards\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\xinshi_credits\CreditException;
use Drupal\xinshi_credits\CreditQuery;
use Drupal\xinshi_credits\CreditValue;
use Drupal\xinshi_rewards\Rewards;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Review one explicit gift before committing it with a persistent request key. */
final class GiftForm extends FormBase {

  public function __construct(protected Rewards $rewards, protected CreditQuery $query,
    protected UuidInterface $uuid, protected EntityTypeManagerInterface $entities) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_rewards.rewards'), $container->get('xinshi_credits.query'),
      $container->get('uuid'), $container->get('entity_type.manager'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string { return 'xinshi_rewards_gift'; }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $user = NULL): array {
    $form['#cache']['max-age'] = 0;
    $form['#attached']['library'][] = 'xinshi_rewards/gift';
    if (!$form_state->get('request_key')) $form_state->set('request_key', $this->uuid->generate());
    $form_state->set('target', $user);
    try {
      $balance = $this->query->account($user);
      $policy = $form_state->get('snapshot')
        ? $form_state->get('review_policy') : $this->rewards->giftPolicy($user);
    }
    catch (CreditException $error) {
      $form['unavailable'] = ['#markup' => $this->t('Credit gift unavailable: @reason', ['@reason' => $error->reason])];
      return $form;
    }
    $form_state->set('policy', $policy);
    $subject = $this->entities->getStorage('user')->load($user);
    $form['target'] = ['#markup' => $this->t('Recipient: @name (user ID @id). Current account state: @state.',
      ['@name' => $subject->getAccountName(), '@id' => $user, '@state' => $balance['status']])];
    if (isset($balance['available_atoms'])) {
      $form['balance'] = ['#type' => 'item', '#title' => $this->t('Available credits'),
        '#plain_text' => CreditValue::display($balance['available_atoms'], 3)];
    }
    $form['limits'] = ['#type' => 'item', '#title' => $this->t('Published authorization'),
      '#markup' => $this->t('Pool @pool. Per-gift maximum: @limit credits. Remaining pool: @remaining credits. Scope: @scope. Latest expiry (UTC): @expiry.',
        ['@pool' => $policy['budget_pool_id'], '@limit' => CreditValue::display($policy['grant_limit_atoms'], 3),
          '@remaining' => CreditValue::display($policy['remaining_atoms'], 3), '@scope' => implode(', ', $policy['scope']),
          '@expiry' => gmdate('Y-m-d H:i:s', $policy['expires_at'])])];
    if ($result = $form_state->get('result')) {
      $form['result'] = ['#type' => 'item', '#title' => $this->t('Gift committed'),
        '#markup' => $this->t('Transaction: @id. The recipient can read the transaction and notification.',
          ['@id' => $result['receipt']['journal_id']])];
      return $form;
    }
    if ($snapshot = $form_state->get('snapshot')) {
      $rows = [];
      $labels = ['beneficiary_user_id' => $this->t('Recipient user ID'), 'amount_atoms' => $this->t('Credits to give'),
        'public_reason' => $this->t('Reason shown to the recipient'), 'internal_note' => $this->t('Internal audit note'),
        'budget_pool_id' => $this->t('Published pool'), 'scope' => $this->t('Scope'), 'expires_at' => $this->t('Expiry (UTC)')];
      foreach ($labels as $field => $label) {
        $value = $snapshot[$field];
        if ($field === 'amount_atoms') $value = CreditValue::display($value, 3) . ' credits';
        if ($field === 'expires_at') $value = gmdate('Y-m-d H:i:s', $value) . ' UTC';
        if (is_array($value)) $value = implode(', ', $value);
        $rows[] = [['data' => ['#plain_text' => $label]], ['data' => ['#plain_text' => (string) $value]]];
      }
      $form['review'] = ['#type' => 'table', '#caption' => $this->t('Review gift'),
        '#header' => [$this->t('Field'), $this->t('Confirmed value')], '#rows' => $rows];
      $form['confirm'] = ['#type' => 'submit', '#value' => $this->t('Confirm gift')];
      $form['edit'] = ['#type' => 'submit', '#value' => $this->t('Edit gift'),
        '#submit' => ['::edit'], '#limit_validation_errors' => []];
      return $form;
    }
    $draft = $form_state->get('validated_input');
    $form['amount'] = ['#type' => 'textfield', '#title' => $this->t('Credits to give'), '#required' => TRUE,
      '#default_value' => $draft ? CreditValue::display($draft['amount_atoms'], 3) : '',
      '#description' => $this->t('Enter a positive amount with at most 3 decimal places. No amount is selected by default.')];
    $form['public_reason'] = ['#type' => 'textfield', '#title' => $this->t('Reason shown to the recipient'), '#required' => TRUE,
      '#maxlength' => 255, '#default_value' => $draft['public_reason'] ?? ''];
    $form['internal_note'] = ['#type' => 'textfield', '#title' => $this->t('Internal audit note'),
      '#maxlength' => 1000, '#default_value' => $draft['internal_note'] ?? ''];
    $form['expiry'] = ['#type' => 'textfield', '#title' => $this->t('Expiry (UTC)'), '#required' => TRUE,
      '#default_value' => $draft ? gmdate('Y-m-d H:i:s', $draft['expires_at']) : '',
      '#description' => $this->t('Use YYYY-MM-DD HH:MM:SS. The expiry must not exceed the published latest expiry.')];
    $form['review_action'] = ['#type' => 'submit', '#value' => $this->t('Review gift')];
    return $form;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->get('snapshot')) return;
    try {
      $policy = $form_state->get('policy');
      if (!$policy) throw new CreditException('rewards_not_configured', 503);
      $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $form_state->getValue('expiry'), new \DateTimeZone('UTC'));
      if (!$date || $date->format('Y-m-d H:i:s') !== $form_state->getValue('expiry')) throw new CreditException('invalid_expiry');
      $input = $this->rewards->normalizeGift(['beneficiary_user_id' => $form_state->get('target'),
        'amount_atoms' => CreditValue::decimal($form_state->getValue('amount'), 3),
        'budget_pool_id' => $policy['budget_pool_id'], 'scope' => $policy['scope'],
        'expires_at' => $date->getTimestamp(), 'public_reason' => $form_state->getValue('public_reason'),
        'internal_note' => $form_state->getValue('internal_note') ?? '']);
      if ((int) $input['amount_atoms'] > (int) $policy['grant_limit_atoms']
        || $input['expires_at'] > $policy['expires_at'] || $input['expires_at'] <= time()) {
        throw new CreditException('invalid_grant_policy');
      }
      $form_state->set('validated_input', $input);
    }
    catch (CreditException $error) { $form_state->setErrorByName('', $this->t('Gift not accepted: @reason', ['@reason' => $error->reason])); }
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$form_state->get('snapshot')) {
      $form_state->set('review_policy', $form_state->get('policy'));
      $form_state->set('snapshot', $form_state->get('validated_input'))->setRebuild();
      return;
    }
    try {
      $form_state->set('result', $this->rewards->gift($form_state->get('snapshot'), $form_state->get('request_key')));
      $this->messenger()->addStatus($this->t('Credits granted.'));
    }
    catch (CreditException $error) { $this->messenger()->addError($this->t('Gift not accepted: @reason', ['@reason' => $error->reason])); }
    catch (\Throwable $error) {
      $this->getLogger('xinshi_rewards')->error('Gift failed with @class.', ['@class' => $error::class]);
      $this->messenger()->addError($this->t('Gift status could not be confirmed. Retry this review with the same request.'));
    }
    $form_state->setRebuild();
  }

  /** Return to editing without silently replacing the request identity. */
  public function edit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('snapshot', NULL)->setRebuild();
  }

}

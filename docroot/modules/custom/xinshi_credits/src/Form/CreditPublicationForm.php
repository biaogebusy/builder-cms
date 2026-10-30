<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits\Form;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\xinshi_credits\CreditException;
use Drupal\xinshi_credits\CreditPublication;
use Drupal\xinshi_credits\CreditValue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Review and publish immutable limits without enabling or issuing credits. */
final class CreditPublicationForm extends FormBase {

  public function __construct(protected CreditPublication $publication, protected UuidInterface $uuid) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_credits.publication'), $container->get('uuid'));
  }

  /** {@inheritdoc} */
  public function getFormId(): string { return 'xinshi_credit_publication'; }

  /** {@inheritdoc} */
  public function buildForm(array $form, FormStateInterface $form_state, string $type = 'budget'): array {
    $form_state->set('publication_type', $type);
    $form['#cache']['max-age'] = 0;
    $form['#attributes']['class'][] = 'credit-publication-form';
    $form['#attached']['library'][] = 'xinshi_credits/publication';
    $form['intro'] = ['#markup' => '<p>' . $this->t('Publishing sets limits only. It does not issue credits or enable grants or spending. Published versions cannot be edited. All dates are UTC.') . '</p>'];
    $form['navigation'] = ['#theme' => 'item_list', '#attributes' => ['class' => ['credit-publication-navigation']], '#items' => [
      Link::createFromRoute($this->t('Cost budgets'), 'xinshi_credits.budgets'),
      Link::createFromRoute($this->t('Reward allocations'), 'xinshi_credits.pools'),
    ]];
    try { $catalog = $this->publication->catalog($type); }
    catch (CreditException $error) {
      $form['unavailable'] = ['#plain_text' => $this->message($error)];
      return $form;
    }
    if ($review = $form_state->get('publication_review')) {
      $form['review'] = ['#type' => 'table', '#caption' => $this->t('Review publication'),
        '#header' => [$this->t('Setting'), $this->t('Value')], '#rows' => $form_state->get('publication_summary')];
      $form['notice'] = ['#markup' => '<p>' . $this->t('Confirm the evidence, currency, period and limits. Publication checks the current budget again. A cost reference records your evidence; it does not verify supplier data.') . '</p>'];
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['publish'] = ['#type' => 'submit', '#value' => $this->t('Publish immutable version'), '#name' => 'publish'];
      $form['actions']['back'] = ['#type' => 'submit', '#value' => $this->t('Back to edit'), '#name' => 'back', '#limit_validation_errors' => [], '#submit' => ['::submitForm']];
      return $form;
    }
    $draft = $form_state->get('publication_draft') ?? [];
    $form_state->set('publication_ref', $form_state->get('publication_ref') ?? $this->uuid->generate());
    $form['publication_ref'] = $this->field($this->t('Publication reference'), $draft['publication_ref'] ?? $form_state->get('publication_ref'));
    $form['source_ref'] = $this->field($this->t('Budget or rule evidence reference'), $draft['source_ref'] ?? '');
    foreach (['period_start' => $this->t('Period start (UTC)'), 'period_end' => $this->t('Period end, exclusive (UTC)')] as $key => $label) {
      $form[$key] = $this->field($label, $draft[$key] ?? '', $this->t('Use YYYY-MM-DD HH:MM. The end is outside the period.'));
    }
    if ($type === 'budget') {
      $form['category'] = $this->field($this->t('Service category identifier'), $draft['category'] ?? '');
      $form['currency'] = $this->field($this->t('Currency code'), $draft['currency'] ?? '', $this->t('Three uppercase letters. Different currencies are not added together.'));
      $form['cap'] = $this->field($this->t('Cost budget in currency units'), $draft['cap'] ?? '', $this->t('Up to six decimal places. Enter an approved amount.'));
      $form['headroom'] = $this->field($this->t('Execution and failure cost reserve'), $draft['headroom'] ?? '', $this->t('Part of this budget, unavailable for reward allocation plans. Enter zero only when supported by your evidence.'));
    }
    else {
      $form['rule_id'] = $this->field($this->t('Rule identifier'), $draft['rule_id'] ?? '');
      $form['rule_version'] = $this->field($this->t('Rule version'), $draft['rule_version'] ?? '');
      $form['mode'] = ['#type' => 'select', '#title' => $this->t('Grant amount policy'), '#required' => TRUE,
        '#empty_option' => $this->t('- Select -'), '#default_value' => $draft['mode'] ?? '',
        '#options' => ['fixed' => $this->t('Fixed amount per grant'), 'variable' => $this->t('Variable amount with a per-grant limit')]];
      $form['source_kind'] = ['#type' => 'select', '#title' => $this->t('Grant source'), '#required' => TRUE,
        '#empty_option' => $this->t('- Select -'), '#default_value' => $draft['source_kind'] ?? '',
        '#options' => ['promotion' => $this->t('Promotion'), 'admin_gift' => $this->t('Administrator gift'), 'compensation' => $this->t('Compensation')]];
      $form['cap'] = $this->field($this->t('Total reward allocation (points)'), $draft['cap'] ?? '', $this->t('Up to three decimal places. Fixed allocations must be a multiple of the per-grant amount.'));
      $form['grant_limit'] = $this->field($this->t('Fixed amount or per-grant maximum (points)'), $draft['grant_limit'] ?? '');
      $form['expires_at'] = $this->field($this->t('Latest grant expiry (UTC)'), $draft['expires_at'] ?? '', $this->t('Leave empty for no expiry. Otherwise use YYYY-MM-DD HH:MM, after the period start. Existing credits remain valid after the issuance period ends.'), FALSE);
      $budgets = $this->publication->catalog('budget'); $options = [];
      foreach ($budgets['rows'] as $row) {
        $options[$row['id']] = $row['category'] . ' / ' . $row['currency'] . ' / ' . gmdate('Y-m-d H:i', (int) $row['period_start']) . ' / ' . $row['publication_ref'];
      }
      $form['bounds'] = ['#type' => 'fieldset', '#title' => $this->t('Cost bound for every eligible category'), '#tree' => TRUE,
        '#description' => $this->t('Each category must cover the full allocation. Variable grants reserve the worst rounding cost down to 0.001 point. Leave an unused row empty. The most recent 100 budgets are listed.')];
      for ($i = 0; $i < ($form_state->get('bound_count') ?? 2); $i++) {
        $values = $draft['bounds'][$i] ?? [];
        $form['bounds'][$i] = ['#type' => 'fieldset', '#title' => $this->t('Category @number', ['@number' => $i + 1])];
        $form['bounds'][$i]['bucket_id'] = ['#type' => 'select', '#title' => $this->t('Published cost budget'),
          '#empty_option' => $this->t('- Select -'), '#options' => $options, '#default_value' => $values['bucket_id'] ?? ''];
        $form['bounds'][$i]['rate'] = $this->field($this->t('Maximum currency cost per point'), $values['rate'] ?? '', $this->t('Use the selected budget currency, up to six decimal places. This is a cost bound, not a credit sale price.'), FALSE);
        $form['bounds'][$i]['source_ref'] = $this->field($this->t('Cost bound evidence reference'), $values['source_ref'] ?? '', '', FALSE);
      }
      $form['add_bound'] = ['#type' => 'submit', '#value' => $this->t('Add category'), '#name' => 'add_bound', '#limit_validation_errors' => [],
        '#submit' => ['::submitForm'], '#access' => ($form_state->get('bound_count') ?? 2) < 32];
    }
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['review'] = ['#type' => 'submit', '#value' => $this->t('Review publication'), '#name' => 'review'];
    $form['catalog'] = ['#type' => 'details', '#title' => $this->t('Published versions'), '#open' => TRUE];
    $form['catalog']['items'] = ['#theme' => 'item_list', '#items' => [], '#empty' => $this->t('No published versions.')];
    foreach ($catalog['rows'] as $row) {
      $summary = $type === 'budget'
        ? $row['category'] . ' / ' . $row['currency'] . ' ' . CreditValue::display((string) $row['cap_micros'], 6)
        : $row['rule_id'] . ' / ' . $row['rule_version'] . ' / ' . $this->t('@amount points', ['@amount' => CreditValue::display((string) $row['cap_atoms'], 3)]);
      $form['catalog']['items']['#items'][] = ['#type' => 'details', '#title' => $summary . ' / ' . $row['publication_ref'],
        'record' => ['#type' => 'table', '#header' => [$this->t('Setting'), $this->t('Value')], '#rows' => $this->summary($type, $row, $type === 'pool' ? $budgets['rows'] : [])]];
    }
    if ($catalog['has_more']) $form['catalog']['limit'] = ['#plain_text' => $this->t('Showing the most recent 100 versions. Older versions are retained.')];
    return $form;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getTriggeringElement()['#name'] ?? '') !== 'review') return;
    $input = $form_state->getValues(); $type = $form_state->get('publication_type');
    try {
      $value = array_intersect_key($input, array_flip(['publication_ref', 'source_ref', 'category', 'currency', 'rule_id', 'rule_version', 'mode', 'source_kind']));
      foreach (['period_start', 'period_end', ...($type === 'pool' ? ['expires_at'] : [])] as $field) {
        $value[$field] = $field === 'expires_at' && $input[$field] === '' ? NULL : $this->date($input[$field]);
      }
      $value[$type === 'budget' ? 'cap_micros' : 'cap_atoms'] = CreditValue::decimal($input['cap'], $type === 'budget' ? 6 : 3);
      if ($type === 'budget') $value['execution_headroom_micros'] = CreditValue::decimal($input['headroom'], 6);
      else {
        $value['grant_limit_atoms'] = CreditValue::decimal($input['grant_limit'], 3);
        $value['bounds'] = [];
        foreach ($input['bounds'] as $bound) {
          if ($bound['bucket_id'] === '' && $bound['rate'] === '' && $bound['source_ref'] === '') continue;
          $value['bounds'][] = ['bucket_id' => $bound['bucket_id'], 'micros_per_point' => CreditValue::decimal($bound['rate'], 6), 'source_ref' => $bound['source_ref']];
        }
      }
      $form_state->set('normalized_publication', $this->publication->normalize($type, $value));
    }
    catch (CreditException $error) { $form_state->setErrorByName('', $this->message($error)); }
  }

  /** {@inheritdoc} */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $action = $form_state->getTriggeringElement()['#name'];
    if ($action === 'publish') {
      try {
        $receipt = $this->publication->publish($form_state->get('publication_type'), $form_state->get('publication_review'));
        $this->messenger()->addStatus($this->t('Published @reference. No credits were issued.', ['@reference' => $receipt['publication_ref']]));
        $form_state->setRedirect($form_state->get('publication_type') === 'budget' ? 'xinshi_credits.budgets' : 'xinshi_credits.pools');
        return;
      }
      catch (CreditException $error) { $this->messenger()->addError($this->message($error)); }
      catch (\Throwable $error) {
        // Database exceptions can contain private cost evidence in their query parameters.
        $this->logger('xinshi_credits')->error('Credit publication failed with @class.', ['@class' => $error::class]);
        $this->messenger()->addError($this->t('Publication could not be confirmed. Retry with the same reference.'));
      }
    }
    elseif ($action === 'back') $form_state->set('publication_review', NULL);
    elseif ($action === 'add_bound') {
      $form_state->set('publication_draft', $form_state->getUserInput());
      $form_state->set('bound_count', min(32, ($form_state->get('bound_count') ?? 2) + 1));
    }
    else {
      $value = $form_state->get('normalized_publication');
      $form_state->set('publication_review', $value)->set('publication_draft', $form_state->getValues());
      $form_state->set('publication_summary', $this->summary($form_state->get('publication_type'), $value));
    }
    $form_state->setRebuild();
  }

  private function field(mixed $title, string $value, mixed $description = '', bool $required = TRUE): array {
    return ['#type' => 'textfield', '#title' => $title, '#default_value' => $value, '#description' => $description,
      '#required' => $required, '#maxlength' => 255];
  }

  private function date(mixed $value): int {
    if (!is_string($value)) throw new CreditException('invalid_period');
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, new \DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d H:i') !== $value) throw new CreditException('invalid_period');
    return $date->getTimestamp();
  }

  private function summary(string $type, array $value, ?array $budgetRows = NULL): array {
    $rows = [[$this->t('Publication reference'), $value['publication_ref']], [$this->t('Evidence reference'), $value['source_ref']],
      [$this->t('Period start (UTC)'), gmdate('Y-m-d H:i', (int) $value['period_start'])],
      [$this->t('Period end, exclusive (UTC)'), gmdate('Y-m-d H:i', (int) $value['period_end'])]];
    if ($type === 'budget') {
      foreach (['category' => $this->t('Service category'), 'currency' => $this->t('Currency')] as $key => $label) $rows[] = [$label, $value[$key]];
      foreach (['cap_micros' => $this->t('Cost budget'), 'execution_headroom_micros' => $this->t('Execution and failure reserve'),
        'planned_cost_micros' => $this->t('Published reward cost plans'),
        'committed_cost_micros' => $this->t('Current commitments'), 'reserved_cost_micros' => $this->t('Current reservations'), 'actual_cost_micros' => $this->t('Actual cost')] as $key => $label) {
        if (isset($value[$key])) $rows[] = [$label, CreditValue::display((string) $value[$key], 6) . ' ' . $value['currency']];
      }
    }
    else {
      $policy = isset($value['grant_policy_json']) ? json_decode($value['grant_policy_json'], TRUE, 16, JSON_THROW_ON_ERROR) : $value;
      $rows[] = [$this->t('Rule / version'), $value['rule_id'] . ' / ' . $value['rule_version']];
      $rows[] = [$this->t('Source'), $policy['source_kind']];
      $rows[] = [$this->t('Amount policy'), $policy['mode'] === 'fixed' ? $this->t('Fixed') : $this->t('Variable')];
      $rows[] = [$this->t('Total allocation (points)'), CreditValue::display((string) $value['cap_atoms'], 3)];
      $rows[] = [$this->t('Per-grant amount or maximum (points)'), CreditValue::display($policy['grant_limit_atoms'], 3)];
      $rows[] = [$this->t('Latest expiry (UTC)'), $policy['expires_at'] === NULL ? $this->t('No expiry') : gmdate('Y-m-d H:i', $policy['expires_at'])];
      if (isset($value['issued_atoms'])) $rows[] = [$this->t('Issued (points)'), CreditValue::display((string) $value['issued_atoms'], 3)];
      $bounds = $value['bounds'] ?? array_values(json_decode($value['bounds_json'], TRUE, 16, JSON_THROW_ON_ERROR));
      $evidence = isset($value['cost_evidence_json']) ? array_values(json_decode($value['cost_evidence_json'], TRUE, 16, JSON_THROW_ON_ERROR)) : [];
      $budgets = array_column($budgetRows ?? $this->publication->catalog('budget')['rows'], NULL, 'id');
      foreach ($bounds as $i => $bound) {
        $budget = $budgets[$bound['bucket_id']] ?? NULL;
        $name = $budget ? $budget['category'] . ' / ' . $budget['currency'] : $bound['bucket_id'];
        $rows[] = [$this->t('Category cost bound'), $name . ' / ' . CreditValue::display($bound['micros_per_point'], 6)];
        $rows[] = [$this->t('Cost evidence'), $bound['source_ref'] ?? $evidence[$i]];
      }
    }
    return $rows;
  }

  private function message(CreditException $error): mixed {
    return match ($error->reason) {
      'permission_denied' => $this->t('You do not have permission to publish credit limits.'),
      'credits_not_configured' => $this->t('Configure the credit site identifier before publishing limits.'),
      'invalid_amount', 'amount_overflow' => $this->t('Enter nonnegative decimal amounts within the supported range. Budgets, allocations and grant limits must be positive.'),
      'invalid_period' => $this->t('Check the UTC dates. The start must precede the end, and the end and latest expiry must be in the future.'),
      'invalid_grant_policy' => $this->t('Check the grant amount, source and expiry. Fixed allocations must be a multiple of the per-grant amount. A specified expiry must be after the allocation start.'),
      'invalid_budget_bound', 'cost_budget_unavailable', 'invalid_budget_scope' => $this->t('Select distinct published category budgets that cover the entire period. Supply a positive cost bound and evidence for each category.'),
      'reward_budget_exhausted' => $this->t('The cost budget cannot cover this allocation and its execution reserve. Review the amounts and evidence.'),
      'publication_period_overlap' => $this->t('This category or rule already has a version in this period. Select a non-overlapping period.'),
      'idempotency_conflict' => $this->t('This publication reference already has different settings. Keep the original version and use a new reference for a new period.'),
      default => $this->t('Publication could not be completed. Check the values and retry with the same reference. Error: @code', ['@code' => $error->reason]),
    };
  }

}

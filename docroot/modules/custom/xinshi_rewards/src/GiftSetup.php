<?php

declare(strict_types=1);

namespace Drupal\xinshi_rewards;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_credits\{CreditContext, CreditException, CreditPublication, CreditValue};

/** Publishes administrator allocations through the shared immutable policy store. */
final class GiftSetup {

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $config,
    private readonly CreditContext $context,
    private readonly CreditPublication $publication,
    private readonly AccountProxyInterface $user,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /** Read the selected rule and initial scope without creating an allocation. */
  public function current(): array {
    $this->authorize();
    $settings = $this->config->get('xinshi_rewards.settings');
    $ids = $settings->get('points_pool_ids') ?? [];
    $selected = $settings->get('admin_gift_pool_id');
    if ($selected) $ids[] = $selected;
    $rows = $ids ? $this->database->select('credit_reward_pool', 'p')->fields('p')
      ->condition('site_id', $this->context->site())->condition('id', array_unique($ids), 'IN')
      ->condition('accounting_mode', 'points')->execute()->fetchAll(\PDO::FETCH_ASSOC) : [];
    $pool = NULL;
    foreach ($rows as $row) {
      if ($row['rule_id'] === 'G12' && (!$selected || $row['id'] === $selected)) $pool = $row;
    }
    return ['pool' => $pool, 'enabled' => (bool) $settings->get('admin_gifts_enabled'),
      'default_policy' => $pool ?? ($rows[0] ?? NULL)];
  }

  /** Freeze explicit limits and the configuration version for the review step. */
  public function prepare(array $input, string $version, int $start): array {
    CreditValue::fields($input, ['cap_atoms', 'grant_limit_atoms', 'scope', 'period_end', 'expires_at']);
    $current = $this->current();
    $site = $this->context->site();
    if ($this->database->select('credit_grant', 'g')->condition('site_id', $site)
      ->condition('accounting_mode', 'budgeted')->range(0, 1)->fields('g', ['id'])->execute()->fetchField()) {
      throw new CreditException('gift_setup_requires_points', 409);
    }
    $value = $this->publication->normalize('pool', $input + [
      'publication_ref' => $version, 'rule_version' => $version, 'period_start' => $start,
      'source_ref' => 'Administrator gift policy ' . $version, 'rule_id' => 'G12',
      'mode' => 'variable', 'source_kind' => 'admin_gift', 'accounting_mode' => 'points',
    ]);
    if ($value['period_end'] <= time() || ($value['expires_at'] !== NULL && $value['expires_at'] <= time())) {
      throw new CreditException('invalid_period');
    }
    return ['input' => $value, 'site' => $site, 'previous_pool' => $current['pool']['id'] ?? '',
      'previous_enabled' => $current['enabled']];
  }

  /** Publish and activate one reviewed rule atomically, without issuing credits. */
  public function save(array $draft): void {
    $this->authorize();
    $transaction = $this->database->startTransaction();
    try {
      $this->lockConfiguration();
      $current = $this->current();
      $value = $this->publication->normalize('pool', $draft['input']);
      if (($current['pool']['publication_ref'] ?? '') === $value['publication_ref']) {
        if (!hash_equals($current['pool']['publication_hash'], hash('sha256', json_encode($value, JSON_THROW_ON_ERROR)))) {
          throw new CreditException('idempotency_conflict', 409);
        }
        $transaction->rollBack();
        return;
      }
      $review = $this->prepare(array_intersect_key($value, array_flip([
        'cap_atoms', 'grant_limit_atoms', 'scope', 'period_end', 'expires_at',
      ])), $value['publication_ref'], $value['period_start']);
      if ($review !== $draft) throw new CreditException('gift_configuration_changed', 409);
      $pool = $this->publication->publish('pool', $value);
      $this->config->getEditable('xinshi_rewards.settings')->set('admin_gift_pool_id', $pool['id'])
        ->set('admin_gifts_enabled', TRUE)->save();
      $transaction->commitOrRelease();
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      $this->config->reset();
      throw $error;
    }
  }

  /** Pause or resume the same allocation without resetting its issued quantity. */
  public function setEnabled(bool $enabled, string $poolId): void {
    $this->authorize();
    $transaction = $this->database->startTransaction();
    try {
      $this->lockConfiguration();
      $current = $this->current();
      if (!$poolId || ($current['pool']['id'] ?? '') !== $poolId) throw new CreditException('gift_configuration_changed', 409);
      $policy = json_decode($current['pool']['grant_policy_json'], TRUE, 16, JSON_THROW_ON_ERROR);
      if ($enabled && ((int) $current['pool']['period_end'] <= time()
        || ($policy['expires_at'] !== NULL && $policy['expires_at'] <= time()))) throw new CreditException('invalid_period');
      $this->config->getEditable('xinshi_rewards.settings')->set('admin_gifts_enabled', $enabled)->save();
      $transaction->commitOrRelease();
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      $this->config->reset();
      throw $error;
    }
  }

  private function lockConfiguration(): void {
    $locked = $this->database->update('credit_publication_guard')->expression('generation', 'generation + 1')
      ->condition('id', 1)->condition('generation', PHP_INT_MAX, '<')->execute();
    if ($locked !== 1) throw new CreditException('publication_unavailable', 503);
    $this->config->reset();
  }

  private function authorize(): void {
    if (!$this->user->isAuthenticated()) throw new CreditException('permission_denied', 403);
    foreach (['administer credit budgets', 'administer site configuration'] as $permission) {
      if (!$this->user->hasPermission($permission)) throw new CreditException('permission_denied', 403);
    }
    $user = $this->entities->getStorage('user')->load($this->user->id());
    if (!$user || !$user->isActive()) throw new CreditException('permission_denied', 403);
  }

}

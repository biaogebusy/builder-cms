<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use Drupal\xinshi_credits\{CreditException, CreditPublication, CreditValue};

/** Publishes a small trial policy through the existing ledger and billing services. */
final class TrialSetup {

  public const PERMISSIONS = ['administer site configuration', 'administer credit budgets', 'administer ai credit prices', 'administer permissions'];
  public const USER_PERMISSIONS = ['view own credits', 'receive welcome credits', 'receive daily credits', 'use ai credits'];

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $config,
    private readonly CreditPublication $publication,
    private readonly BillingProfiles $profiles,
    private readonly LocalUsageProducer $usage,
    private readonly AccountProxyInterface $user,
    private readonly EntityTypeManagerInterface $entities,
  ) {}

  /** Return only enabled platform-funded models from the site's existing catalog. */
  public function models(string $capability): array {
    $catalog = $this->config->get('xinshi_ai.models'); $models = [];
    foreach ($catalog->get('models') ?? [] as $model) {
      $platform = $catalog->get('platforms.' . ($model['platform'] ?? ''));
      if (($model['enabled'] ?? TRUE) && ($platform['enabled'] ?? FALSE) && empty($platform['user_supplied'])
        && in_array($capability, $model['capabilities'] ?? [], TRUE)) $models[$model['id']] = $model;
    }
    return $models;
  }

  /** Validate and freeze form inputs; no publication, grant or provider call occurs here. */
  public function prepare(array $input, string $version, int $start): array {
    $this->authorize();
    CreditValue::fields($input, ['welcome', 'daily', 'chat', 'chat_model', 'role', 'timezone', 'expires_at']);
    foreach (['welcome', 'daily', 'chat'] as $key) {
      $input[$key] = (string) CreditValue::amount($input[$key], TRUE);
      if ((int) $input[$key] > 1000000) throw new CreditException('invalid_trial_amount');
    }
    if ((int) $input['welcome'] < 5 * (int) $input['chat']) throw new CreditException('trial_experience_too_small');
    $input['chat_model'] = CreditValue::text($input['chat_model'], 128);
    $chat = $this->models('chat')[$input['chat_model']] ?? NULL;
    if (!$chat) throw new CreditException('trial_models_unavailable');
    $role = $this->entities->getStorage('user_role')->load(CreditValue::id($input['role']));
    if (!$role || $role->id() === 'anonymous' || $role->isAdmin()) throw new CreditException('invalid_trial_role');
    if (!in_array($input['timezone'], \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), TRUE)) throw new CreditException('invalid_trial_timezone');
    if (!$this->database->schema()->tableExists('credit_reward_calendar')) throw new CreditException('trial_rewards_required');
    $zone = $this->database->select('credit_reward_calendar', 'c')->fields('c', ['timezone'])->condition('id', 1)->execute()->fetchField();
    if ($zone && $zone !== $input['timezone']) throw new CreditException('reward_calendar_changed');
    $input['expires_at'] = CreditValue::expiry($input['expires_at']);
    if ($input['expires_at'] !== NULL && $input['expires_at'] <= time()) throw new CreditException('invalid_expiry');
    if ($this->usage->mode() !== LocalUsageProducer::MODE_ENFORCE || !$this->usage->siteId()) throw new CreditException('image_metering_unavailable');
    $site = $this->config->get('xinshi_credits.settings')->get('site_id');
    if ($site && $site !== $this->usage->siteId()) throw new CreditException('trial_site_mismatch');
    // Mixing spend modes would make the aggregate available balance misleading.
    if ($this->database->select('credit_grant', 'g')->condition('site_id', $this->usage->siteId())
      ->condition('accounting_mode', 'budgeted')->countQuery()->execute()->fetchField()) throw new CreditException('trial_existing_budgeted_credits');
    foreach ($this->config->get('xinshi_ai_billing.settings')->get('active_versions') ?? [] as $active) {
      if (($this->profiles->load($active)['accounting_mode'] ?? '') !== 'points') throw new CreditException('trial_existing_budgeted_prices');
    }
    if ($start <= 0 || $start > time() || time() - $start > 86400) throw new CreditException('invalid_period');
    return ['input' => $input, 'version' => CreditValue::id($version), 'start' => $start, 'site' => $this->usage->siteId(),
      'previous_version' => $this->config->get('xinshi_ai_billing.trial')->get('version') ?? '',
      'chat' => $chat, 'models_hash' => hash('sha256', BillingProfiles::json($this->config->get('xinshi_ai.models')->getRawData()))];
  }

  /** Activate reviewed immutable policies; retries cannot issue rewards or erase old records. */
  public function save(array $draft): void {
    $this->authorize();
    if (($this->config->get('xinshi_ai_billing.trial')->get('version') ?? '') === $draft['version']) return;
    $transaction = $this->database->startTransaction();
    try {
      $locked = $this->database->update('credit_publication_guard')->expression('generation', 'generation + 1')
        ->condition('id', 1)->condition('generation', PHP_INT_MAX, '<')->execute();
      if ($locked !== 1) throw new CreditException('publication_unavailable', 503);
      $this->config->reset();
      if (($this->config->get('xinshi_ai_billing.trial')->get('version') ?? '') === $draft['version']) {
        $transaction->rollBack();
        return;
      }
      $current = $this->prepare($draft['input'], $draft['version'], $draft['start']);
      if (BillingProfiles::json($current) !== BillingProfiles::json($draft)) throw new CreditException('trial_configuration_changed', 409);
      $i = $draft['input']; $version = $draft['version']; $poolIds = [];
      $versions = array_values(array_filter($this->config->get('xinshi_ai_billing.settings')->get('active_versions') ?? [],
        fn(string $active): bool => $this->profiles->load($active)['feature'] !== 'common'));
      $this->config->getEditable('xinshi_credits.settings')->set('site_id', $draft['site'])->save();
      foreach (['G13' => 'welcome', 'G11' => 'daily'] as $rule => $key) {
        $atoms = CreditValue::multiply((int) $i[$key], 1000);
        $pool = $this->publication->publish('pool', ['publication_ref' => $version . '-' . $rule,
          'period_start' => $draft['start'], 'period_end' => 4102444800, 'source_ref' => 'Trial policy ' . $version,
          'rule_id' => $rule, 'rule_version' => $version, 'mode' => 'fixed', 'source_kind' => 'promotion',
          'accounting_mode' => 'points', 'scope' => ['chat', 'image'], 'grant_limit_atoms' => (string) $atoms,
          'cap_atoms' => (string) CreditValue::multiply($atoms, 1000000), 'expires_at' => $i['expires_at']]);
        $poolIds[] = $pool['id'];
      }
      $calls = [];
      foreach (['primary' => [$draft['chat']['id']], 'auxiliary' => [
        $this->config->get('xinshi_ai.models')->get('defaults.classifier'),
        $this->config->get('xinshi_ai.models')->get('defaults.critic')], 'repair' => [$draft['chat']['id']]] as $role => $ids) {
        foreach (array_unique(array_filter($ids)) as $id) {
          $model = $this->models('chat')[$id] ?? NULL;
          if (!$model) throw new CreditException('trial_models_unavailable');
          $calls[] = ['model' => $id, 'account_ref' => $model['platform'], 'role' => $role,
            'max_calls' => 8, 'max_input_tokens' => 131072, 'max_output_tokens' => 32768, 'max_images' => 0];
        }
      }
      foreach (['common'] as $feature) {
        $p = $this->profiles->publish(['version' => $version . '-' . $feature, 'feature' => $feature,
          'model' => $draft['chat']['id'], 'platform' => $draft['chat']['platform'], 'category' => 'chat',
          'accounting_mode' => 'points', 'charge_atoms' => (string) ((int) $i['chat'] * 1000),
          'evidence_ref' => 'Trial policy ' . $version, 'quote_ttl_seconds' => 300, 'calls' => $calls]);
        $versions[] = $p['version'];
      }
      $this->config->getEditable('xinshi_ai_billing.trial')->setData($i + ['version' => $version])->save();
      $this->config->getEditable('xinshi_rewards.settings')->set('points_pool_ids', $poolIds)->set('business_timezone', $i['timezone'])
        ->set('welcome_enabled', TRUE)->set('daily_enabled', TRUE)->set('admin_gifts_enabled', FALSE)->save();
      $this->config->getEditable('xinshi_ai_billing.settings')->set('active_versions', $versions)->set('enabled', TRUE)->save();
      $this->config->getEditable('xinshi_credits.settings')->set('grants_enabled', TRUE)->set('spending_enabled', TRUE)->save();
      $role = $this->entities->getStorage('user_role')->load($i['role']);
      foreach (self::USER_PERMISSIONS as $permission) $role->grantPermission($permission);
      $role->save();
      $transaction->commitOrRelease();
    }
    catch (\Throwable $error) {
      $transaction->rollBack();
      $this->config->reset();
      $this->entities->getStorage('user_role')->resetCache();
      throw $error;
    }
  }

  private function authorize(): void {
    foreach (self::PERMISSIONS as $permission) if (!$this->user->hasPermission($permission)) throw new CreditException('permission_denied', 403);
    $user = $this->entities->getStorage('user')->load($this->user->id());
    if (!$user || !$user->isActive()) throw new CreditException('permission_denied', 403);
  }

}

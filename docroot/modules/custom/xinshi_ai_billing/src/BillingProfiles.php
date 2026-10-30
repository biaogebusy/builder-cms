<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_billing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\xinshi_credits\{CreditContext, CreditException, CreditValue};

/** Immutable sales specifications; procurement prices remain in AI usage. */
final class BillingProfiles {

  public function __construct(
    private readonly Connection $database,
    private readonly ConfigFactoryInterface $config,
    private readonly CreditContext $context,
  ) {}

  /** Publish validated source-backed prices without enabling them. */
  public function publish(array $input): array {
    $profile = self::validate($input);
    $site = $this->context->site();
    $json = self::json($profile);
    try {
      $this->database->insert('ai_billing_profile')->fields(['site_id' => $site,
        'version' => $profile['version'], 'profile_json' => $json])->execute();
    }
    catch (\Drupal\Core\Database\IntegrityConstraintViolationException $error) {
      if (self::json($this->load($profile['version'])) !== $json) {
        throw new CreditException('price_version_conflict', 409);
      }
    }
    return $profile;
  }

  /** Load an original version, including versions no longer offered to new users. */
  public function load(string $version): array {
    $json = $this->database->select('ai_billing_profile', 'p')->fields('p', ['profile_json'])
      ->condition('site_id', $this->context->site())->condition('version', $version)->execute()->fetchField();
    if (!$json) throw new CreditException('price_unavailable', 503);
    return json_decode($json, TRUE, 32, JSON_THROW_ON_ERROR);
  }

  /** Require exactly one explicitly enabled profile for the actual request selection. */
  public function select(string $feature, string $model, string $platform): array {
    $found = [];
    $versions = $this->config->get('xinshi_ai_billing.settings')->get('active_versions') ?? [];
    if (count($versions) > 128) throw new CreditException('price_configuration_invalid', 503);
    foreach ($versions as $version) {
      $p = $this->load(CreditValue::id($version));
      if ($p['feature'] === $feature && $p['model'] === $model && $p['platform'] === $platform) $found[] = $p;
    }
    if (count($found) !== 1) throw new CreditException('price_unavailable', 503);
    return $found[0];
  }

  /** A conservative estimate equals the hard ceiling; no invented usage average. */
  public static function amounts(array $profile): array {
    $atoms = 0; $cost = 0;
    foreach ($profile['calls'] as $call) {
      $cost = CreditValue::add($cost, CreditValue::multiply($call['max_calls'], (int) $call['cost_limit_micros']));
      if ($call['role'] !== 'primary') continue;
      $charge = self::charge($call, $call['max_input_tokens'], $call['max_output_tokens'], $call['max_images']);
      $atoms = CreditValue::add($atoms, CreditValue::multiply($call['max_calls'], $charge));
    }
    if ($atoms === 0 || $cost === 0) throw new CreditException('invalid_price');
    return ['maximum_atoms' => (string) $atoms, 'estimated_atoms' => (string) $atoms,
      'estimate_basis' => 'upper_bound', 'cost_limit_micros' => (string) $cost];
  }

  /** Charge input/output inclusively, rounding once per attempt to the atom. */
  public static function charge(array $call, int $input, int $output, int $images): int {
    $tokens = CreditValue::add(CreditValue::multiply($input, (int) $call['input_atoms_per_million']),
      CreditValue::multiply($output, (int) $call['output_atoms_per_million']));
    return CreditValue::add(intdiv($tokens, 1000000) + ($tokens % 1000000 ? 1 : 0),
      CreditValue::multiply($images, (int) $call['atoms_per_image']));
  }

  /** Closed, bounded profile input; example values are never installed as defaults. */
  public static function validate(array $p): array {
    CreditValue::fields($p, ['version', 'feature', 'model', 'platform', 'category', 'currency',
      'cost_bucket_id', 'evidence_ref', 'quote_ttl_seconds', 'calls'], ['image']);
    foreach (['version', 'feature', 'platform', 'cost_bucket_id'] as $key) $p[$key] = CreditValue::id($p[$key]);
    $p['model'] = CreditValue::text($p['model'], 128);
    $p['evidence_ref'] = CreditValue::text($p['evidence_ref']);
    if (!in_array($p['category'], ['chat', 'image'], TRUE)
      || !is_string($p['currency']) || !preg_match('/^[A-Z]{3}$/D', $p['currency'])
      || !is_int($p['quote_ttl_seconds']) || $p['quote_ttl_seconds'] < 1 || $p['quote_ttl_seconds'] > 900
      || !is_array($p['calls']) || !array_is_list($p['calls']) || !$p['calls'] || count($p['calls']) > 32) {
      throw new CreditException('invalid_price');
    }
    $keys = []; $count = 0;
    foreach ($p['calls'] as &$call) {
      if (!is_array($call)) throw new CreditException('invalid_price');
      CreditValue::fields($call, ['model', 'account_ref', 'role', 'max_calls', 'max_input_tokens',
        'max_output_tokens', 'max_images', 'cost_limit_micros', 'input_atoms_per_million',
        'output_atoms_per_million', 'atoms_per_image']);
      $call['model'] = CreditValue::text($call['model'], 128);
      $call['account_ref'] = CreditValue::id($call['account_ref']);
      if (!in_array($call['role'], ['primary', 'auxiliary', 'repair'], TRUE)
        || $call['account_ref'] === 'customer_key') throw new CreditException('invalid_price');
      foreach (['max_calls', 'max_input_tokens', 'max_output_tokens', 'max_images'] as $key) {
        if (!is_int($call[$key]) || $call[$key] < 0 || $call[$key] > 10000000) throw new CreditException('invalid_price');
      }
      if ($call['max_calls'] < 1 || ($call['max_output_tokens'] === 0 && $call['max_images'] === 0)) {
        throw new CreditException('invalid_price');
      }
      if ($p['category'] === 'chat' && $call['max_images'] !== 0) throw new CreditException('invalid_price');
      foreach (['cost_limit_micros', 'input_atoms_per_million', 'output_atoms_per_million', 'atoms_per_image'] as $key) {
        $call[$key] = (string) CreditValue::amount($call[$key], $key === 'cost_limit_micros');
      }
      $id = self::json([$call['model'], $call['account_ref'], $call['role']]);
      if (isset($keys[$id])) throw new CreditException('invalid_price');
      $keys[$id] = TRUE; $count += $call['max_calls'];
    }
    unset($call);
    if ($p['category'] === 'image') {
      if (!is_array($p['image'] ?? NULL) || count($p['calls']) !== 1) throw new CreditException('invalid_price');
      CreditValue::fields($p['image'], ['max_prompt_bytes', 'params']);
      $image = $p['image'];
      if (!is_int($image['max_prompt_bytes']) || $image['max_prompt_bytes'] < 1 || $image['max_prompt_bytes'] > 100000
        || !is_array($image['params'])) throw new CreditException('invalid_price');
      CreditValue::fields($image['params'], ['n', 'size'], ['quality', 'output_format', 'response_format']);
      if (!is_int($image['params']['n']) || $image['params']['n'] < 1 || $image['params']['n'] > 16) {
        throw new CreditException('invalid_price');
      }
      foreach ($image['params'] as $key => $value) if ($key !== 'n') CreditValue::text($value, 128);
      $primary = $p['calls'][0];
      if ($primary['role'] !== 'primary' || $primary['max_calls'] !== 1
        || $primary['model'] !== $p['model'] || $primary['account_ref'] !== $p['platform']
        || $primary['max_images'] !== $image['params']['n'] || $primary['atoms_per_image'] === '0'
        || $primary['input_atoms_per_million'] !== '0' || $primary['output_atoms_per_million'] !== '0') {
        throw new CreditException('invalid_price');
      }
    }
    elseif (isset($p['image']) || array_filter($p['calls'], static fn(array $c): bool => $c['atoms_per_image'] !== '0')) {
      throw new CreditException('invalid_price');
    }
    if ($count > 128) throw new CreditException('invalid_price');
    self::amounts($p);
    return $p;
  }

  /** Deterministic JSON for immutable identity and replay comparisons. */
  public static function json(mixed $value): string {
    if (is_array($value)) {
      if (!array_is_list($value)) ksort($value, SORT_STRING);
      foreach ($value as &$item) if (is_array($item)) $item = json_decode(self::json($item), TRUE, 32, JSON_THROW_ON_ERROR);
      unset($item);
    }
    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Database\Database;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;

/** Assemble financial services once per container, preserving outstanding work. */
final class XinshiCreditsServiceProvider extends ServiceProviderBase {

  /** {@inheritdoc} */
  public function alter(ContainerBuilder $container) {
    $storage = BootstrapConfigStorageFactory::get();
    // Site overrides have the same priority as ConfigFactory's settings.php overrides.
    $read = static fn(string $name): array => array_replace_recursive(
      $storage->read($name) ?: [], $GLOBALS['config'][$name] ?? []);
    $credits = $read('xinshi_credits.settings');
    $billing = $read('xinshi_ai_billing.settings');
    $rewards = $read('xinshi_rewards.settings');
    foreach ([[$credits, ['grants_enabled', 'spending_enabled']],
      [$billing, ['enabled', 'image_enabled']],
      [$rewards, ['daily_enabled', 'welcome_enabled', 'admin_gifts_enabled']]] as [$config, $flags]) {
      foreach ($flags as $flag) {
        if (array_key_exists($flag, $config) && !is_bool($config[$flag])) {
          throw new \LogicException('Invalid financial configuration flag: ' . $flag);
        }
      }
    }
    $installed = $container->getParameter('container.modules');
    $charging = isset($installed['xinshi_ai_billing']) && ($billing['enabled'] ?? FALSE) === TRUE;
    $active = ($credits['grants_enabled'] ?? FALSE) === TRUE
      || ($credits['spending_enabled'] ?? FALSE) === TRUE || $charging;
    $db = Database::getConnection();
    $pending = static function (string $table, string $state, string $operator) use ($db): bool {
      return $db->schema()->tableExists($table)
        && (bool) $db->select($table, 'p')->fields('p', ['state'])
          ->condition('state', $state, $operator)->range(0, 1)->execute()->fetchField();
    };
    $billingPending = $pending('ai_billing_authorization', 'closed', '<>');
    $creditPending = $pending('credit_reservation', 'held', '=');
    $defaults = [
      'credits' => $active || $creditPending || $billingPending,
      'billing' => $charging || $billingPending,
      'rewards' => isset($installed['xinshi_rewards']) && ($credits['grants_enabled'] ?? FALSE) === TRUE
        && (($rewards['daily_enabled'] ?? FALSE) === TRUE || ($rewards['welcome_enabled'] ?? FALSE) === TRUE
          || ($rewards['admin_gifts_enabled'] ?? FALSE) === TRUE),
    ];
    $overrides = Settings::get('xinshi_finance', []);
    if (!is_array($overrides) || array_diff_key($overrides, $defaults)) {
      throw new \LogicException('Invalid xinshi_finance assembly settings');
    }
    $assembly = array_replace($defaults, $overrides);
    foreach ($assembly as $name => $enabled) {
      $module = $name === 'billing' ? 'xinshi_ai_billing' : 'xinshi_' . $name;
      if (!is_bool($enabled) || ($enabled && !isset($installed[$module]))) {
        throw new \LogicException('Invalid or missing financial module: ' . $name);
      }
    }
    if ((!$assembly['credits'] && ($active || $creditPending || $billingPending || $assembly['billing'] || $assembly['rewards']))
      || (!$assembly['billing'] && ($charging || $billingPending))) {
      throw new \LogicException('Stop new financial operations and reconcile pending records before disabling modules');
    }
    $container->setParameter('xinshi_finance.assembly', $assembly);
    $container->setParameter('xinshi_finance.chat_billing', $assembly['billing'] && $charging);
    $container->setParameter('xinshi_site_finance', [
      'version' => 1, 'credits' => $assembly['credits'],
      'billing_recovery' => $assembly['billing'],
      'chat_billing' => $assembly['billing'] && $charging,
      'image_billing' => $assembly['billing'] && $charging && ($billing['image_enabled'] ?? FALSE) === TRUE,
    ]);
    foreach ($assembly as $name => $enabled) {
      if ($enabled) continue;
      $prefix = $name === 'billing' ? 'xinshi_ai_billing.' : 'xinshi_' . $name . '.';
      foreach (array_keys($container->getDefinitions()) as $id) {
        if (str_starts_with($id, $prefix) && !in_array($id, [
          $prefix . 'uninstall', 'xinshi_credits.routes',
        ], TRUE)) $container->removeDefinition($id);
      }
    }
  }

}

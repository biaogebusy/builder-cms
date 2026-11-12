<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Database\Database;

/** Stop collection only after current facts and unknown requests are reconciled. */
final class XinshiAiUsageServiceProvider extends ServiceProviderBase {

  /** {@inheritdoc} */
  public function alter(ContainerBuilder $container) {
    if ($container->getParameter('xinshi_site_finance')['metering'] !== 'off') return;
    $db = Database::getConnection();
    $events = $db->schema()->tableExists('ai_usage_event') && $db->select('ai_usage_event', 'e')
      ->fields('e', ['id'])->isNull('processed_at')->range(0, 1)->execute()->fetchField();
    $unresolved = $db->condition('OR')->condition('state', ['prepared', 'in_flight', 'unknown'], 'IN')
      ->condition('dispatch_state', 'unknown');
    $unknown = $db->schema()->tableExists('ai_provider_attempt') && $db->select('ai_provider_attempt', 'a')
      ->fields('a', ['id'])->condition($unresolved)
      ->range(0, 1)->execute()->fetchField();
    if ($events || $unknown) {
      throw new \LogicException('Process usage events and reconcile unknown attempts before selecting off');
    }
    foreach (array_keys($container->getDefinitions()) as $id) {
      if (str_starts_with($id, 'xinshi_ai_usage.')) $container->removeDefinition($id);
    }
  }

}

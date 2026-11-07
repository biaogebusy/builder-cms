<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;

/** Usage remains available without any financial module. */
final class XinshiAiUsageServiceProvider extends ServiceProviderBase {

  /** {@inheritdoc} */
  public function register(ContainerBuilder $container) {
    // Without credits installed, its provider cannot validate financial overrides.
    if (!isset($container->getParameter('container.modules')['xinshi_credits'])) {
      $overrides = Settings::get('xinshi_finance', []);
      if (!is_array($overrides)
        || array_diff_key($overrides, array_flip(['credits', 'billing', 'rewards']))
        || array_filter($overrides, static fn(mixed $enabled): bool => $enabled !== FALSE)) {
        throw new \LogicException('Financial assembly requires installed modules or explicit FALSE overrides');
      }
    }
    $container->setParameter('xinshi_site_finance', [
      'version' => 1, 'credits' => FALSE, 'chat_billing' => FALSE, 'image_billing' => FALSE,
      'billing_recovery' => FALSE,
    ]);
  }

}

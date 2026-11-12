<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_runtime;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;

/** Publish startup-fixed capabilities even when only Chat is installed. */
final class XinshiAiRuntimeServiceProvider extends ServiceProviderBase {

  /** {@inheritdoc} */
  public function register(ContainerBuilder $container) {
    $installed = $container->getParameter('container.modules');
    $usage = isset($installed['xinshi_ai_usage']);
    $mode = Settings::get('xinshi_ai_usage.mode', $usage ? 'observe' : 'off');
    if (!in_array($mode, ['off', 'observe', 'enforce'], TRUE) || (!$usage && $mode !== 'off')) {
      throw new \LogicException('Invalid metering mode or missing xinshi_ai_usage module');
    }
    if (!isset($installed['xinshi_credits'])) {
      $overrides = Settings::get('xinshi_finance', []);
      if (!is_array($overrides)
        || array_diff_key($overrides, array_flip(['credits', 'billing', 'rewards']))
        || array_filter($overrides, static fn(mixed $enabled): bool => $enabled !== FALSE)) {
        throw new \LogicException('Financial assembly requires installed modules or explicit FALSE overrides');
      }
    }
    $container->setParameter('xinshi_site_finance', [
      'version' => 2, 'metering' => $mode, 'harness' => isset($installed['xinshi_ai']), 'credits' => FALSE,
      'chat_billing' => FALSE, 'image_billing' => FALSE, 'billing_recovery' => FALSE,
    ]);
  }

}

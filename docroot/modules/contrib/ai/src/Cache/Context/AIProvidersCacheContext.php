<?php

declare(strict_types=1);

namespace Drupal\ai\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;

/**
 * Cache context that uses the existence of AI Provider plugins.
 */
final class AIProvidersCacheContext implements CalculatedCacheContextInterface {

  public function __construct(
    private readonly AiProviderPluginManager $aiProvider,
    private readonly AiVdbProviderPluginManager $aiVdbProvider,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string {
    return (string) t('AI Providers');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL): string {
    if ($plugins = $this->getPlugins()) {
      if ($parameter) {
        if (array_key_exists($parameter, $plugins)) {
          $context = $parameter;
        }
        else {
          $context = 'no-' . $parameter;
        }
      }
      else {
        $keys = array_keys($plugins);
        $context = implode('-', $keys);
      }
    }
    else {
      $context = 'none';
    }

    return $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL): CacheableMetadata {
    return new CacheableMetadata();
  }

  /**
   * Helper to get an array of the currently enabled AI Providers.
   *
   * @return array
   *   An array of the plugins.
   */
  private function getPlugins(): array {
    $plugins = $this->aiProvider->getDefinitions();

    return array_merge($plugins, $this->aiVdbProvider->getDefinitions());
  }

}

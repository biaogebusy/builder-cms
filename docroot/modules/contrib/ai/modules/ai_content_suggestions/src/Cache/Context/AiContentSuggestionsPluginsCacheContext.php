<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager;

/**
 * Adds a cache context for the existence of AI Content Suggestions plugins.
 */
final class AiContentSuggestionsPluginsCacheContext implements CalculatedCacheContextInterface {

  /**
   * Constructs the cache context.
   *
   * @param \Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager $pluginManager
   *   The Plugin Manager.
   */
  public function __construct(
    protected AiContentSuggestionsPluginManager $pluginManager,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): string {
    return (string) t('AI Content Suggestions Plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL): string {
    if ($plugins = $this->pluginManager->getDefinitions()) {
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

}

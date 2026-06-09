<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Field translation plugin manager.
 */
interface FieldTextExtractorPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface {

  /**
   * Get translator for the field type.
   *
   * @param string $fieldType
   *   Field type ID.
   *
   * @return \Drupal\ai_translate\Attribute\FieldTextExtractor|null
   *   Extractor plugin.
   */
  public function getExtractor(string $fieldType): ?FieldTextExtractorInterface;

}

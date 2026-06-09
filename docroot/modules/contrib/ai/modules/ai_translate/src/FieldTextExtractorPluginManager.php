<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_translate\Attribute\FieldTextExtractor;

/**
 * Field translation plugin manager.
 */
final class FieldTextExtractorPluginManager extends DefaultPluginManager implements FieldTextExtractorPluginManagerInterface {

  use StringTranslationTrait;

  /**
   * Array of plugin definitions, keyed by field_type.
   *
   * @var array
   */
  protected array $definitionsByFieldType = [];

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/FieldTextExtractor', $namespaces, $module_handler, FieldTextExtractorInterface::class, FieldTextExtractor::class);
    $this->alterInfo('ai_translate_text_extract_info');
    $this->setCacheBackend($cache_backend, 'ai_translate_text_extractors');
  }

  /**
   * {@inheritdoc}
   */
  public function getExtractor(string $fieldType): ?FieldTextExtractorInterface {
    if (empty($this->definitionsByFieldType[$fieldType])) {
      foreach ($this->getDefinitions() as $definition) {
        if (in_array($fieldType, $definition['field_types'])) {
          $this->definitionsByFieldType[$fieldType] = $definition;
        }
      }
    }
    if (empty($this->definitionsByFieldType[$fieldType])) {
      return NULL;
    }
    return $this->createInstance($this->definitionsByFieldType[$fieldType]['id'], [
      'field_type' => $fieldType,
    ]);
  }

}

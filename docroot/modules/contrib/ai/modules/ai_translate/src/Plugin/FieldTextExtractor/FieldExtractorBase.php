<?php

declare(strict_types=1);

namespace Drupal\ai_translate\Plugin\FieldTextExtractor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\ai_translate\FieldTextExtractorInterface;
use Drupal\Core\Plugin\PluginBase;

/**
 * Base class for text extractor plugins.
 */
abstract class FieldExtractorBase extends PluginBase implements FieldTextExtractorInterface {

  /**
   * {@inheritdoc}
   */
  public function getColumns(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface $entity, string $fieldName): array {
    if ($entity->get($fieldName)->isEmpty() || empty($this->getColumns())) {
      return [];
    }
    $textMeta = [];
    foreach ($entity->get($fieldName) as $delta => $fieldItem) {
      $textMeta[] = ['delta' => $delta, '_columns' => $this->getColumns()] + $fieldItem->getValue();
    }
    return $textMeta;
  }

  /**
   * {@inheritDoc}
   */
  public function shouldExtract(ContentEntityInterface $entity, FieldConfigInterface $fieldDefinition): bool {
    return $fieldDefinition->isTranslatable();
  }

}

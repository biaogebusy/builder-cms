<?php

namespace Drupal\entity_theme_engine\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;

class FileItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ['Drupal\file\Plugin\Field\FieldType\FileItem'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, ?string $format = NULL, array $context = []): array {
    $data = parent::normalize($field, $format, $context);
    if ($field->entity) {
      // File URLs can change without saving the entity that references them.
      CacheableMetadata::createFromRenderArray($data)
        ->addCacheableDependency($field->entity)
        ->applyTo($data);
      $uri = $field->entity->getFileUri();
      $data['file_url'] = \Drupal::service('file_url_generator')
        ->generateString($uri);
    } else {
      \Drupal::logger('entity_theme_engine')->error("fileItem: {$field->getString()} not found.");
    }
    return $data;
  }
}

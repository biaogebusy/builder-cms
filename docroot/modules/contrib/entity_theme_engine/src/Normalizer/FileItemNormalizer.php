<?php

namespace Drupal\entity_theme_engine\Normalizer;


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
  public function normalize($field, $format = NULL, array $context = []) {
    $data = parent::normalize($field, $format, $context);
    if ($field->entity) {
      $uri = $field->entity->getFileUri();
      $data['file_url'] = \Drupal::service('file_url_generator')
        ->generateAbsoluteString($uri);
    } else {
      \Drupal::logger('entity_theme_engine')->error("fileItem: {$field->getString()} not found.");
    }
    return $data;
  }
}

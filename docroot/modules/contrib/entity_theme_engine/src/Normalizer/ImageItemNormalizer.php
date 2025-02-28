<?php

namespace Drupal\entity_theme_engine\Normalizer;


class ImageItemNormalizer extends FileItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ['Drupal\image\Plugin\Field\FieldType\ImageItem', 'Drupal\multiversion\ImageItem'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $data = parent::normalize($field, $format, $context);
    $data['styles'] = $this->getImageStylesVariables($field->entity);
    return $data;
  }
}

<?php

namespace Drupal\entity_theme_engine\Normalizer;


class ListItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ['Drupal\options\Plugin\Field\FieldType\ListItemBase'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, ?string $format = NULL, array $context = []): array {
    $data = parent::normalize($field, $format, $context);
    $data['options'] = $field->getPossibleOptions();
    return $data;
  }
}

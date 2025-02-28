<?php

namespace Drupal\replication\Normalizer;

/**
 * Normalizer for entity_reference_quantity field type.
 */
class EntityReferenceQuantityItemNormalizer extends EntityReferenceItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\entity_reference_quantity\Plugin\Field\FieldType\EntityReferenceQuantity';

  /**
   * Format.
   *
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $field_info = parent::normalize($field, $format, $context);

    $value = $field->getValue();
    $field_info['quantity'] = $value['quantity'];

    return $field_info;
  }

}

<?php

namespace Drupal\replication\Normalizer;

use Drupal\webform\Plugin\Field\FieldType\WebformEntityReferenceItem;

/**
 * Defines a class for normalizing WebformEntityReferenceItems.
 */
class WebformEntityReferenceItemNormalizer extends EntityReferenceItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = [WebformEntityReferenceItem::class];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    $value = parent::normalize($field, $format, $context);
    if (isset($value['open']) && empty($value['open'])) {
      unset($value['open']);
    }
    if (isset($value['close']) && empty($value['close'])) {
      unset($value['close']);
    }

    return $value;
  }

}

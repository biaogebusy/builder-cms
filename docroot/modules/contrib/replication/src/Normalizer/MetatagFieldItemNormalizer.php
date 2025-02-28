<?php

namespace Drupal\replication\Normalizer;

use Drupal\serialization\Normalizer\FieldItemNormalizer;


/**
 * Converts the Metatag field item object structure to METATAG array structure.
 */
class MetatagFieldItemNormalizer extends FieldItemNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = 'Drupal\metatag\Plugin\Field\FieldType\MetatagFieldItem';

  /**
   * {inheritDoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    return parent::normalize($object, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    if (in_array($type, ['Drupal\metatag\Plugin\Field\FieldType\MetatagFieldItem'])) {
      return TRUE;
    }
    return FALSE;
  }

}

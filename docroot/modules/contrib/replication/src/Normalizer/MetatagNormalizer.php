<?php

namespace Drupal\replication\Normalizer;

use Drupal\serialization\Normalizer\FieldNormalizer;

/**
 * Normalizes metatag into the viewed entity.
 */
class MetatagNormalizer extends FieldNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = 'Drupal\metatag\Plugin\Field\MetatagEntityFieldItemList';

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $normalized = [];
    if (\Drupal::hasService('metatag.normalizer.metatag')) {
      $normalized = \Drupal::service('metatag.normalizer.metatag')->normalize($field_item, $format, $context);
    }

    return [$normalized];
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    return parent::denormalize($data, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    if (in_array($type, ['Drupal\metatag\Plugin\Field\MetatagEntityFieldItemList'])) {
      return TRUE;
    }
    return FALSE;
  }

}

<?php

namespace Drupal\replication\Normalizer;

use Drupal\serialization\Normalizer\FieldNormalizer;

/**
 * Normalizes path field.
 */
class PathFieldItemListNormalizer extends FieldNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = [
    'Drupal\path\Plugin\Field\FieldType\PathFieldItemList',
    'Drupal\multiversion\MultiversionFieldItemList',
  ];

  /**
   * @var string[]
   */
  protected $format = ['json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    $value = $field_item->getValue();
    $entity = $field_item->getEntity();

    if (isset($entity->path->pathauto)) {
      $value[0]['pathauto'] = $entity->path->pathauto;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (isset($data[0]['pid'])) {
      unset($data[0]['pid']);
    }
    if (isset($data[0]['source'])) {
      unset($data[0]['source']);
    }
    if (isset($data[0]['workspace'])) {
      unset($data[0]['workspace']);
    }
    return parent::denormalize($data, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    if (in_array($type, ['Drupal\path\Plugin\Field\FieldType\PathFieldItemList', 'Drupal\multiversion\MultiversionFieldItemList'])) {
      return TRUE;
    }
    return FALSE;
  }

}

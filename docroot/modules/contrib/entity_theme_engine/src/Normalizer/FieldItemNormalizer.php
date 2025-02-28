<?php

namespace Drupal\entity_theme_engine\Normalizer;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Denormalizes field item object structure by updating the entity field values.
 */
class FieldItemNormalizer extends ContentEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = FieldItemInterface::class;
  
  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $cache = [
      'contexts' => [],
      'tags' => [],
      'max-age' => -1,
    ];
    $data = $object->getValue();
    foreach ($data as $key => $value) {
      if($key[0] == '_'){
        unset($data[$key]);
      }
    }
    $data['#cache'] = $cache;
    return $data;
  }
}

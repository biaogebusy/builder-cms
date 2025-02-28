<?php

namespace Drupal\entity_theme_engine\Normalizer;

class LinkItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\link\LinkItemInterface';


  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $data = parent::normalize($object, $format, $context);
    $data['title'] = $object->title;
    $data['url'] = $object->getUrl()->toString();
    return $data;
  }

}

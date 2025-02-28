<?php

namespace Drupal\replication\Normalizer;

use Drupal\taxonomy\TermInterface;

class TaxonomyTermNormalizer extends ContentEntityNormalizer {

  /**
   * @var string[]
   */
  protected $supportedInterfaceOrClass = ['\Drupal\taxonomy\Entity\Term'];

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    if (!isset($entity->parent->target_id)) {
      $parents = $storage->loadParents($entity->id());
      $parent = reset($parents);
      if ($parent instanceof TermInterface) {
        $entity->parent->target_id = $parent->id();
      }
      else {
        $entity->parent->target_id = 0;
      }
    }

    return parent::normalize($entity, $format, $context);
  }

}

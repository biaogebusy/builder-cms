<?php

namespace Drupal\entity\EventSubscriber;

use Drupal\entity\BundleEntityDuplicatorInterface;
use Drupal\entity\Event\EntityDuplicateEvent;
use Drupal\entity\Event\EntityEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EntityDuplicateSubscriber implements EventSubscriberInterface {

  /**
   * The bundle entity duplicator.
   *
   * @var \Drupal\entity\BundleEntityDuplicatorInterface
   */
  protected $bundleEntityDuplicator;

  /**
   * Constructs a new EntityDuplicateSubscriber object.
   *
   * @param \Drupal\entity\BundleEntityDuplicatorInterface $bundle_entity_duplicator
   *   The bundle entity duplicator.
   */
  public function __construct(BundleEntityDuplicatorInterface $bundle_entity_duplicator) {
    $this->bundleEntityDuplicator = $bundle_entity_duplicator;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      EntityEvents::ENTITY_DUPLICATE => ['onEntityDuplicate'],
    ];
    return $events;
  }

  /**
   * Duplicates bundle fields and displays.
   *
   * @param \Drupal\entity\Event\EntityDuplicateEvent $event
   *   The entity duplicate event.
   */
  public function onEntityDuplicate(EntityDuplicateEvent $event) {
    $entity = $event->getEntity();
    if ($entity->getEntityType()->getBundleOf()) {
      /** @var \Drupal\Core\Config\Entity\ConfigEntityInterface $source_entity */
      $source_entity = $event->getSourceEntity();
      $this->bundleEntityDuplicator->duplicateFields($source_entity, $entity->id());
      $this->bundleEntityDuplicator->duplicateDisplays($source_entity, $entity->id());
    }
  }

}

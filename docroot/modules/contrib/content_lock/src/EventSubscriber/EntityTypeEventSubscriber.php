<?php

namespace Drupal\content_lock\EventSubscriber;

use Drupal\content_lock\Hook\ContentLockHooks;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Entity\EntityTypeEvent;
use Drupal\Core\Entity\EntityTypeEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class acts on config save event.
 */
class EntityTypeEventSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ConfigInstallerInterface $configInstaller,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [EntityTypeEvents::DELETE => 'onEntityTypeDelete'];
  }

  /**
   * Removes entity type from content_lock config on entity type delete.
   *
   * @param \Drupal\Core\Entity\EntityTypeEvent $event
   *   The entity type event.
   */
  public function onEntityTypeDelete(EntityTypeEvent $event): void {
    if ($this->configInstaller->isSyncing()) {
      return;
    }
    $entity_type = $event->getEntityType();
    $config = $this->configFactory->getEditable("content_lock.settings");
    if (array_key_exists($entity_type->id(), $config->get("types") ?? [])) {
      ContentLockHooks::removeEntityTypeFromConfig($entity_type->id(), $config)->save();
    }
  }

}

<?php

namespace Drupal\replication_alter_test\Event;


use Drupal\replication\Event\ReplicationContentDataAlterEvent;
use Drupal\replication\Event\ReplicationDataEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to test altering data during normalization.
 */
class ContentEntityTestAlterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[ReplicationDataEvents::ALTER_CONTENT_DATA][] = ['onAlterContentData', 0];
    return $events;
  }

  /**
   * Alter content normalization data.
   *
   * @param ReplicationContentDataAlterEvent $event
   */
  public function onAlterContentData(ReplicationContentDataAlterEvent $event) {
    // Add some data under a '_test' key.
    $normalized = $event->getData();
    $normalized['_test'] = ['foo' => 'bar'];
    $event->setData($normalized);
  }

}

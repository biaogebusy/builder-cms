<?php

namespace Drupal\elasticsearch_connector_test\EventSubscriber;

use Drupal\elasticsearch_connector\Event\IndexCreatedEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\search_api\IndexInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * ElasticSearch test event subscribers.
 */
class ElasticSearchEventSubscribers implements EventSubscriberInterface {

  /**
   * The index that was created.
   */
  private static IndexInterface $index;

  /**
   * Event handler for when an index is created.
   */
  public function onIndexCreated(IndexCreatedEvent $event): void {
    self::$index = $event->getIndex();
  }

  /**
   * Event handler for when something is indexed.
   */
  public function onIndexParams(IndexParamsEvent $event): void {
    $params = $event->getParams();

    // Waiting for data to be indexed.
    $params['refresh'] = 'wait_for';
    $event->setParams($params);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      IndexCreatedEvent::class => ['onIndexCreated'],
      IndexParamsEvent::class => ['onIndexParams'],
    ];
  }

  /**
   * Get the index that was created.
   */
  public static function getIndex(): IndexInterface {
    return self::$index;
  }

}

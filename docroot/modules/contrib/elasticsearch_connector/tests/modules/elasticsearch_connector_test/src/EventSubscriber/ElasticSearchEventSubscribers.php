<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector_test\EventSubscriber;

use Drupal\elasticsearch_connector\Event\IndexCreatedEvent;
use Drupal\elasticsearch_connector\Event\IndexParamsEvent;
use Drupal\elasticsearch_connector\Event\IndexPreCreateEvent;
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
   * The index array, containing all settings for creating the index.
   */
  private static array $params;

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
   * Event handler called before an index is created.
   */
  public function onIndexPreCreate(IndexPreCreateEvent $event): void {
    $params = $event->getParams();
    $params['settings']['max_ngram_diff'] = 25;
    $event->setParams($params);
    self::$params = $event->getParams();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      IndexCreatedEvent::class => ['onIndexCreated'],
      IndexParamsEvent::class => ['onIndexParams'],
      IndexPreCreateEvent::class => ['onIndexPreCreate'],
    ];
  }

  /**
   * Get the index that was created.
   */
  public static function getIndex(): IndexInterface {
    return self::$index;
  }

  /**
   * Get the index settings for creating the index.
   */
  public static function getParams(): array {
    return self::$params;
  }

}

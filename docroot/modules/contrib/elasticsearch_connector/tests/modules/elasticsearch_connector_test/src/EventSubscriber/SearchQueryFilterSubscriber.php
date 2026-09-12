<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector_test\EventSubscriber;

use Drupal\elasticsearch_connector\Event\AlterSearchQueryFiltersEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// cspell:ignore ~pomme

/**
 * Alter a search query's filters before a search query is built.
 *
 * @see \Drupal\elasticsearch_connector\Event\AlterSearchQueryFiltersEvent
 * @see \Drupal\Tests\elasticsearch_connector\Functional\AlterSearchQueryFiltersEventTest
 */
class SearchQueryFilterSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      AlterSearchQueryFiltersEvent::class => 'onSearchFilter',
    ];
  }

  /**
   * Alter a search query's filters before a search query is built.
   *
   * @param \Drupal\elasticsearch_connector\Event\AlterSearchQueryFiltersEvent $event
   *   An event allowing search query filters to be altered.
   */
  public function onSearchFilter(AlterSearchQueryFiltersEvent $event): void {
    // Get the parameters we want to modify from the event.
    $params = $event->getParams();

    // If we filter by the word 'Pomme' (i.e.: the French word for apple), then
    // substitute filtering by the word 'apple' instead. Note this intentionally
    // only matches when the first letter is capitalized: see
    // \Drupal\Tests\elasticsearch_connector\Functional\SearchFilterParamsEventTest.
    // Note this test assumes the term 'pomme' will never exist in the test
    // data.
    if ($event->getIndexName() === 'test_elasticsearch_index'
      && isset($params['post_filters']['term']['keywords'])
      && $params['post_filters']['term']['keywords'] === 'Pomme'
      && isset($params['facets_post_filters']['keywords']['terms']['keywords'][0])
      && $params['facets_post_filters']['keywords']['terms']['keywords'][0] === 'Pomme'
    ) {
      $params['post_filters']['term']['keywords'] = 'apple';
      $params['facets_post_filters']['keywords']['terms']['keywords'][0] = 'apple';
    }

    // Re-set the parameters in the event to our modified version.
    $event->setParams($params);
  }

}

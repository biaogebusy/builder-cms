<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector_test\EventSubscriber;

use Drupal\elasticsearch_connector\Event\AlterSearchQueryParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// cspell:ignore Supercalifragilisticexpialidocious smileὠ

/**
 * Alter search query parameters before the query is run.
 *
 * @see \Drupal\elasticsearch_connector\Event\AlterSearchQueryParamsEvent
 * @see \Drupal\Tests\elasticsearch_connector\Functional\AlterSearchQueryParamsEventTest
 */
class SearchQueryEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AlterSearchQueryParamsEvent::class => 'onSearch',
    ];
  }

  /**
   * Alter search query parameters before the query is run.
   *
   * @param \Drupal\elasticsearch_connector\Event\AlterSearchQueryParamsEvent $event
   *   An event altering search query parameters before the query is run.
   */
  public function onSearch(AlterSearchQueryParamsEvent $event): void {
    // Get the parameters we want to modify from the event.
    $params = $event->getParams();

    // If we are on the test search page, and the user entered the string
    // 'Supercalifragilisticexpialidocious', then substitute the search string
    // 'smileὠ1' instead. Note this intentionally only matches when the first
    // letter is capitalized: see
    // \Drupal\Tests\elasticsearch_connector\Functional\AlterSearchQueryParamsEventTest.
    // Note this test assumes the term 'supercalifragilisticexpialidocious' will
    // never exist in the test data.
    if ($event->getQuery()->getSearchId() === 'views_page:test_elasticsearch_index_search__page_1'
      && isset($params['body']['query']['query_string']['query'])
      && $params['body']['query']['query_string']['query'] === 'Supercalifragilisticexpialidocious'
    ) {
      $params['body']['query']['query_string']['query'] = 'smileὠ1';
    }

    // Re-set the parameters in the event to our modified version.
    $event->setParams($params);
  }

}

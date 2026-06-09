<?php

namespace Drupal\ai_search\EventSubscriber;

use Drupal\ai_search\Plugin\search_api\processor\SolrBoostByAiSearch;
use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to alter the Solarium query for AI boosting.
 */
class SolrBoostByAiSearchEventSubscriber implements EventSubscriberInterface {

  /**
   * Alters the Solarium query to boost AI results.
   *
   * @param \Drupal\ai_search\Event\PostConvertedQueryEvent $event
   *   The event containing the Solarium query.
   */
  public function onPostConvertedQuery(PostConvertedQueryEvent $event) {
    $solarium_query = $event->getSolariumQuery();
    $query = $event->getSearchApiQuery();
    SolrBoostByAiSearch::queryAlter($solarium_query, $query);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostConvertedQueryEvent::class => 'onPostConvertedQuery',
    ];
  }

}

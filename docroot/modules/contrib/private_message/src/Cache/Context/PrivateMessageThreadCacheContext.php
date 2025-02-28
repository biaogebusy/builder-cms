<?php

namespace Drupal\private_message\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;

/**
 * Defines the PrivateMessageThread service, for "per thread" caching.
 *
 * Cache context ID: 'private_message_thread'.
 */
class PrivateMessageThreadCacheContext implements CacheContextInterface {

  /**
   * The current route matcher.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $currentRouteMatcher;

  /**
   * Constructs a new UserCacheContextBase class.
   *
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $currentRouteMatcher
   *   The current route matcher.
   */
  public function __construct(ResettableStackedRouteMatchInterface $currentRouteMatcher) {
    $this->currentRouteMatcher = $currentRouteMatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Private Message Thread');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $thread = $this->currentRouteMatcher->getParameter('private_message_thread');
    if ($thread) {
      return $thread->id();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}

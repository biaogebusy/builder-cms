<?php

declare(strict_types=1);

namespace Drupal\private_message\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Defines the PrivateMessageThread service, for "per thread" caching.
 *
 * Cache context ID: 'private_message_thread'.
 */
class PrivateMessageThreadCacheContext implements CacheContextInterface {

  public function __construct(
    protected readonly RouteMatchInterface $currentRouteMatch,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Private Message Thread');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    $thread = $this->currentRouteMatch->getParameter('private_message_thread');
    if ($thread) {
      return (string) $thread->id();
    }
    return 'none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return new CacheableMetadata();
  }

}

<?php

namespace Drupal\consumers;

use Drupal\consumers\Entity\ConsumerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * An interface for the consumer negotiator.
 */
interface NegotiatorInterface {

  /**
   * Obtains the consumer from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request object to inspect for a consumer. Set to NULL to use the
   *   current request.
   *
   * @return \Drupal\consumers\Entity\ConsumerInterface|null
   *   The consumer.
   *
   * @throws \Drupal\consumers\MissingConsumer
   */
  public function negotiateFromRequest(?Request $request = NULL): ?ConsumerInterface;

  /**
   * Obtains the consumer client ID from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request object to inspect for a consumer. Set to NULL to use the
   *   current request.
   *
   * @return string
   *   The consumer client ID.
   *
   * @throws \Drupal\consumers\MissingConsumer
   */
  public function negotiateClientIdFromRequest(?Request $request = NULL): string;

}

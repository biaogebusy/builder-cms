<?php

declare(strict_types=1);

namespace Drupal\simple_oauth;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\user\UserInterface;

/**
 * Responds to updates of external data to handle token expiry.
 *
 * Methods may be added to this interface as part of minor releases.
 */
interface TokenExpiryTriggerHandlerInterface {

  /**
   * Handle expiry of tokens that may have timed out.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function handleCron(): void;

  /**
   * Handle invalidation of tokens due to a user update.
   *
   * @param \Drupal\user\UserInterface $user
   *   The updated user.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function handleUserUpdate(UserInterface $user): void;

  /**
   * Handle invalidation of tokens due to a consumer update.
   *
   * @param \Drupal\consumers\Entity\ConsumerInterface $consumer
   *   The consumer that was updated.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function handleConsumerUpdate(ConsumerInterface $consumer): void;

}

<?php

declare(strict_types=1);

namespace Drupal\simple_oauth;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * Responds to updates of external data to handle token expiry.
 *
 * Invalidates tokens related to a user (or a consumer acting on behalf of that
 * user) when that user is updated. Also invalidates all tokens for a consumer
 * in case the configuration for that consumer is changed.
 */
class TokenExpiryTriggerHandler implements TokenExpiryTriggerHandlerInterface {

  /**
   * Create a new handler instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal config factory.
   * @param \Drupal\simple_oauth\ExpiredCollector $collector
   *   The token collector that can find tokens related to users or consumers.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger to be used for this service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ExpiredCollector $collector,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handleCron(): void {
    $token_cron_batch_size = $this->configFactory->get('simple_oauth.settings')->get('token_cron_batch_size') ?? 0;
    // Deleting one batch of expired tokens.
    if (!empty($expired_tokens = $this->collector->collect($token_cron_batch_size))) {
      $this->collector->deleteMultipleTokens($expired_tokens);
      $this->logger->notice('Deleted @limit expired tokens in cron.', [
        '@limit' => $token_cron_batch_size,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleUserUpdate(UserInterface $user): void {
    $this->collector->deleteMultipleTokens($this->collector->collectForAccount($user));
  }

  /**
   * {@inheritdoc}
   */
  public function handleConsumerUpdate(ConsumerInterface $consumer): void {
    $this->collector->deleteMultipleTokens($this->collector->collectForClient($consumer));
  }

}

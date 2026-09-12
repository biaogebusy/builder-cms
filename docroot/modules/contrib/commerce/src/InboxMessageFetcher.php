<?php

namespace Drupal\commerce;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use Drupal\commerce\Utility\Error;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Provides the InboxMessageFetcher service.
 */
class InboxMessageFetcher implements InboxMessageFetcherInterface {

  /**
   * Base feed url.
   */
  const BASE_FEED_URL = 'https://www.centarro.io';

  // Specifies the cron run interval (once a day).
  const CRON_INTERVAL = 86400;

  /**
   * Constructs a new InboxMessageFetcher object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   * @param \Closure $logger
   *   The logger.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\commerce\InboxMessageStorageInterface $inboxMessageStorage
   *   The Commerce inbox message storage.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected ClientInterface $httpClient,
    // Prevent circular reference:
    #[AutowireServiceClosure('commerce.logger')]
    protected \Closure $logger,
    protected ModuleHandlerInterface $moduleHandler,
    protected InboxMessageStorageInterface $inboxMessageStorage,
    protected StateInterface $state,
    protected TimeInterface $time,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): void {
    if (!Settings::get('commerce_dashboard_fetch_inbox_messages', TRUE)) {
      return;
    }

    $request_time = $this->time->getRequestTime();
    $fetch_next = $this->state->get('commerce.inbox_messages_cron_last', 0) + static::CRON_INTERVAL;
    if ($request_time < $fetch_next) {
      return;
    }

    try {
      $this->state->set('commerce.inbox_messages_cron_last', $request_time);
      $response = $this->httpClient->get(self::BASE_FEED_URL . '/drupal-commerce/messages.json', [
        'timeout' => 10,
      ]);
      $messages = Json::decode($response->getBody()->getContents());
      if (is_array($messages) && !empty($messages)) {
        $this->storeMessages($messages);
      }
    }
    catch (\Exception $exception) {
      /** @var \Psr\Log\LoggerInterface $logger */
      $logger = ($this->logger)();
      Error::logException($logger, $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function fetchNewStoreMessages(): void {
    if (!Settings::get('commerce_dashboard_fetch_inbox_messages', TRUE)) {
      return;
    }
    try {
      $response = $this->httpClient->get(self::BASE_FEED_URL . '/drupal-commerce/new-store-messages.json', [
        'timeout' => 10,
      ]);
      $messages = Json::decode($response->getBody()->getContents());
      if (is_array($messages) && !empty($messages)) {
        foreach ($messages as $key => $message) {
          $messages[$key]['send_date'] = $this->time->getRequestTime();
        }
        $this->storeMessages($messages);
      }
    }
    catch (\Exception $exception) {
      /** @var \Psr\Log\LoggerInterface $logger */
      $logger = ($this->logger)();
      Error::logException($logger, $exception);
    }
  }

  /**
   * Store the given messages.
   *
   * @param array $messages
   *   The messages to store.
   */
  protected function storeMessages(array $messages): void {
    foreach ($messages as $message) {
      $dependencies = $message['dependencies'] ?? [];
      $dependencies_satisfied = empty($dependencies);
      foreach ($message['dependencies'] as $dependency) {
        if ($this->moduleHandler->moduleExists($dependency)) {
          $dependencies_satisfied = TRUE;
          break;
        }
      }
      if (!$dependencies_satisfied) {
        continue;
      }
      $message['state'] = 'unread';
      $inbox_message = InboxMessage::fromArray($message);
      $this->inboxMessageStorage->save($inbox_message);
    }
  }

}

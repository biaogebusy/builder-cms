<?php

namespace Drupal\ai_test\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The event to log mock requests.
 *
 * @package Drupal\ai_logging\EventSubscriber
 */
class LogMockRequests implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The AI test provider settings.
   *
   * @var ImmutableConfig
   */
  protected $aiSettings;

  /**
   * Log the data of the requests.
   *
   * @var array
   */
  protected $collectedData = [];

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aiSettings = $configFactory->get('ai_test.settings');
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   The post generate response event.
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'logPreRequest',
      PostGenerateResponseEvent::EVENT_NAME => 'logPostRequest',
      PostStreamingResponseEvent::EVENT_NAME => 'logPostStream',
    ];
  }

  /**
   * Starts the timer of the event.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to log.
   */
  public function logPreRequest(PreGenerateResponseEvent $event) {
    // If the provider is echoai, we do not log the request.
    if (!$this->mockCollectionEnabled() || $event->getProviderId() === 'echoai') {
      return;
    }
    // Set the start time for the request.
    $this->collectedData[$event->getRequestThreadId()]['start_time'] = microtime(TRUE);
    $this->collectedData[$event->getRequestThreadId()]['request'] = $event->getInput();
  }

  /**
   * Log if needed after running an AI request.
   *
   * @param \Drupal\ai\Event\PostGenerateResponseEvent $event
   *   The event to log.
   */
  public function logPostRequest(PostGenerateResponseEvent $event) {
    // If the provider is echoai, we do not log the request.
    if (!$this->mockCollectionEnabled() || $event->getProviderId() === 'echoai') {
      return;
    }
    // If the output is a streaming object, we do not log it here.
    if (empty($event->getOutput())) {
      return;
    }
    // Check if we should log the time.
    $time = 0;
    if ($this->aiSettings->get('catch_processing_time')) {
      $time = microtime(TRUE) - $this->collectedData[$event->getRequestThreadId()]['start_time'];
    }
    // Create the mock provider result entity.
    $storage = $this->entityTypeManager->getStorage('ai_mock_provider_result');
    // Only store if the input and output are actual objects.
    if (!($event->getInput() instanceof InputInterface) || !($event->getOutput() instanceof OutputInterface)) {
      return;
    }
    $result = $storage->create([
      'label' => 'Unnamed',
      'request' => Yaml::dump($event->getInput()->toArray(), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
      'response' => Yaml::dump($event->getOutput()->toArray(), 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK),
      'sleep_time' => $time * 1000,
      'operation_type' => $event->getOperationType(),
      'mock_enabled' => FALSE,
      'tags' => $event->getTags(),
    ]);
    // Save the result.
    $result->save();
  }

  /**
   * If the log was a streaming object, we need to update with the response.
   *
   * @param \Drupal\ai\Event\PostStreamingResponseEvent $event
   *   The event to log.
   */
  public function logPostStream(PostStreamingResponseEvent $event) {
    // If the provider is echoai, we do not log the request.
    if (!$this->mockCollectionEnabled()) {
      return;
    }
  }

  /**
   * Checks if logging should happen.
   *
   * @return bool
   *   TRUE if logging should happen, FALSE otherwise.
   */
  protected function mockCollectionEnabled(): bool {
    return $this->aiSettings->get('catch_results') ?? FALSE;
  }

}

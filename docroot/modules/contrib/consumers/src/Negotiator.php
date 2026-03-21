<?php

namespace Drupal\consumers;

use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Extracts the consumer information from the given context.
 *
 * @internal
 */
class Negotiator implements NegotiatorInterface {

  /**
   * The default consumer.
   *
   * @var \Drupal\consumers\Entity\ConsumerInterface|null
   */
  protected ?ConsumerInterface $defaultConsumer;

  /**
   * Constructs a consumer negotiator instance.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    protected RequestStack $requestStack,
    protected LoggerInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * Obtains the consumer from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\consumers\Entity\ConsumerInterface|null
   *   The consumer.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\consumers\MissingConsumer
   */
  protected function doNegotiateFromRequest(Request $request): ?ConsumerInterface {
    // There are several ways to negotiate the consumer:
    // 1. Via a custom header.
    $consumer_id = $request->headers->get('X-Consumer-ID');
    if (!$consumer_id) {
      // 2. Via a query string parameter.
      $consumer_id = $request->query->get('consumerId');
      if (!$consumer_id && $request->query->has('_consumer_id')) {
        $this->logger()->warning('The "_consumer_id" query string parameter is deprecated and it will be removed in the next major version of the module, please use "consumerId" instead.');
        $consumer_id = $request->query->get('_consumer_id');
      }
    }
    if ($consumer_id) {
      try {
        $results = $this->entityTypeManager->getStorage('consumer')->loadByProperties(['client_id' => $consumer_id]);
        /** @var \Drupal\consumers\Entity\ConsumerInterface $consumer */
        $consumer = !empty($results) ? reset($results) : $results;
      }
      catch (EntityStorageException $exception) {
        Error::logException($this->logger, $exception);
      }
    }
    if (empty($consumer)) {
      $consumer = $this->loadDefaultConsumer();
    }
    return $consumer;
  }

  /**
   * Obtains the client ID from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The consumer client ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\consumers\MissingConsumer
   */
  protected function doNegotiateClientIdFromRequest(Request $request): string {
    // There are several ways to negotiate the consumer:
    // 1. Via a custom header.
    $consumer_id = $request->headers->get('X-Consumer-ID');
    if (!$consumer_id) {
      // 2. Via a query string parameter.
      $consumer_id = $request->query->get('consumerId');
      if (!$consumer_id && $request->query->has('_consumer_id')) {
        $this->logger()->warning('The "_consumer_id" query string parameter is deprecated and it will be removed in the next major version of the module, please use "consumerId" instead.');
        $consumer_id = $request->query->get('_consumer_id');
      }
    }
    if ($consumer_id) {
      // Check the client ID exists.
      $row_count = $this->entityTypeManager->getStorage('consumer')->getQuery()
        ->accessCheck(TRUE)
        ->condition('client_id', $consumer_id)
        ->count()
        ->execute();
      if ($row_count > 0) {
        return $consumer_id;
      }
    }

    return $this->getDefaultClientId();
  }

  /**
   * Gets the client ID from the default consumer.
   *
   * @return string
   *   The default client ID.
   *
   * @throws \Drupal\consumers\MissingConsumer
   */
  private function getDefaultClientId(): string {
    $cache_data = $this->cache->get('consumers:default_client_id');
    if ($cache_data === FALSE) {
      $consumer = $this->loadDefaultConsumer();
      $client_id = $consumer->getClientId();
      $this->cache->set('consumers:default_client_id', $client_id, CacheBackendInterface::CACHE_PERMANENT, $consumer->getCacheTags());
    }
    else {
      $client_id = $cache_data->data;
    }
    return $client_id;
  }

  /**
   * {@inheritdoc}
   */
  public function negotiateFromRequest(?Request $request = NULL): ?ConsumerInterface {
    // If the request is not provided, use the request from the stack.
    $request = $request ? $request : $this->requestStack->getCurrentRequest();
    $consumer = $this->doNegotiateFromRequest($request);
    $request->attributes->set('consumer_id', $consumer->getClientId());
    return $consumer;
  }

  /**
   * {@inheritdoc}
   */
  public function negotiateClientIdFromRequest(?Request $request = NULL): string {
    // If the request is not provided, use the request from the stack.
    $request = $request ? $request : $this->requestStack->getCurrentRequest();
    $client_id = $this->doNegotiateClientIdFromRequest($request);
    $request->attributes->set('consumer_id', $client_id);
    return $client_id;
  }

  /**
   * Finds and loads the default consumer.
   *
   * @return \Drupal\consumers\Entity\ConsumerInterface|null
   *   The consumer entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\consumers\MissingConsumer
   */
  protected function loadDefaultConsumer(): ?ConsumerInterface {
    if (!empty($this->defaultConsumer)) {
      return $this->defaultConsumer;
    }

    $storage = $this->entityTypeManager->getStorage('consumer');
    // Find the default consumer.
    $results = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('is_default', TRUE)
      ->execute();
    $consumer_id = reset($results);
    if (!$consumer_id) {
      // Throw if there is no default consumer.
      throw new MissingConsumer('Unable to find the default consumer.');
    }
    $this->defaultConsumer = $storage->load($consumer_id);

    return $this->defaultConsumer;
  }

  /**
   * Gets the logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger.
   */
  private function logger(): LoggerInterface {
    return ($this->logger)();
  }

}

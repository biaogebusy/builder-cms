<?php

namespace Drupal\core_context\ContextProvider;

use Drupal\Core\Plugin\Context\ContextProviderInterface;

/**
 * Exposes contexts from various provider services, under a single namespace.
 */
final class Generic implements ContextProviderInterface {

  /**
   * The provider services from which to expose contexts.
   *
   * @var \Drupal\Core\Plugin\Context\ContextProviderInterface[]
   */
  private $providers = [];

  /**
   * Adds a provider service from which to expose contexts.
   *
   * @param \Drupal\Core\Plugin\Context\ContextProviderInterface $provider
   *   The context provider to add.
   */
  public function addProvider(ContextProviderInterface $provider) {
    array_push($this->providers, $provider);
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $contexts = [];
    foreach ($this->providers as $provider) {
      $contexts = array_merge($contexts, $provider->getRuntimeContexts($unqualified_context_ids));
    }
    return $contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $contexts = [];
    foreach ($this->providers as $provider) {
      $contexts = array_merge($contexts, $provider->getAvailableContexts());
    }
    return $contexts;
  }

}

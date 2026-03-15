<?php

namespace Drupal\social_auth\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Drupal\social_auth\Plugin\Network\NetworkInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides upcasting for a network instance.
 *
 * @package Drupal\social_auth\ParamConverter
 */
class NetworkConverter implements ParamConverterInterface {

  /**
   * Network manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  protected NetworkManager $networkManager;

  /**
   * Constructs a NetworkConverter instance.
   *
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   Network manager.
   */
  public function __construct(NetworkManager $network_manager) {
    $this->networkManager = $network_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults): ?NetworkInterface {
    $plugin_id = "social_auth_$value";
    if ($this->networkManager->hasDefinition($plugin_id)) {
      return $this->networkManager->createInstance("social_auth_$value");
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return !empty($definition['type']) && $definition['type'] === 'network';
  }

}

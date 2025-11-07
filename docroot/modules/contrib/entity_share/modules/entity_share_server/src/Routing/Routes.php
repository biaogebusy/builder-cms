<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Routing;

use Drupal\Core\Authentication\AuthenticationCollectorInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\entity_share_server\Entity\ChannelInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * @internal
 */
class Routes implements ContainerInjectionInterface {

  /**
   * The authentication collector.
   *
   * @var \Drupal\Core\Authentication\AuthenticationCollectorInterface
   */
  protected $authCollector;

  /**
   * List of providers.
   *
   * @var string[]
   */
  protected $providerIds;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\Core\Authentication\AuthenticationCollectorInterface $auth_collector
   *   The authentication provider collector.
   */
  public function __construct(AuthenticationCollectorInterface $auth_collector) {
    $this->authCollector = $auth_collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Authentication\AuthenticationCollectorInterface $auth_collector */
    $auth_collector = $container->get('authentication_collector');

    return new static($auth_collector);
  }

  /**
   * Provides the entry point route.
   */
  public function entryPoint() {
    $collection = new RouteCollection();

    $route_collection = (new Route('/entity_share', [
      RouteObjectInterface::CONTROLLER_NAME => '\Drupal\entity_share_server\Controller\EntryPoint::index',
    ]))
      ->setRequirement('_permission', ChannelInterface::CHANNELS_ACCESS_PERMISSION)
      ->setMethods(['GET']);
    $route_collection->addOptions([
      '_auth' => $this->authProviderList(),
    ]);
    $collection->add('entity_share_server.resource_list', $route_collection);

    return $collection;
  }

  /**
   * Build a list of authentication provider ids.
   *
   * @return string[]
   *   The list of IDs.
   */
  protected function authProviderList() {
    if (isset($this->providerIds)) {
      return $this->providerIds;
    }
    $this->providerIds = array_keys($this->authCollector->getSortedProviders());

    return $this->providerIds;
  }

}

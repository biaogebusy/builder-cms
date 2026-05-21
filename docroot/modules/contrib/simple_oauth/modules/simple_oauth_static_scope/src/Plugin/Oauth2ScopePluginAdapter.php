<?php

namespace Drupal\simple_oauth_static_scope\Plugin;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\simple_oauth\Oauth2ScopeAdapterInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adapter class for the OAuth2 scope plugin.
 */
class Oauth2ScopePluginAdapter implements Oauth2ScopeAdapterInterface, ContainerInjectionInterface {

  /**
   * The scope plugin manager.
   *
   * @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface
   */
  protected Oauth2ScopeManagerInterface $scopeManager;

  /**
   * Oauth2ScopePluginAdapter constructor.
   *
   * @param \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface $oauth2_scope_manager
   *   The scope plugin manager.
   */
  public function __construct(Oauth2ScopeManagerInterface $oauth2_scope_manager) {
    $this->scopeManager = $oauth2_scope_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.oauth2_scope')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id) {
    try {
      return $this->scopeManager->getInstance(['id' => $id]);
    }
    catch (PluginNotFoundException $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL): array {
    return $this->scopeManager->getInstances($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByName(string $name): ?Oauth2ScopeInterface {
    return $this->load($name);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleByNames(array $names): array {
    return $this->loadMultiple($names);
  }

  /**
   * {@inheritdoc}
   */
  public function loadChildren(string $parent_id): array {
    try {
      return $this->scopeManager->getChildrenInstances($parent_id);
    }
    catch (PluginNotFoundException $e) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadByGrantType(string $grant_type): array {
    $scopes = $this->loadMultiple();
    return array_filter($scopes, function (Oauth2ScopeInterface $scope) use ($grant_type) {
      return $scope->isGrantTypeEnabled($grant_type);
    });
  }

}

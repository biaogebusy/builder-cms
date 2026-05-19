<?php

namespace Drupal\simple_oauth;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Plugin\ScopeProviderManagerInterface;

/**
 * OAuth2 scope provider factory.
 */
class Oauth2ScopeProviderFactory implements Oauth2ScopeProviderFactoryInterface {

  /**
   * The simple_oauth settings config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * The scope provider plugin manager.
   *
   * @var \Drupal\simple_oauth\Plugin\ScopeProviderManagerInterface
   */
  protected ScopeProviderManagerInterface $scopeProviderManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs Oauth2ScopeProvider.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\simple_oauth\Plugin\ScopeProviderManagerInterface $scope_provider_manager
   *   The scope provider plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ScopeProviderManagerInterface $scope_provider_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->config = $config_factory->get('simple_oauth.settings');
    $this->scopeProviderManager = $scope_provider_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function get(): Oauth2ScopeAdapterInterface {
    $plugin_id = $this->config->get('scope_provider') ?? 'dynamic';
    /** @var \Drupal\simple_oauth\Plugin\ScopeProviderInterface $plugin */
    $plugin = $this->scopeProviderManager->getInstance(['id' => $plugin_id]);
    $adapter = $plugin->getScopeProviderAdapter();

    return new Oauth2ScopeProvider($adapter, $this->entityTypeManager);
  }

}

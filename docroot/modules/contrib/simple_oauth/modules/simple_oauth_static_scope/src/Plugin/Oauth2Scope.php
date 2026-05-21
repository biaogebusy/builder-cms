<?php

namespace Drupal\simple_oauth_static_scope\Plugin;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\simple_oauth\Plugin\ScopeGranularityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default object used for OAuth2 Scope plugins.
 *
 * @see \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManager
 */
class Oauth2Scope extends PluginBase implements Oauth2ScopePluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $pluginId,
    array $pluginDefinition,
    protected PluginManagerInterface $granularityManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('plugin.manager.scope_granularity'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getName(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantTypes(): array {
    return $this->pluginDefinition['grant_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGrantTypeDescription(string $grant_type): ?string {
    $grant_types = $this->getGrantTypes();
    return $grant_types[$grant_type]['description'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isGrantTypeEnabled(string $grant_type): bool {
    return array_key_exists($grant_type, $this->getGrantTypes());
  }

  /**
   * {@inheritdoc}
   */
  public function isUmbrella(): bool {
    return $this->pluginDefinition['umbrella'];
  }

  /**
   * {@inheritdoc}
   */
  public function getParent(): ?string {
    $parent = $this->pluginDefinition['parent'] ?? NULL;
    return !$this->isUmbrella() ? $parent : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getGranularity(): ?ScopeGranularityInterface {
    if ($this->isUmbrella()) {
      return NULL;
    }

    return $this->granularityManager->createInstance(
      $this->pluginDefinition['granularity_id'],
      $this->pluginDefinition['granularity_configuration'],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    $granularity = $this->getGranularity();
    assert($granularity instanceof ScopeGranularityInterface);
    return $granularity->getPermissions();
  }

}

<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\simple_oauth\Oauth2ScopeAdapterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Scope Provider plugins.
 */
abstract class ScopeProviderBase extends PluginBase implements ScopeProviderInterface, ContainerFactoryPluginInterface {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected ClassResolverInterface $classResolver;

  /**
   * Oauth2GrantBase constructor.
   *
   * @param array $configuration
   *   The plugin configuration array.
   * @param string $pluginId
   *   The plugin id.
   * @param array $pluginDefinition
   *   The plugin definition array.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(array $configuration, $pluginId, array $pluginDefinition, ClassResolverInterface $class_resolver) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('class_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getScopeProviderAdapter(): Oauth2ScopeAdapterInterface {
    $adapter = $this->classResolver->getInstanceFromDefinition($this->pluginDefinition['adapter_class']);

    if (!$adapter instanceof Oauth2ScopeAdapterInterface) {
      throw new PluginException(sprintf('The plugin "%s" did not specify a valid class, must implement "%s".', $this->getPluginId(), Oauth2ScopeAdapterInterface::class));
    }

    return $adapter;
  }

}

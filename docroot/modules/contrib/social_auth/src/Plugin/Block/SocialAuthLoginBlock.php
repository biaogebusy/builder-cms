<?php

namespace Drupal\social_auth\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\social_api\Plugin\NetworkManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Social Auth Block for Login.
 *
 * @Block(
 *   id = "social_auth_login",
 *   admin_label = @Translation("Social Auth Login"),
 * )
 */
class SocialAuthLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The network manager.
   *
   * @var \Drupal\social_api\Plugin\NetworkManager
   */
  private NetworkManager $networkManager;

  /**
   * Immutable configuration for social_auth.settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $socialAuthConfig;

  /**
   * SocialAuthLoginBlock constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ImmutableConfig $social_auth_config
   *   The Immutable configuration for social_oauth.settings.
   * @param \Drupal\social_api\Plugin\NetworkManager $network_manager
   *   The Social API network manager.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    ImmutableConfig $social_auth_config,
    NetworkManager $network_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->networkManager = $network_manager;
    $this->socialAuthConfig = $social_auth_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')->get('social_auth.settings'),
      $container->get('plugin.network.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $networks = [];
    foreach ($this->networkManager->getDefinitions() as $definition) {
      $networks[] = $this->networkManager->createInstance($definition['id']);
    }

    // Add social network name to data passed to template.
    return [
      '#theme' => 'login_with',
      '#networks' => $networks,
    ];
  }

}

<?php

namespace Drupal\private_message\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the private message actions block.
 *
 * This block holds links to perform actions on a private message thread.
 *
 * @Block(
 *   id = "private_message_actions_block",
 *   admin_label = @Translation("Private Message Actions"),
 *   category =  @Translation("Private Message"),
 * )
 */
class PrivateMessageActionsBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected ConfigFactory $configFactory;

  /**
   * Constructs a PrivateMessageForm object.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The ID of the plugin.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    AccountProxyInterface $currentUser,
    ConfigFactory $configFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): PrivateMessageActionsBlock {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($account->hasPermission('use private messaging system'))
      ->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configFactory->get('private_message.settings');
    $url = Url::fromRoute('private_message.private_message_create');
    $block['links'] = [
      '#type' => 'link',
      '#title' => $config->get("create_message_label"),
      '#url' => $url,
    ];

    // Add the default classes, as these are not added when the block output
    // is overridden with a template.
    $block['#attributes']['class'][] = 'block';
    $block['#attributes']['class'][] = 'block-private-message';
    $block['#attributes']['class'][] = 'block-private-message-actions-block';

    return $block;
  }

}

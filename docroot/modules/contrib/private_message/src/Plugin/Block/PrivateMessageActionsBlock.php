<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the private message actions block.
 *
 * This block holds links to perform actions on a private message thread.
 */
#[Block(
  id: 'private_message_actions_block',
  admin_label: new TranslatableMarkup('Private Message Actions'),
  category: new TranslatableMarkup('Private Message'),
)]
class PrivateMessageActionsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
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
    if (!$this->currentUser->hasPermission('use private messaging system')) {
      return [];
    }

    $config = $this->configFactory->get('private_message.settings');
    $url = Url::fromRoute('private_message.private_message_create');
    $block['links'][] = [
      '#type' => 'link',
      '#title' => $config->get("create_message_label"),
      '#url' => $url,
    ];

    $url = Url::fromRoute('private_message.ban_page');
    $block['links'][] = [
      '#type' => 'link',
      '#title' => $config->get("ban_page_label"),
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

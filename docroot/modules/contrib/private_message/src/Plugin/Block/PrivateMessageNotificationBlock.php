<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Drupal\private_message\Traits\PrivateMessageSettingsTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the private message notification block.
 */
#[Block(
   id: "private_message_notification_block",
   admin_label: new TranslatableMarkup('Private Message Notification'),
   category:  new TranslatableMarkup('Private Message'),
)]
class PrivateMessageNotificationBlock extends BlockBase implements BlockPluginInterface, ContainerFactoryPluginInterface {

  use PrivateMessageSettingsTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly CsrfTokenGenerator $csrfToken,
    protected readonly PrivateMessageServiceInterface $privateMessageService,
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
      $container->get('csrf_token'),
      $container->get('private_message.service'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf(
      $account->isAuthenticated() &&
      $account->hasPermission('use private messaging system')
    )->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $block = [
      '#theme' => 'private_message_notification_block',
    ];

    $config = $this->getConfiguration();

    switch ($config['count_method']) {
      default:
      case 'threads':
        $block['#new_items_count'] = $this->privateMessageService->getUnreadThreadCount();
        $url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_unread_thread_count']);
        break;

      case 'messages':
        $block['#new_items_count'] = $this->privateMessageService->getUnreadMessageCount();
        $url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_unread_message_count']);
        break;
    }
    $block['#count_method'] = $config['count_method'];

    $token = $this->csrfToken->get($url->getInternalPath());
    $url->setOptions(['query' => ['token' => $token]]);
    $block['#attached']['drupalSettings']['privateMessageNotificationBlock']['newMessageCountCallback'] = $url->toString();

    $block['#attached']['drupalSettings']['privateMessageNotificationBlock']['ajaxRefreshRate'] = $config['ajax_refresh_rate'];

    $block['#attached']['library'][] = 'private_message/notification_block_script';
    $style_disabled = $this->getPrivateMessageSettings()->get('remove_css');
    if (!$style_disabled) {
      $block['#attached']['library'][] = 'private_message/notification_block_style';
    }

    // Add the default classes, as these are not added when the block output
    // is overridden with a template.
    $block['#attributes']['class'][] = 'block';
    $block['#attributes']['class'][] = 'block-private-message';
    $block['#attributes']['class'][] = 'block-private-message-notification-block';

    return $block;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $tags = [
      'private_message:status:uid:' . $this->currentUser->id(),
      // This cache tag is @deprecated, since we have added the cache tag above.
      // https://www.drupal.org/project/private_message/issues/3035510.
      'private_message_notification_block:uid:' . $this->currentUser->id(),
    ];
    return Cache::mergeTags(parent::getCacheTags(), $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // Vary caching of this block per user and session.
    return Cache::mergeContexts(parent::getCacheContexts(), ['user', 'session']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'ajax_refresh_rate' => 15,
      'count_method' => 'threads',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['ajax_refresh_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Ajax refresh rate'),
      '#default_value' => $config['ajax_refresh_rate'],
      '#min' => 0,
      '#description' => $this->t('The number of seconds between checks to see if there are any new messages. Setting this to a low number will result in more requests to the server, adding overhead and bandwidth. Setting this number to zero will disable ajax refresh, and the inbox will only updated if/when the page is refreshed.'),
    ];

    $form['count_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Count method'),
      '#options' => [
        'threads' => $this->t('Count unread threads'),
        'messages' => $this->t('Count unread messages'),
      ],
      '#default_value' => $config['count_method'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['ajax_refresh_rate'] = $form_state->getValue('ajax_refresh_rate');
    $this->configuration['count_method'] = $form_state->getValue('count_method');
  }

}

<?php

namespace Drupal\commerce;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

/**
 * Defines a class for lazy building render arrays.
 *
 * @internal
 */
final class LazyBuilders implements TrustedCallbackInterface {

  /**
   * Constructs LazyBuilders object.
   *
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfo
   *   Element info.
   * @param \Drupal\commerce\InboxMessageStorageInterface $inboxMessageStorage
   *   The Commerce inbox message storage.
   */
  public function __construct(
    protected ElementInfoManagerInterface $elementInfo,
    protected InboxMessageStorageInterface $inboxMessageStorage,
  ) {
  }

  /**
   * Render the Commerce inbox link with an unread messages indicator.
   *
   * @return array
   *   Render array.
   */
  public function renderCommerceInbox(): array {
    $count = $this->inboxMessageStorage->getUnreadCount();
    $build = [
      '#type' => 'link',
      '#cache' => [
        'context' => ['user.permissions'],
        'tags' => [
          'commerce_inbox_message',
        ],
      ],
      '#title' => $count ? t('Commerce inbox <span>@count</span>', ['@count' => $count]) : t('Commerce inbox'),
      '#url' => Url::fromRoute('commerce.admin_commerce'),
      '#id' => Html::getId('toolbar-item-commerce-inbox'),
      '#attributes' => [
        'title' => t('Review project updates and news related to your Drupal Commerce site.'),
        'data-drupal-announce-trigger' => '',
        'class' => [
          'toolbar-icon',
          'toolbar-item',
          'toolbar-icon-commerce-inbox',
        ],
      ],
      '#attached' => [
        'library' => [
          'commerce/toolbar',
        ],
      ],
    ];

    $build += $this->elementInfo->getInfo('link');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['renderCommerceInbox'];
  }

}

<?php

namespace Drupal\commerce\Hook;

use Drupal\commerce\RenderCallbacks;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/**
 * Hook implementations for Commerce.
 */
class CommerceToolbarHooks {

  /**
   * Constructs a new CommerceToolbarHooks object.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Render\ElementInfoManagerInterface $elementInfoManager
   *   The element info manager.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected ElementInfoManagerInterface $elementInfoManager,
  ) {
  }

  /**
   * Implements hook_toolbar().
   */
  #[Hook('toolbar')]
  public function toolbar(): array {
    if (!Settings::get('commerce_dashboard_show_toolbar_link', TRUE)) {
      return [];
    }

    if (!$this->currentUser->hasPermission('access commerce administration pages')) {
      return [
        '#cache' => ['contexts' => ['user.permissions']],
      ];
    }

    $items['commerce_inbox'] = [
      '#type' => 'toolbar_item',
      'tab' => [
        '#lazy_builder' => [
          'commerce.lazy_builders:renderCommerceInbox',
          [],
        ],
        '#create_placeholder' => TRUE,
      ],
      '#wrapper_attributes' => [
        'class' => ['commerce-inbox-toolbar-tab'],
      ],
      '#cache' => [
        'contexts' => ['user.permissions'],
        'tags' => [
          'commerce_inbox_message',
        ],
      ],
      '#weight' => 3399,
    ];

    // \Drupal\toolbar\Element\ToolbarItem::preRenderToolbarItem adds an
    // #attributes property to each toolbar item's tab child automatically.
    // Lazy builders don't support an #attributes property so we need to
    // add another render callback to remove the #attributes property. We start by
    // adding the defaults, and then we append our own pre render callback.
    $items['commerce_inbox'] += $this->elementInfoManager->getInfo('toolbar_item');
    $items['commerce_inbox']['#pre_render'][] = [RenderCallbacks::class, 'removeTabAttributes'];

    return $items;
  }

  /**
   * Implements hook_toolbar_alter().
   */
  #[Hook('toolbar_alter')]
  public function toolbarAlter(&$items): void {
    $items['administration']['#attached']['library'][] = 'commerce/toolbar';
  }

}

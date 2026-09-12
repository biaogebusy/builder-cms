<?php

namespace Drupal\commerce_order\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Theme hook implementations for Commerce Order.
 */
class CommerceOrderThemeHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceOrderThemeHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MessengerInterface $messenger,
    protected RouteMatchInterface $routeMatch,
  ) {
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'commerce_order' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommerceOrder',
      ],
      'commerce_order__admin' => [
        'base hook' => 'commerce_order',
        'render element' => 'elements',
      ],
      'commerce_order__user' => [
        'base hook' => 'commerce_order',
        'render element' => 'elements',
      ],
      'commerce_order_edit_form' => [
        'render element' => 'form',
      ],
      'commerce_order_receipt' => [
        'variables' => [
          'order_entity' => NULL,
          'billing_information' => NULL,
          'shipping_information' => NULL,
          'payment_method' => NULL,
          'totals' => NULL,
        ],
      ],
      'commerce_order_item_inline_operations' => [
        'variables' => [
          'links' => [],
        ],
      ],
      'commerce_order_receipt__entity_print' => [
        'base hook' => 'commerce_order_receipt',
      ],
      'commerce_order_total_summary' => [
        'variables' => [
          'order_entity' => NULL,
          'totals' => NULL,
        ],
      ],
      'commerce_order_item' => [
        'render element' => 'elements',
        'initial preprocess' => static::class . ':preprocessCommerceOrderItem',
      ],
      'commerce_order_dashboard_metrics_form' => [
        'render element' => 'form',
      ],
      'views_view__commerce_order_item_table_admin' => [
        'base hook' => 'views_view',
      ],
      'commerce_order_item_title' => [
        'variables' => [
          'label' => NULL,
          'sku' => NULL,
        ],
      ],
    ];
  }

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(&$theme_registry): void {
    if (isset($theme_registry['commerce_price_calculated'])) {
      $theme_registry['commerce_price_calculated']['variables'] += [
        'result' => NULL,
        'base_price' => NULL,
        'adjustments' => [],
      ];
    }
  }

  /**
   * Implements hook_theme_suggestions_HOOK() for 'commerce_order'.
   */
  #[Hook('theme_suggestions_commerce_order')]
  public function themeSuggestionsCommerceOrder(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_order', $variables);
  }

  /**
   * Implements hook_theme_suggestions_HOOK() for 'commerce_order_item'.
   */
  #[Hook('theme_suggestions_commerce_order_item')]
  public function themeSuggestionsCommerceOrderItem(array $variables): array {
    return _commerce_entity_theme_suggestions('commerce_order_item', $variables);
  }

  /**
   * Implements hook_theme_suggestions_HOOK() for 'commerce_order_receipt'.
   */
  #[Hook('theme_suggestions_commerce_order_receipt')]
  public function themeSuggestionsCommerceOrderReceipt(array $variables): array {
    $suggestions = [];
    if (isset($variables['order_entity'])) {
      $order = $variables['order_entity'];
      $suggestions[] = $variables['theme_hook_original'] . '__' . $order->bundle();
    }
    return $suggestions;
  }

  /**
   * Prepares variables for order templates.
   *
   * Default template: commerce-order.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommerceOrder(array &$variables): void {
    $view_mode = $variables['elements']['#view_mode'];
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $variables['elements']['#commerce_order'];

    $variables['order_entity'] = $order;
    $variables['order'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['order'][$key] = $variables['elements'][$key];
    }
    // Inject order fields not manually printed in a separate variable for easier
    // rendering.
    if (in_array($view_mode, ['user', 'admin'], TRUE)) {
      $printed_fields = [
        'activity',
        'balance',
        'billing_information',
        'billing_profile',
        'changed',
        'completed',
        'coupons',
        'created',
        'ip_address',
        'mail',
        'order_items',
        'placed',
        'shipping_information',
        'state',
        'store_id',
        'total_paid',
        'total_price',
        'uid',
      ];
      $variables['additional_order_fields'] = array_diff_key($variables['order'], array_combine($printed_fields, $printed_fields));
      if ($billing_profile = $order->getBillingProfile()) {
        $profile_view_builder = $this->entityTypeManager->getViewBuilder('profile');
        $variables['order']['billing_information'] = $profile_view_builder->view($billing_profile, $view_mode);
      }

      if ($view_mode === 'admin') {
        // Show the order's store only if there are multiple available.
        /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
        $store_storage = $this->entityTypeManager->getStorage('commerce_store');
        $store_query = $store_storage->getQuery()->accessCheck(TRUE);
        $variables['stores_count'] = (int) $store_query->count()->execute();

        // Order information section.
        $edit_order_url = Url::fromRoute('commerce_order.entity_form.form_mode', [
          'commerce_order' => $order->id(),
          'form_mode' => 'order_details',
        ]);
        $edit_order_url->setOption('attributes', [
          'class' => ['use-ajax', 'commerce-edit-link'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 700,
            'title' => $this->t('Order details'),
          ]),
        ]);
        if ($edit_order_url->access()) {
          $variables['edit_order_modal_link'] = Link::fromTextAndUrl($this->t('Edit'), $edit_order_url);
        }
        $order_details_fields = ['mail', 'coupons'];
        $variables['order_details_fields'] = array_intersect_key($variables['order'], array_combine($order_details_fields, $order_details_fields));
        foreach ($order_details_fields as $index => $order_info_field) {
          $variables['order_details_fields'][$order_info_field]['#weight'] = $index * 5;
          $variables['order_details_fields'][$order_info_field]['#attributes']['class'][] = 'form-item';
        }
      }
    }

    if ($this->routeMatch?->getRouteName() !== 'entity.commerce_order.canonical') {
      return;
    }
    if ($order->isLocked() &&
      $order->access('unlock')) {
      $options = [
        'query' => [
          'destination' => $order->toUrl()->toString(),
        ],
      ];
      $order_unlock_link = Url::fromRoute('entity.commerce_order.unlock_form', [
        'commerce_order' => $order->id(),
      ], $options)->toString();
      $this->messenger->addStatus($this->t('This order is locked and cannot be edited or deleted. You can <a href=":link">unlock it here</a>.', [':link' => $order_unlock_link]));
      $this->messenger->addStatus($this->t('Orders are typically locked during the payment step in checkout to ensure prices on it do not change during a payment attempt. If the customer is currently paying for this order on a hosted payment page, editing this order could result in a mismatch between the order total and payment amount.'));
    }
    elseif ($order->getState()->getId() === 'draft') {
      $variables['draft_warning'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
          'role' => 'alert',
        ],
        'content' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['messages__content', 'no-print'],
          ],
          '#markup' => $this->t('Editing a draft order refreshes it similar to a shopping cart, recalculating prices and other adjustments.'),
        ],
      ];
    }
  }

  /**
   * Prepares variables for commerce order item templates.
   *
   * Default template: commerce-order-item.html.twig.
   *
   * @param array $variables
   *   An associative array containing:
   *   - elements: An associative array containing rendered fields.
   *   - attributes: HTML attributes for the containing element.
   */
  public function preprocessCommerceOrderItem(array &$variables): void {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $variables['elements']['#commerce_order_item'];
    $variables['order_item_entity'] = $order_item;

    $variables['order_item'] = [];
    foreach (Element::children($variables['elements']) as $key) {
      $variables['order_item'][$key] = $variables['elements'][$key];
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   *
   * Open the order local actions in modals.
   */
  #[Hook('preprocess_menu_local_action')]
  public function preprocessMenuLocalAction(array &$variables): void {
    if ($this->routeMatch?->getRouteName() !== 'entity.commerce_order.canonical') {
      return;
    }
    $attributes = &$variables['link']['#options']['attributes'];
    $attributes['class'][] = 'use-ajax';
    $attributes['data-dialog-type'] = 'modal';
    $attributes['data-dialog-options'] = json_encode(['width' => 880]);
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  #[Hook('preprocess_username')]
  public function preprocessUsername(array &$variables): void {
    if ($this->routeMatch?->getRouteName() !== 'entity.commerce_order.canonical') {
      return;
    }
    $variables['attributes']['class'][] = 'link';
  }

}

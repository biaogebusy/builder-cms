<?php

namespace Drupal\commerce_cart\Hook;

use Drupal\commerce\CronInterface;
use Drupal\commerce_cart\CartSessionInterface;
use Drupal\commerce_cart\Form\AddToCartForm;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\OrderAssignmentInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Hook implementations for Commerce Cart.
 */
class CommerceCartHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceCartHooks object.
   *
   * @param \Drupal\commerce\CronInterface $cron
   *   The commerce cart cron service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Closure $cartProvider
   *   The cart provider.
   * @param \Drupal\commerce_order\OrderAssignmentInterface $orderAssignment
   *   The order assignment.
   * @param \Drupal\commerce_cart\CartSessionInterface $cartSession
   *   The cart session.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    #[Autowire(service: 'commerce_cart.cron')]
    protected readonly CronInterface $cron,
    protected readonly ModuleHandlerInterface $moduleHandler,
    #[AutowireServiceClosure('commerce_cart.cart_provider')]
    protected \Closure $cartProvider,
    protected readonly OrderAssignmentInterface $orderAssignment,
    protected readonly CartSessionInterface $cartSession,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->cron->run();
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === 'commerce_order') {
      $fields['cart'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Cart'))
        ->setSettings([
          'on_label' => $this->t('Yes'),
          'off_label' => $this->t('No'),
        ])
        ->setDisplayOptions('view', [
          'label' => 'inline',
          'type' => 'boolean',
        ])
        ->setDisplayOptions('form', [
          'type' => 'boolean_checkbox',
          'settings' => [
            'display_label' => TRUE,
          ],
          'weight' => 20,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDefaultValue(FALSE);
    }
    return $fields;
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('commerce_order_access')]
  public function commerceOrderAccess(OrderInterface $order, string $operation, AccountInterface $account): AccessResultInterface {
    return $this->orderAccess($order, $operation, $account);
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('commerce_order_item_access')]
  public function commerceOrderItemAccess(OrderItemInterface $order_item, string $operation, AccountInterface $account): AccessResultInterface {
    $order = $order_item->getOrder();
    return $order ? $this->orderAccess($order, $operation, $account) : AccessResult::neutral();
  }

  /**
   * Implements hook_entity_type_build().
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    if ($this->moduleHandler->moduleExists('commerce_product')) {
      $entity_types['commerce_order_item']->setFormClass('add_to_cart', AddToCartForm::class);
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for commerce_order_form.
   */
  #[Hook('form_commerce_order_form_alter')]
  public function formCommerceOrderFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!isset($form['cart'])) {
      return;
    }
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_state->getFormObject()->getEntity();
    if ($order->getState()->getId() == 'draft') {
      // Move the cart element to the bottom of the meta sidebar container.
      $form['cart']['#group'] = 'meta';
      $form['cart']['#weight'] = 100;
    }
    else {
      // Only draft orders can be carts.
      $form['cart']['#type'] = 'hidden';
      $form['#default_value'] = FALSE;
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for 'commerce_order_item_type_form'.
   */
  #[Hook('form_commerce_order_item_type_form_alter')]
  public function formCommerceOrderItemTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($this->moduleHandler->moduleExists('commerce_product')) {
      $form['actions']['submit']['#submit'][] = [static::class, 'orderItemTypeFormSubmit'];
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for 'commerce_order_type_form'.
   */
  #[Hook('form_commerce_order_type_form_alter')]
  public function formCommerceOrderTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $form_state->getFormObject()->getEntity();
    $cart_form_view = $order_type->getThirdPartySetting('commerce_cart', 'cart_form_view', 'commerce_cart_form');
    $cart_block_view = $order_type->getThirdPartySetting('commerce_cart', 'cart_block_view', 'commerce_cart_block');
    $cart_expiration = $order_type->getThirdPartySetting('commerce_cart', 'cart_expiration');
    $enable_cart_message = $order_type->getThirdPartySetting('commerce_cart', 'enable_cart_message', TRUE);
    $view_storage = $this->entityTypeManager->getStorage('view');
    $available_form_views = [];
    $available_block_views = [];
    foreach ($view_storage->loadMultiple() as $view) {
      if (str_contains($view->get('tag'), 'commerce_cart_form')) {
        $available_form_views[$view->id()] = $view->label();
      }
      if (str_contains($view->get('tag'), 'commerce_cart_block')) {
        $available_block_views[$view->id()] = $view->label();
      }
    }

    $form['commerce_cart'] = [
      '#type' => 'details',
      '#title' => $this->t('Shopping cart settings'),
      '#weight' => 5,
      '#open' => TRUE,
      '#attached' => [
        'library' => ['commerce_cart/admin'],
      ],
    ];
    $form['commerce_cart']['cart_form_view'] = [
      '#type' => 'select',
      '#title' => $this->t('Shopping cart form view'),
      '#options' => $available_form_views,
      '#default_value' => $cart_form_view,
    ];
    $form['commerce_cart']['cart_block_view'] = [
      '#type' => 'select',
      '#title' => $this->t('Shopping cart block view'),
      '#options' => $available_block_views,
      '#default_value' => $cart_block_view,
    ];

    $form['commerce_cart']['cart_expiration_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete abandoned carts'),
      '#default_value' => !empty($cart_expiration['number']),
    ];
    $form['commerce_cart']['cart_expiration'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['interval'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="commerce_cart[cart_expiration_enable]"]' => ['checked' => TRUE],
        ],
      ],
      '#open' => TRUE,
    ];
    $form['commerce_cart']['cart_expiration']['anonymous_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete abandoned carts from anonymous users only'),
      '#default_value' => $cart_expiration['anonymous_only'] ?? FALSE,
    ];
    $form['commerce_cart']['cart_expiration']['number'] = [
      '#type' => 'number',
      '#title' => $this->t('Interval'),
      '#default_value' => !empty($cart_expiration['number']) ? $cart_expiration['number'] : 30,
      '#required' => TRUE,
      '#min' => 1,
    ];
    $form['commerce_cart']['cart_expiration']['unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Unit'),
      '#title_display' => 'invisible',
      '#default_value' => !empty($cart_expiration['unit']) ? $cart_expiration['unit'] : 'day',
      '#options' => [
        'minute' => $this->t('Minute'),
        'hour' => $this->t('Hour'),
        'day' => $this->t('Day'),
        'month' => $this->t('Month'),
      ],
      '#required' => TRUE,
    ];
    $form['commerce_cart']['enable_cart_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display a message when an item is added to the cart.'),
      '#default_value' => $enable_cart_message,
    ];

    $form['actions']['submit']['#submit'][] = [static::class, 'orderTypeFormSubmit'];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for entity_form_display_edit_form.
   *
   * Hides irrelevant purchased_entity widgets on the add_to_cart order item
   * form display.
   */
  #[Hook('form_entity_form_display_edit_form_alter')]
  public function formEntityFormDisplayEditFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();
    if ($form['#entity_type'] == 'commerce_order_item' && $entity->getMode() == 'add_to_cart') {
      $options = &$form['fields']['purchased_entity']['plugin']['type']['#options'];
      unset(
        $options['entity_reference_autocomplete_tags'],
        $options['entity_reference_autocomplete'],
        $options['inline_entity_form_complex'],
        $options['commerce_entity_select'],
        $options['commerce_order_items'],
        $options['options_buttons'],
        $options['options_select']
      );
    }
  }

  /**
   * Implements hook_entity_form_display_alter().
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    if ($context['entity_type'] != 'commerce_order_item') {
      return;
    }
    // The "add_to_cart" form mode doesn't have a form display yet.
    // Default to hiding the unit_price field.
    if ($context['form_mode'] == 'add_to_cart' && $context['form_mode'] != $form_display->getMode()) {
      $form_display->removeComponent('unit_price');
    }
  }

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(array &$links): void {
    $description = $this->t('Manage fields, Add to Cart forms, other form and display settings for your order items.');
    $links['entity.commerce_order_item_type.collection']['description'] = $description;
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin(UserInterface $account): void {
    // Assign the anonymous user's carts to the logged-in account.
    // This will only affect the carts that are in the user's session.
    $anonymous = new AnonymousUserSession();
    /** @var \Drupal\commerce_cart\CartProviderInterface $cart_provider */
    $cart_provider = ($this->cartProvider)();
    $carts = $cart_provider->getCarts($anonymous);
    $this->orderAssignment->assignMultiple($carts, $account);
  }

  /**
   * Checks that the account has access to the cart.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The cart order.
   * @param string $operation
   *   The operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   */
  protected function orderAccess(OrderInterface $order, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'view' && $operation !== 'update') {
      return AccessResult::neutral();
    }

    if ($account->isAuthenticated()) {
      $customer_check = $account->id() == $order->getCustomerId();
    }
    else {
      $order_id = $order->id();
      $active_cart = $this->cartSession->hasCartId($order_id);
      $completed_cart = $this->cartSession->hasCartId($order_id, CartSessionInterface::COMPLETED);
      $customer_check = $active_cart || $completed_cart;
    }

    $access_result = AccessResult::allowedIf($customer_check);
    if ($operation === 'update') {
      $access_result = $access_result->andIf(
        AccessResult::allowedIf($order->getState()->getId() === 'draft'),
      );
    }
    return $access_result
      ->addCacheableDependency($order)
      ->cachePerUser();
  }

  /**
   * Submission handler for commerce_cart_form_commerce_order_type_form_alter().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function orderTypeFormSubmit(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $form_state->getFormObject()->getEntity();
    $settings = &$form_state->getValue('commerce_cart');
    $order_type->setThirdPartySetting('commerce_cart', 'cart_form_view', $settings['cart_form_view']);
    $order_type->setThirdPartySetting('commerce_cart', 'cart_block_view', $settings['cart_block_view']);

    $cart_expiration = [];
    if (!empty($settings['cart_expiration_enable'])) {
      $cart_expiration = [
        'unit' => $settings['cart_expiration']['unit'],
        'number' => $settings['cart_expiration']['number'],
        'anonymous_only' => $settings['cart_expiration']['anonymous_only'],
      ];
    }
    $order_type->setThirdPartySetting('commerce_cart', 'cart_expiration', $cart_expiration);
    $order_type->setThirdPartySetting('commerce_cart', 'enable_cart_message', $settings['enable_cart_message']);
    $order_type->save();
  }

  /**
   * Submission handler for commerce_cart_form_commerce_order_item_type_form_alter().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function orderItemTypeFormSubmit(array $form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityForm);
    if ($form_object->getOperation() == 'add') {
      // Help merchants navigate the admin UI by ensuring the order item type
      // has a matching 'add_to_cart' form display.
      $storage = \Drupal::entityTypeManager()
        ->getStorage('entity_form_display');
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
      $form_display = $storage->create([
        'targetEntityType' => 'commerce_order_item',
        'bundle' => $form_object->getEntity()->id(),
        'mode' => 'add_to_cart',
        'status' => TRUE,
      ]);
      $form_display->removeComponent('unit_price');
      $form_display->save();
    }
  }

}

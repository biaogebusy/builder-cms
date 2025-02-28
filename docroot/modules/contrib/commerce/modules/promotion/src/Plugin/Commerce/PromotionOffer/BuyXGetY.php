<?php

namespace Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer;

use Drupal\commerce\ConditionGroup;
use Drupal\commerce\ConditionManagerInterface;
use Drupal\commerce\Context;
use Drupal\commerce\Plugin\Commerce\Condition\PurchasableEntityConditionInterface;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\PriceSplitterInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the "Buy X Get Y" offer for orders.
 *
 * Examples:
 * - "Buy 1 t-shirt, get 1 hat for $10 less"
 * - "Buy 3 t-shirts, get 2 t-shirts free (100% off)"
 *
 * The cheapest items are always discounted first. The offer applies multiple
 * times ("Buy 3 Get 1" will discount 2 items when 6 are bought).
 *
 * Decimal quantities are supported.
 *
 * @CommercePromotionOffer(
 *   id = "order_buy_x_get_y",
 *   label = @Translation("Buy X Get Y"),
 *   entity_type = "commerce_order",
 * )
 */
class BuyXGetY extends OrderPromotionOfferBase {

  /**
   * The condition manager.
   *
   * @var \Drupal\commerce\ConditionManagerInterface
   */
  protected $conditionManager;

  /**
   * The chain base price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected $chainPriceResolver;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BuyXGetY object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The pluginId for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   * @param \Drupal\commerce_order\PriceSplitterInterface $splitter
   *   The splitter.
   * @param \Drupal\commerce\ConditionManagerInterface $condition_manager
   *   The condition manager.
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain price resolver.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RounderInterface $rounder, PriceSplitterInterface $splitter, ConditionManagerInterface $condition_manager, ChainPriceResolverInterface $chain_price_resolver, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $rounder, $splitter);

    $this->conditionManager = $condition_manager;
    $this->chainPriceResolver = $chain_price_resolver;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_price.rounder'),
      $container->get('commerce_order.price_splitter'),
      $container->get('plugin.manager.commerce_condition'),
      $container->get('commerce_price.chain_price_resolver'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'buy_quantity' => 1,
      'buy_conditions' => [],
      'get_quantity' => 1,
      'get_conditions' => [],
      'get_auto_add' => FALSE,
      'offer_type' => 'percentage',
      'offer_percentage' => '0',
      'offer_amount' => NULL,
      'offer_limit' => '0',
      'display_inclusive' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form += parent::buildConfigurationForm($form, $form_state);
    // Remove the main fieldset.
    $form['#type'] = 'container';

    $form['buy'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Customer buys'),
      '#collapsible' => FALSE,
    ];
    $form['buy']['quantity'] = [
      '#type' => 'commerce_number',
      '#title' => $this->t('Quantity'),
      '#default_value' => $this->configuration['buy_quantity'],
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['buy']['conditions'] = [
      '#type' => 'commerce_conditions',
      '#title' => $this->t('Matching any of the following'),
      '#parent_entity_type' => 'commerce_promotion',
      '#entity_types' => ['commerce_order_item'],
      '#default_value' => $this->configuration['buy_conditions'],
    ];

    $form['get'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Customer gets'),
      '#collapsible' => FALSE,
    ];
    $form['get']['quantity'] = [
      '#type' => 'commerce_number',
      '#title' => $this->t('Quantity'),
      '#default_value' => $this->configuration['get_quantity'],
      '#min' => 1,
      '#required' => TRUE,
    ];
    $form['get']['conditions'] = [
      '#type' => 'commerce_conditions',
      '#title' => $this->t('Matching any of the following'),
      '#parent_entity_type' => 'commerce_promotion',
      '#entity_types' => ['commerce_order_item'],
      '#default_value' => $this->configuration['get_conditions'],
    ];
    $states = $this->getAutoAddStatesVisibility();
    $form['get']['auto_add_help'] = [
      '#type' => 'item',
      '#title' => $this->t('Behavior'),
      '#states' => [
        'visible' => [$states],
      ],
    ];
    $form['get']['auto_add'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Automatically add the offer product to the cart if it isn't in it already"),
      '#description' => $this->t('This behavior will only work when a single product variation (or a single product with only one variation) is specified.'),
      '#default_value' => $this->configuration['get_auto_add'],
      '#states' => [
        'visible' => [$states],
      ],
    ];
    $parents = array_merge($form['#parents'], ['offer', 'type']);
    $selected_offer_type = NestedArray::getValue($form_state->getUserInput(), $parents);
    $selected_offer_type = $selected_offer_type ?: $this->configuration['offer_type'];
    $offer_wrapper = Html::getUniqueId('buy-x-get-y-offer-wrapper');
    $form['offer'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('At a discounted value'),
      '#collapsible' => FALSE,
      '#prefix' => '<div id="' . $offer_wrapper . '">',
      '#suffix' => '</div>',
    ];
    $form['offer']['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Discounted by a'),
      '#title_display' => 'invisible',
      '#options' => [
        'percentage' => $this->t('Percentage'),
        'fixed_amount' => $this->t('Fixed amount'),
      ],
      '#default_value' => $selected_offer_type,
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxRefresh'],
        'wrapper' => $offer_wrapper,
      ],
    ];
    if ($selected_offer_type == 'percentage') {
      $form['offer']['percentage'] = [
        '#type' => 'commerce_number',
        '#title' => $this->t('Percentage off'),
        '#default_value' => Calculator::multiply($this->configuration['offer_percentage'], '100'),
        '#maxlength' => 255,
        '#min' => 0,
        '#max' => 100,
        '#size' => 4,
        '#field_suffix' => $this->t('%'),
        '#required' => TRUE,
      ];
    }
    else {
      $form['offer']['amount'] = [
        '#type' => 'commerce_price',
        '#title' => $this->t('Amount off'),
        '#default_value' => $this->configuration['offer_amount'],
        '#required' => TRUE,
      ];
    }
    $form['offer']['display_inclusive'] = [
      '#type' => 'radios',
      '#title' => $this->t('Discount display'),
      '#title_display' => 'invisible',
      '#options' => [
        TRUE => $this->t('Include the discount in the displayed unit price'),
        FALSE => $this->t('Only show the discount on the order total summary'),
      ],
      '#default_value' => (int) $this->configuration['display_inclusive'],
    ];

    $form['limit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Offer limit'),
      '#description' => $this->t('The number of times this offer can apply to the same order.'),
      '#collapsible' => FALSE,
    ];
    $form['limit']['amount'] = [
      '#type' => 'radios',
      '#title' => $this->t('Limited to'),
      '#title_display' => 'invisible',
      '#options' => [
        0 => $this->t('Unlimited'),
        1 => $this->t('Limited number of uses'),
      ],
      '#default_value' => $this->configuration['offer_limit'] ? 1 : 0,
    ];
    $form['limit']['offer_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of uses'),
      '#title_display' => 'invisible',
      '#default_value' => $this->configuration['offer_limit'] ?: 1,
      '#states' => [
        'invisible' => [
          ':input[name="offer[0][target_plugin_configuration][order_buy_x_get_y][limit][amount]"]' => ['value' => 0],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $parents = $form_state->getTriggeringElement()['#array_parents'];
    $parents = array_slice($parents, 0, -2);
    return NestedArray::getValue($form, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    if ($values['offer']['type'] == 'percentage' && empty($values['offer']['percentage'])) {
      $form_state->setError($form, $this->t('Percentage must be a positive number.'));
    }

    if ($values['get']['auto_add']) {
      // Ensure that at least one compatible condition enabled.
      $valid_condition_ids = array_keys($this->getPurchasableEntityConditions());
      $has_valid_condition = FALSE;
      foreach ($values['get']['conditions']['products'] as $condition_id => $condition_values) {
        if (in_array($condition_id, $valid_condition_ids) && (bool) $condition_values['enable']) {
          $has_valid_condition = TRUE;
          break;
        }
      }
      // We can't automatically add the "get" product if no valid conditions are
      // selected.
      if (!$has_valid_condition) {
        $values['get']['auto_add'] = FALSE;
        $form_state->setValue($form['#parents'], $values);
        return;
      }

      // Ensure that the offer is a 100% discount.
      if ($values['offer']['type'] === 'fixed_amount' || ($values['offer']['type'] === 'percentage' && $values['offer']['percentage'] != '100')) {
        $form_state->setError($form['offer'], $this->t('The "auto-add" offer can only be enabled for fully discounted products (i.e with a 100% discount).'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['buy_quantity'] = $values['buy']['quantity'];
      $this->configuration['buy_conditions'] = $values['buy']['conditions'];
      $this->configuration['get_quantity'] = $values['get']['quantity'];
      $this->configuration['get_conditions'] = $values['get']['conditions'];
      $this->configuration['get_auto_add'] = $values['get']['auto_add'];
      $this->configuration['offer_type'] = $values['offer']['type'];
      $this->configuration['display_inclusive'] = !empty($values['offer']['display_inclusive']);
      if ($this->configuration['offer_type'] == 'percentage') {
        $this->configuration['offer_percentage'] = Calculator::divide((string) $values['offer']['percentage'], '100');
        $this->configuration['offer_amount'] = NULL;
      }
      else {
        $this->configuration['offer_percentage'] = NULL;
        $this->configuration['offer_amount'] = $values['offer']['amount'];
      }
      $this->configuration['offer_limit'] = ($values['limit']['amount'] == 0 ? 0 : $values['limit']['offer_limit']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, PromotionInterface $promotion) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $order_items = $order->getItems();
    $buy_conditions = $this->buildConditionGroup($this->configuration['buy_conditions']);
    $buy_order_items = $this->selectOrderItems($order_items, $buy_conditions, 'DESC');
    $buy_quantities = $this->getOrderItemsQuantities($buy_order_items);

    if (array_sum($buy_quantities) < $this->configuration['buy_quantity']) {
      return;
    }

    $get_conditions = $this->buildConditionGroup($this->configuration['get_conditions']);
    if ($this->configuration['get_auto_add'] && ($get_purchasable_entity = $this->findSinglePurchasableEntity($get_conditions))) {
      $order_item = $this->findOrCreateOrderItem($get_purchasable_entity, $order_items);
      $expected_get_quantity = $this->calculateExpectedGetQuantity($buy_quantities, $order_item);
      // If the expected get quantity is non-zero, we need to update the
      // quantity of the 'get' order item accordingly.
      if (Calculator::compare($expected_get_quantity, '0') !== 0) {
        // Add the expected get quantity to the current quantity.
        // Multiple promotions can target the same "get" order item, so we
        // need to ensure the existing quantity is taken into account.
        $order_item->setQuantity(Calculator::add($order_item->getQuantity(), $expected_get_quantity));
        // Keep track of the quantity that was auto-added to this order item so
        // we can subtract it (or remove the order item completely) if the buy
        // conditions are no longer satisfied on the next order refresh.
        $order_item->setData("promotion:{$promotion->id()}:auto_add_quantity", $expected_get_quantity);
        // Ensure "auto-added" order items are locked.
        $order_item->lock();

        $time = $order->getCalculationDate()->format('U');
        $context = new Context($order->getCustomer(), $order->getStore(), $time);
        $unit_price = $this->chainPriceResolver->resolve($get_purchasable_entity, $order_item->getQuantity(), $context);
        $order_item->setUnitPrice($unit_price);

        if ($order_item->isNew()) {
          $order_item->set('order_id', $order->id());
          $order_item->save();
          $order->addItem($order_item);
          $order_items = $order->getItems();
          $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->load($order_item->id());
        }
        // When the get order item is automatically added, we shouldn't have to
        // look for it via selectOrderItems().
        // For some reason, we have to reload the order item here, otherwise
        // changes are not detected by the order refresh when the promotion
        // adjustment is added later on.
        $get_order_items[$order_item->id()] = $order_item;
      }
    }

    $get_order_items = $get_order_items ?? $this->selectOrderItems($order_items, $get_conditions, 'ASC');
    $get_quantities = $this->getOrderItemsQuantities($get_order_items);
    if (empty($get_quantities)) {
      return;
    }

    // It is possible for $buy_quantities and $get_quantities to overlap (have
    // the same order item IDs). For example, in a "Buy 3 Get 1" scenario with
    // a single T-shirt order item of quantity: 8, there are 6 bought and 2
    // discounted products, in this order: 3, 1, 3, 1. To ensure the specified
    // results, $buy_quantities must be processed group by group, with any
    // overlaps immediately removed from the $get_quantities (and vice-versa).
    // Additionally, ensure that any items from $buy_quantities that overlap
    // with $get_quantities are processed last, in order to accommodate the case
    // when $buy_conditions (or the lack thereof) are satisfied by the other
    // (non-overlapping) $buy_quantity items.
    foreach ($buy_quantities as $id => $quantity) {
      if (isset($get_quantities[$id])) {
        unset($buy_quantities[$id]);
        $buy_quantities[$id] = $quantity;
      }
    }

    $final_quantities = [];
    $i = 0;
    while (!empty($buy_quantities)) {
      $selected_buy_quantities = $this->sliceQuantities($buy_quantities, $this->configuration['buy_quantity']);
      if (array_sum($selected_buy_quantities) < $this->configuration['buy_quantity']) {
        break;
      }
      $get_quantities = $this->removeQuantities($get_quantities, $selected_buy_quantities);
      $selected_get_quantities = $this->sliceQuantities($get_quantities, $this->configuration['get_quantity']);
      $buy_quantities = $this->removeQuantities($buy_quantities, $selected_get_quantities);
      // Merge the selected get quantities into a final list, to ensure that
      // each order item only gets a single adjustment.
      $final_quantities = $this->mergeQuantities($final_quantities, $selected_get_quantities);

      // Determine whether the offer reached its limit.
      if ($this->configuration['offer_limit'] == ++$i) {
        break;
      }
    }

    foreach ($final_quantities as $order_item_id => $quantity) {
      $order_item = $get_order_items[$order_item_id];
      $adjusted_unit_price = $order_item->getAdjustedUnitPrice(['promotion']);

      // The adjusted unit price is already reduced to 0, no need to continue
      // further.
      if ($adjusted_unit_price->isZero()) {
        continue;
      }
      $adjustment_amount = $this->buildAdjustmentAmount($order_item, $quantity);

      $order_item->addAdjustment(new Adjustment([
        'type' => 'promotion',
        'label' => $promotion->getDisplayName() ?: $this->t('Discount'),
        'amount' => $adjustment_amount->multiply('-1'),
        'source_id' => $promotion->id(),
        'included' => !empty($this->configuration['display_inclusive']),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clear(EntityInterface $entity, PromotionInterface $promotion) {
    parent::clear($entity, $promotion);

    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;

    // Check if we have any order item whose quantity has been changed by this
    // promotion, and subtract that amount. If the promotion still applies, the
    // necessary quantity will be added back in ::apply(). Order items that will
    // end up with a quantity of 0 will be removed from the order by
    // \Drupal\commerce_order\OrderRefresh::refresh().
    $promotion_data_key = "promotion:{$promotion->id()}:auto_add_quantity";
    $auto_add_order_items = array_filter($order->getItems(), function (OrderItemInterface $order_item) use ($promotion_data_key) {
      return $order_item->getData($promotion_data_key);
    });
    foreach ($auto_add_order_items as $order_item) {
      $new_quantity = Calculator::subtract($order_item->getQuantity(), $order_item->getData($promotion_data_key));
      $order_item->setQuantity($new_quantity);
      $order_item->unlock();
      $order_item->unsetData($promotion_data_key);
    }
  }

  /**
   * Builds a condition group for the given condition configuration.
   *
   * @param array $condition_configuration
   *   The condition configuration.
   *
   * @return \Drupal\commerce\ConditionGroup
   *   The condition group.
   */
  protected function buildConditionGroup(array $condition_configuration) {
    $conditions = [];
    foreach ($condition_configuration as $condition) {
      if (!empty($condition['plugin'])) {
        $conditions[] = $this->conditionManager->createInstance($condition['plugin'], $condition['configuration']);
      }
    }

    return new ConditionGroup($conditions, 'OR');
  }

  /**
   * Selects non-free order items that match the given conditions.
   *
   * Selected order items are sorted by unit price in the specified direction.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items
   *   The order items.
   * @param \Drupal\commerce\ConditionGroup $conditions
   *   The conditions.
   * @param string $sort_direction
   *   The sort direction.
   *   Use 'ASC' for least expensive first, 'DESC' for most expensive first.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface[]
   *   The selected order items, keyed by order item ID.
   */
  protected function selectOrderItems(array $order_items, ConditionGroup $conditions, $sort_direction = 'ASC') {
    $selected_order_items = [];
    foreach ($order_items as $index => $order_item) {
      if ($order_item->getAdjustedTotalPrice()->isZero()) {
        continue;
      }
      if (!$conditions->evaluate($order_item)) {
        continue;
      }
      $selected_order_items[$order_item->id()] = $order_item;
    }
    uasort($selected_order_items, function (OrderItemInterface $a, OrderItemInterface $b) use ($sort_direction) {
      if ($sort_direction == 'ASC') {
        $result = $a->getUnitPrice()->compareTo($b->getUnitPrice());
      }
      else {
        $result = $b->getUnitPrice()->compareTo($a->getUnitPrice());
      }

      return $result;
    });

    return $selected_order_items;
  }

  /**
   * Gets an array of order items quantities.
   *
   * @param array $order_items
   *   The order items.
   *
   * @return array
   *   The order items quantities.
   */
  protected function getOrderItemsQuantities(array $order_items) {
    return array_map(function (OrderItemInterface $order_item) {
      return $order_item->getQuantity();
    }, $order_items);
  }

  /**
   * Finds the configured purchasable entity amongst the given conditions.
   *
   * @param \Drupal\commerce\ConditionGroup $conditions
   *   The condition group.
   *
   * @return \Drupal\commerce\PurchasableEntityInterface|null
   *   The purchasable entity, or NULL if not found in the conditions.
   */
  protected function findSinglePurchasableEntity(ConditionGroup $conditions) {
    foreach ($conditions->getConditions() as $condition) {
      if ($condition instanceof PurchasableEntityConditionInterface) {
        $purchasable_entity_ids = $condition->getPurchasableEntityIds();
        if (count($purchasable_entity_ids) === 1) {
          $purchasable_entities = $condition->getPurchasableEntities();
          return reset($purchasable_entities);
        }
      }
    }

    return NULL;
  }

  /**
   * Attempt to find the given purchasable entity amongst the given order items.
   *
   * If the given purchasable entity isn't referenced by any order item, create
   * an order item referencing it so we can automatically add it to the order.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $get_purchasable_entity
   *   The "get" purchasable entity.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items
   *   The order items.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface
   *   An order item referencing the given purchasable entity.
   */
  protected function findOrCreateOrderItem(PurchasableEntityInterface $get_purchasable_entity, array $order_items) {
    foreach ($order_items as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity) {
        continue;
      }
      // We skip order items that are not auto-added here, note that the same
      // order item across different "BuyXGetY" promotions.
      // For sake of simplicity and clarity, we do not attempt to reuse an order item
      // already added by the customer.
      if (!$this->isAutoAddedOrderItem($order_item)) {
        continue;
      }
      if ($purchased_entity->getEntityTypeId() == $get_purchasable_entity->getEntityTypeId()
        && $purchased_entity->id() == $get_purchasable_entity->id()) {
        return $order_item;
      }
    }

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('commerce_order_item');
    $order_item = $storage->createFromPurchasableEntity($get_purchasable_entity, [
      'quantity' => 0,
      'data' => [
        'owned_by_promotion' => TRUE,
      ],
    ]);

    return $order_item;
  }

  /**
   * Checks whether the given order item was auto added.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return bool
   *   Whether the given order item was auto added.
   */
  protected function isAutoAddedOrderItem(OrderItemInterface $order_item): bool {
    if ($order_item->get('data')->isEmpty()) {
      return FALSE;
    }
    if ($order_item->getData('owned_by_promotion')) {
      return TRUE;
    }
    $order_item_data_keys = array_keys($order_item->get('data')->first()->getValue());
    if (!preg_grep('/promotion:\d*:auto_add_quantity/', $order_item_data_keys)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Calculates the expected get quantity.
   *
   * @param array $buy_quantities
   *   An array of buy quantities.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return string
   *   The expected get quantity.
   */
  protected function calculateExpectedGetQuantity(array $buy_quantities, OrderItemInterface $order_item) {
    $expected_get_quantity = '0';

    // Ensure that any possible "get" quantity already in the order is always
    // processed last.
    if (!$order_item->isNew()) {
      if (isset($buy_quantities[$order_item->id()])) {
        $quantity = $buy_quantities[$order_item->id()];
        unset($buy_quantities[$order_item->id()]);
        $buy_quantities[$order_item->id()] = $quantity;
      }
    }

    $i = 0;
    while (!empty($buy_quantities)) {
      $this->sliceQuantities($buy_quantities, $this->configuration['buy_quantity']);
      $expected_get_quantity = Calculator::add($expected_get_quantity, $this->configuration['get_quantity']);

      // Determine whether the offer reached its limit.
      if ($this->configuration['offer_limit'] == ++$i) {
        break;
      }

      // If the "get" purchasable entity is already in the order, we need to
      // ensure that the already discounted quantity is not counted towards the
      // buy quantities, note that we only do that if the "get" purchasable
      // entity is different than the "buy" purchasable entity.
      if (!$order_item->isNew() && !isset($buy_quantities[$order_item->id()])) {
        $buy_quantities = $this->removeQuantities($buy_quantities, [$order_item->id() => $this->configuration['get_quantity']]);
      }

      if (array_sum($buy_quantities) < $this->configuration['buy_quantity']) {
        break;
      }
    }

    return $expected_get_quantity;
  }

  /**
   * Gets the #states visibility array for the 'auto_add' form element.
   *
   * @return array
   *   An array of visibility options for a form element's #state property.
   */
  protected function getAutoAddStatesVisibility() {
    // The 'auto_add' form element has to be shown if _any_ condition that
    // provides a purchasable entity is enabled for the 'get' conditions. This
    // means we need to construct a list of OR statements for #states, which
    // looks like this:
    // @code
    // '#states' => [
    //   'visible' => [
    //     [':input[name="some_element"]' => ['checked' => TRUE]],
    //     'or',
    //     [':input[name="another_element"]' => ['checked' => TRUE]],
    //     ...
    //   ],
    // ],
    // @endcode
    $conditions = $this->getPurchasableEntityConditions();
    $states_visibility = array_map(function ($value) {
      return [':input[name="offer[0][target_plugin_configuration][order_buy_x_get_y][get][conditions][products][' . $value . '][enable]"]' => ['checked' => TRUE]];
    }, array_keys($conditions));

    for ($i = 0; $i < count($conditions) - 1; $i++) {
      array_splice($states_visibility, $i + 1, 0, 'or');
    }

    return $states_visibility;
  }

  /**
   * Takes a slice from the given quantity list.
   *
   * For example, ['1' => '10', '2' => '5'] sliced for total quantity '11'
   * will produce a ['1' => '10', '2' => '1'] slice, leaving ['2' => '4']
   * in the original list.
   *
   * @param array $quantities
   *   The quantity list. Modified by reference.
   * @param string $total_quantity
   *   The total quantity of the new slice.
   *
   * @return array
   *   The quantity list slice.
   */
  protected function sliceQuantities(array &$quantities, $total_quantity) {
    $remaining_quantity = $total_quantity;
    $slice = [];
    foreach ($quantities as $order_item_id => $quantity) {
      if ($quantity <= $remaining_quantity) {
        $remaining_quantity = Calculator::subtract($remaining_quantity, $quantity);
        $slice[$order_item_id] = $quantity;
        unset($quantities[$order_item_id]);
        if ($remaining_quantity === '0') {
          break;
        }
      }
      else {
        $slice[$order_item_id] = $remaining_quantity;
        $quantities[$order_item_id] = Calculator::subtract($quantity, $remaining_quantity);
        break;
      }
    }

    return $slice;
  }

  /**
   * Removes the second quantity list from the first quantity list.
   *
   * For example: ['1' => '10', '2' => '20'] - ['1' => '10', '2' => '17']
   * will result in ['2' => '3'].
   *
   * @param array $first_quantities
   *   The first quantity list.
   * @param array $second_quantities
   *   The second quantity list.
   *
   * @return array
   *   The new quantity list.
   */
  protected function removeQuantities(array $first_quantities, array $second_quantities) {
    foreach ($second_quantities as $order_item_id => $quantity) {
      if (isset($first_quantities[$order_item_id])) {
        $first_quantities[$order_item_id] = Calculator::subtract($first_quantities[$order_item_id], $second_quantities[$order_item_id]);
        if ($first_quantities[$order_item_id] <= 0) {
          unset($first_quantities[$order_item_id]);
        }
      }
    }

    return $first_quantities;
  }

  /**
   * Merges the first quantity list with the second quantity list.
   *
   * Quantities belonging to shared order item IDs will be added together.
   *
   * For example: ['1' => '10'] and ['1' => '10', '2' => '17']
   * will merge into ['1' => '20', '2' => '17'].
   *
   * @param array $first_quantities
   *   The first quantity list.
   * @param array $second_quantities
   *   The second quantity list.
   *
   * @return array
   *   The new quantity list.
   */
  protected function mergeQuantities(array $first_quantities, array $second_quantities) {
    foreach ($second_quantities as $order_item_id => $quantity) {
      if (!isset($first_quantities[$order_item_id])) {
        $first_quantities[$order_item_id] = $quantity;
      }
      else {
        $first_quantities[$order_item_id] = Calculator::add($first_quantities[$order_item_id], $second_quantities[$order_item_id]);
      }
    }

    return $first_quantities;
  }

  /**
   * Builds an adjustment amount for the given order item and quantity.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param string $quantity
   *   The quantity.
   *
   * @return \Drupal\commerce_price\Price
   *   The adjustment amount.
   */
  protected function buildAdjustmentAmount(OrderItemInterface $order_item, $quantity) {
    if ($this->configuration['offer_type'] == 'percentage') {
      $percentage = (string) $this->configuration['offer_percentage'];
      $adjusted_total_price = $order_item->getAdjustedTotalPrice(['promotion']);
      $adjustment_amount = $order_item->getUnitPrice()->multiply($quantity);
      $adjustment_amount = $adjustment_amount->multiply($percentage);
      // Don't reduce the unit price past 0.
      if ($adjustment_amount->greaterThan($adjusted_total_price)) {
        $adjustment_amount = $adjusted_total_price;
      }
      if (!empty($this->configuration['display_inclusive'])) {
        $new_unit_price = $order_item->getTotalPrice()->subtract($adjustment_amount)->divide($order_item->getQuantity());
        $new_unit_price = $this->rounder->round($new_unit_price);
        $order_item->setUnitPrice($new_unit_price);
      }
      $adjustment_amount = $this->rounder->round($adjustment_amount);
    }
    else {
      $amount = Price::fromArray($this->configuration['offer_amount']);
      $unit_price = $order_item->getAdjustedUnitPrice(['promotion']);
      if ($amount->greaterThan($unit_price)) {
        $amount = $unit_price;
      }
      $adjustment_amount = $amount->multiply($quantity);
      if (!empty($this->configuration['display_inclusive'])) {
        $new_unit_price = $order_item->getTotalPrice()->subtract($adjustment_amount)->divide($order_item->getQuantity());
        $new_unit_price = $this->rounder->round($new_unit_price);
        $order_item->setUnitPrice($new_unit_price);
      }
      $adjustment_amount = $this->rounder->round($adjustment_amount);
    }

    return $adjustment_amount;
  }

  /**
   * Gets the purchasable entity condition plugin definitions.
   *
   * @return array
   *   The purchasable entity condition plugin definitions.
   */
  protected function getPurchasableEntityConditions() {
    return array_filter($this->conditionManager->getFilteredDefinitions('commerce_promotion', ['commerce_order_item']), function ($definition) {
      return is_subclass_of($definition['class'], PurchasableEntityConditionInterface::class);
    });
  }

}

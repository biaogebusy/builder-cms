<?php

namespace Drupal\commerce_product\Plugin\Commerce\Condition;

use Drupal\commerce\EntityUuidMapperInterface;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\commerce\Plugin\Commerce\Condition\PurchasableEntityConditionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the product condition for order items.
 *
 * @CommerceCondition(
 *   id = "order_item_product",
 *   label = @Translation("Product"),
 *   display_label = @Translation("Specific products"),
 *   category = @Translation("Products"),
 *   entity_type = "commerce_order_item",
 *   weight = -1,
 * )
 */
class OrderItemProduct extends ConditionBase implements PurchasableEntityConditionInterface, ContainerFactoryPluginInterface {

  use ProductTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new OrderItemProduct object.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce\EntityUuidMapperInterface $entity_uuid_mapper
   *   The entity UUID mapper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityUuidMapperInterface $entity_uuid_mapper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->productStorage = $entity_type_manager->getStorage('commerce_product');
    $this->entityUuidMapper = $entity_uuid_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('commerce.entity_uuid_mapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $entity;
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $purchased_entity */
    $purchased_entity = $order_item->getPurchasedEntity();
    if (!$purchased_entity || $purchased_entity->getEntityTypeId() != 'commerce_product_variation') {
      return FALSE;
    }
    $product_ids = $this->getProductIds();

    return in_array($purchased_entity->getProductId(), $product_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasableEntityIds() {
    $variation_ids = [];

    $product_ids = $this->getProductIds();
    if (!empty($product_ids)) {
      foreach ($this->productStorage->loadMultiple($product_ids) as $product) {
        /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
        $variation_ids += $product->getVariationIds();
      }
    }

    return array_values($variation_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasableEntities() {
    if ($entity_ids = $this->getPurchasableEntityIds()) {
      $storage = $this->entityTypeManager->getStorage('commerce_product_variation');
      $entities = $storage->loadMultiple($entity_ids);
    }

    return $entities ?? [];
  }

}

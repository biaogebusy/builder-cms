<?php

namespace Drupal\commerce_product\Plugin\views\filter;

use Drupal\commerce_product\Entity\ProductAttributeInterface;
use Drupal\commerce_product\Entity\ProductAttributeValueInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter for product attribute values.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("commerce_product_attribute_value")
 */
class ProductAttributeValue extends InOperator {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs a new ProductAttributeValue object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
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
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $attribute_storage = $this->entityTypeManager->getStorage('commerce_product_attribute');
      $attribute = $attribute_storage->load($this->definition['attribute']);
      assert($attribute instanceof ProductAttributeInterface);
      $attribute_values = array_map(function (ProductAttributeValueInterface $value) {
        return $this->entityRepository->getTranslationFromContext($value);
      }, $attribute->getValues());
      foreach ($attribute_values as $value) {
        $this->valueOptions[$value->id()] = $value->label();
      }
    }

    return $this->valueOptions;
  }

}

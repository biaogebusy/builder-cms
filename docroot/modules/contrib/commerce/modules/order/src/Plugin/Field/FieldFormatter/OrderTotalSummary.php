<?php

namespace Drupal\commerce_order\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_order_total_summary' formatter.
 */
#[FieldFormatter(
  id: "commerce_order_total_summary",
  label: new TranslatableMarkup("Order total summary"),
  field_types: ["commerce_price"],
)]
class OrderTotalSummary extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The order total summary service.
   *
   * @var \Drupal\commerce_order\OrderTotalSummaryInterface
   */
  protected $orderTotalSummary;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->orderTotalSummary = $container->get('commerce_order.order_total_summary');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $items->getEntity();
    $elements = [];
    if (!$items->isEmpty()) {
      $elements[0] = [
        '#theme' => 'commerce_order_total_summary',
        '#order_entity' => $order,
        '#totals' => $this->orderTotalSummary->buildTotals($order),
      ];
    }
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($order);
    $cacheability->applyTo($elements);

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $entity_type == 'commerce_order' && $field_name == 'total_price';
  }

}

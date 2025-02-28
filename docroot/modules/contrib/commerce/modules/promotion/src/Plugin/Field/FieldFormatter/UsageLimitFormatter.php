<?php

namespace Drupal\commerce_promotion\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_usage_limit' formatter.
 *
 * @FieldFormatter(
 *   id = "commerce_usage_limit",
 *   label = @Translation("Usage limit"),
 *   field_types = {
 *     "integer",
 *   },
 * )
 */
class UsageLimitFormatter extends FormatterBase {

  /**
   * The usage.
   *
   * @var \Drupal\commerce_promotion\PromotionUsageInterface
   */
  protected $usage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->usage = $container->get('commerce_promotion.usage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $field_name = $items->getFieldDefinition()->getName();
    $entity = $items->getEntity();

    // For the "usage_limit" output actual usage / limit.
    if ($field_name === 'usage_limit') {
      // Gets the promotion|coupon usage.
      $current_usage = $entity->getEntityTypeId() === 'commerce_promotion' ?
        $this->usage->load($entity) :
        $this->usage->loadByCoupon($entity);
      $usage_limit = $entity->getUsageLimit();
      $usage_limit = $usage_limit ?: $this->t('Unlimited');
      $usage = $current_usage . ' / ' . $usage_limit;
    }
    else {
      $customer_limit = $entity->getCustomerUsageLimit();
      $usage = $customer_limit ?: $this->t('Unlimited');
    }

    $elements[0] = [
      '#markup' => $usage,
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    $applicable_entity_type = in_array($entity_type, ['commerce_promotion', 'commerce_promotion_coupon']);
    $applicable_field_name = in_array($field_name, ['usage_limit', 'usage_limit_customer']);
    return $applicable_entity_type && $applicable_field_name;
  }

}

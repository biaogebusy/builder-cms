<?php

namespace Drupal\commerce_promotion;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderPreprocessorInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Applies promotions to orders during the order refresh process.
 */
class PromotionOrderProcessor implements OrderPreprocessorInterface, OrderProcessorInterface {

  /**
   * Constructs a new PromotionOrderProcessor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(OrderInterface $order) {
    // Collect the promotion adjustments, to give promotions a chance to clear
    // any potential modifications made to the order prior to refreshing it.
    $promotion_ids = [];
    foreach ($order->collectAdjustments(['promotion']) as $adjustment) {
      if (empty($adjustment->getSourceId())) {
        continue;
      }
      $promotion_ids[] = $adjustment->getSourceId();
    }

    // Additionally, promotions may have altered the order without actually
    // adding promotion adjustments to the order, in this case, we need to
    // inspect the order item data to see if arbitrary data was stored by
    // promotion offers.
    // This will eventually need to be replaced by a proper solution at some
    // point once we have a more reliable way to figure out what the applied
    // promotions are.
    foreach ($order->getItems() as $order_item) {
      if ($order_item->get('data')->isEmpty()) {
        continue;
      }
      $data = $order_item->get('data')->first()->getValue();
      foreach ($data as $key => $value) {
        $key_parts = explode(':', $key);
        // Skip order item data keys that are not starting by
        // "promotion:<promotion_id>".
        if (count($key_parts) === 1 || $key_parts[0] !== 'promotion' || !is_numeric($key_parts[1])) {
          continue;
        }
        $promotion_ids[] = $key_parts[1];
      }
    }

    // No promotions were found, stop here.
    if (!$promotion_ids) {
      return;
    }
    $promotion_ids = array_unique($promotion_ids);

    /** @var \Drupal\commerce_promotion\PromotionStorageInterface $promotion_storage */
    $promotion_storage = $this->entityTypeManager->getStorage('commerce_promotion');
    $promotions = $promotion_storage->loadMultiple($promotion_ids);
    /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
    foreach ($promotions as $promotion) {
      $promotion->clear($order);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    // Remove coupons that are no longer valid (due to availability/conditions.)
    $coupons_field_list = $order->get('coupons');
    $constraints = $coupons_field_list->validate();
    $coupons_to_remove = [];
    /** @var \Symfony\Component\Validator\ConstraintViolationInterface $constraint */
    foreach ($constraints as $constraint) {
      [$delta, $property_name] = explode('.', $constraint->getPropertyPath());
      // Collect the coupon IDS to remove, for use in the item list filter
      // callback right after.
      $coupons_to_remove[] = $coupons_field_list->get($delta)->target_id;
    }
    if ($coupons_to_remove) {
      $coupons_field_list->filter(function ($item) use ($coupons_to_remove) {
        return !in_array($item->target_id, $coupons_to_remove, TRUE);
      });
    }
    /** @var \Drupal\commerce_promotion\PromotionStorageInterface $promotion_storage */
    $promotion_storage = $this->entityTypeManager->getStorage('commerce_promotion');
    $config = $this->configFactory->get('commerce_promotion.settings');

    /** @var \Drupal\commerce_promotion\Entity\CouponInterface[] $coupons */
    $coupons = $order->get('coupons')->referencedEntities();
    $coupon_promotions = [];
    foreach ($coupons as $coupon) {
      $promotion = $coupon->getPromotion();
      $coupon_promotions[] = $promotion;
    }
    $promotions = array_merge($coupon_promotions, $promotion_storage->loadAvailable($order));

    // When enforce ordering is enabled, sort promotions by weight.
    if ($config->get('enforce_weight_ordering') && $promotions) {
      uasort($promotions, [Promotion::class, 'sort']);
    }

    // Determine if compatibility checks are necessary.
    $promotion_compatibility_matters = FALSE;
    if (count($promotions) > 1) {
      foreach ($promotions as $promotion) {
        if ($promotion->getCompatibility() === PromotionInterface::COMPATIBLE_NONE) {
          $promotion_compatibility_matters = TRUE;
          break;
        }
      }
    }
    $content_langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $applied_any = FALSE;
    foreach ($promotions as $promotion) {
      // Skip incompatible promotions if another promotion has already applied.
      if ($promotion_compatibility_matters &&
        $this->isIncompatibleWithOthers($promotion, $applied_any)) {
        continue;
      }

      // Skip promotions that do not apply.
      if (!$promotion->applies($order)) {
        continue;
      }

      if ($promotion->hasTranslation($content_langcode)) {
        $promotion = $promotion->getTranslation($content_langcode);
      }
      $promotion->apply($order);

      // Track whether any promotion adjustments were actually added.
      if ($promotion_compatibility_matters && !$applied_any) {
        $applied_any = !empty($order->collectAdjustments(['promotion', 'shipping_promotion']));
      }

      // Break the loop if this promotion blocks others.
      if ($promotion_compatibility_matters && $this->isIncompatibleWithOthers($promotion, $applied_any)) {
        break;
      }
    }

    // Cleanup order items added by the BuyXGetY offer in case the promotion
    // no longer applies.
    foreach ($order->getItems() as $order_item) {
      if (!$order_item->getData('owned_by_promotion', FALSE)) {
        continue;
      }
      // Remove order items which had their quantities set to 0.
      if (Calculator::compare($order_item->getQuantity(), '0') === 0) {
        $order->removeItem($order_item);
        $order_item->delete();
      }
    }
  }

  /**
   * Determines whether the given promotion is incompatible with others.
   *
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface $promotion
   *   The promotion being evaluated.
   * @param bool $applied_any
   *   Whether any previous promotion adjustments were applied.
   *
   * @return bool
   *   TRUE if this promotion should block or skip further promotions.
   */
  private function isIncompatibleWithOthers(PromotionInterface $promotion, bool $applied_any): bool {
    // Currently, only COMPATIBLE_NONE blocks others
    return $promotion->getCompatibility() === PromotionInterface::COMPATIBLE_NONE && $applied_any;
  }

}

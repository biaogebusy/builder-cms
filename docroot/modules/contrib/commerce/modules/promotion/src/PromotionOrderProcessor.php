<?php

namespace Drupal\commerce_promotion;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderPreprocessorInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Calculator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Applies promotions to orders during the order refresh process.
 */
class PromotionOrderProcessor implements OrderPreprocessorInterface, OrderProcessorInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new PromotionOrderProcessor object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
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

    $content_langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    /** @var \Drupal\commerce_promotion\Entity\CouponInterface[] $coupons */
    $coupons = $order->get('coupons')->referencedEntities();
    foreach ($coupons as $index => $coupon) {
      $promotion = $coupon->getPromotion();
      $promotion->apply($order);
    }

    // Non-coupon promotions are loaded and applied separately.
    /** @var \Drupal\commerce_promotion\PromotionStorageInterface $promotion_storage */
    $promotion_storage = $this->entityTypeManager->getStorage('commerce_promotion');
    $promotions = $promotion_storage->loadAvailable($order);
    foreach ($promotions as $promotion) {
      if (!$promotion->applies($order)) {
        continue;
      }
      // Ensure the promotion is in the right language, to ensure promotions
      // adjustments labels are correctly translated.
      if ($promotion->hasTranslation($content_langcode)) {
        $promotion = $promotion->getTranslation($content_langcode);
      }
      $promotion->apply($order);
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

}

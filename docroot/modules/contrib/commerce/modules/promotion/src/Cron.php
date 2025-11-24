<?php

namespace Drupal\commerce_promotion;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce\CronInterface;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Cron disables promotions if they expired or their usage was maxed.
 */
class Cron implements CronInterface {

  /**
   * Limits number of promotions per query.
   */
  const QUERY_LIMIT = 50;

  /**
   * Constructs a new Cron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_promotion\PromotionUsageInterface $promotionUsage
   *   The promotion usage service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected PromotionUsageInterface $promotionUsage) {}

  /**
   * {@inheritdoc}
   */
  public function run(): void {

    // Disable any promotions that have passed their end date.
    $promotions = $this->loadExpired();
    if (!empty($promotions)) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      foreach ($promotions as $promotion) {
        $promotion->setEnabled(FALSE);
        $promotion->save();
      }
    }

    // Disable any promotions that have met their max usage.
    $promotions = $this->loadMaxedUsage();
    if (!empty($promotions)) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      foreach ($promotions as $promotion) {
        $promotion->setEnabled(FALSE);
        $promotion->save();
      }
    }
  }

  /**
   * Return active promotions that have passed their end date.
   *
   * @return \Drupal\commerce_promotion\Entity\PromotionInterface[]
   *   The expired promotion entities.
   */
  protected function loadExpired(): array {
    /** @var \Drupal\commerce_promotion\PromotionStorageInterface $promotion_storage */
    $promotion_storage = $this->entityTypeManager->getStorage('commerce_promotion');
    $query = $promotion_storage->getQuery();

    $query
      ->condition('end_date', date(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<')
      ->condition('status', TRUE);

    $query->range(0, static::QUERY_LIMIT);
    $query->accessCheck(FALSE);
    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    return $promotion_storage->loadMultiple($result);
  }

  /**
   * Returns active promotions which have met their maximum usage.
   *
   * @return \Drupal\commerce_promotion\Entity\PromotionInterface[]
   *   Promotions with maxed usage.
   */
  protected function loadMaxedUsage(): array {
    $promotion_storage = $this->entityTypeManager->getStorage('commerce_promotion');
    $query = $promotion_storage->getQuery();

    $query
      ->condition('usage_limit', 1, '>=')
      ->condition('status', TRUE);

    $query->range(0, static::QUERY_LIMIT);
    $query->accessCheck(FALSE);
    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $promotions = $promotion_storage->loadMultiple($result);
    $usage = $this->promotionUsage->loadMultiple($promotions);
    return array_filter($promotions, function (PromotionInterface $promotion) use ($usage) {
      return $usage[$promotion->id()] >= $promotion->getUsageLimit();
    });
  }

}

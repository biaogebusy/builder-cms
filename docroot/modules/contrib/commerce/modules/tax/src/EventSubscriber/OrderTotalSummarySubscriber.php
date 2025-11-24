<?php

namespace Drupal\commerce_tax\EventSubscriber;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Event\OrderSummaryBuildTotalsEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_tax\Entity\TaxType;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters the tax adjustments labels to include the percentage.
 */
class OrderTotalSummarySubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OrderEvents::ORDER_SUMMARY_BUILD_TOTALS  => ['alterTaxAdjustmentLabels', 100],
    ];
  }

  /**
   * Modifies the tax adjustment labels to include the tax percentage.
   *
   * @param \Drupal\commerce_order\Event\OrderSummaryBuildTotalsEvent $event
   *   The event.
   */
  public function alterTaxAdjustmentLabels(OrderSummaryBuildTotalsEvent $event): void {
    $totals = $event->getTotals();
    $adjustments = $totals['adjustments'] ?? [];
    // Collect the tax types that should have their tax adjustment labels altered.
    $tax_type_ids = array_keys(array_filter(TaxType::loadMultiple(), function (TaxType $tax_type) {
      $plugin = $tax_type->getPlugin();
      return $plugin instanceof LocalTaxTypeInterface && !empty($plugin->getConfiguration()['display_tax_rate_in_label']);
    }));

    foreach ($adjustments as &$adjustment) {
      if (!$this->shouldAlterAdjustment(new Adjustment($adjustment), $tax_type_ids)) {
        continue;
      }
      $label = $adjustment['label'];
      $adjustment['label'] = new TranslatableMarkup('@label (@tax_percentage%)', [
        '@label' => $label,
        '@tax_percentage' => Calculator::multiply($adjustment['percentage'], '100'),
      ]);
    }
    if ($totals['adjustments'] !== $adjustments) {
      $totals['adjustments'] = $adjustments;
      $event->setTotals($totals);
    }
  }

  /**
   * Gets whether the adjustment label should be altered.
   *
   * @param \Drupal\commerce_order\Adjustment $adjustment
   *   The adjustment.
   * @param array $tax_type_ids
   *   The tax types IDS for which the adjustment label should be altered.
   *
   * @return bool
   *   Whether the adjustment label should be altered.
   */
  protected function shouldAlterAdjustment(Adjustment $adjustment, array $tax_type_ids): bool {
    if ($adjustment->getType() !== 'tax' ||
      !$adjustment->getSourceId() ||
      !$adjustment->getPercentage()) {
      return FALSE;
    }

    // Check the prefix before the first pipe.
    [$prefix] = explode('|', $adjustment->getSourceId(), 2);
    return in_array($prefix, $tax_type_ids, TRUE);
  }

}

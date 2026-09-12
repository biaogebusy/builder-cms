<?php

namespace Drupal\commerce_price;

use Drupal\Core\Entity\EntityTypeManagerInterface;

class Rounder implements RounderInterface {

  /**
   * Constructs a new Rounder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function round(Price $price, $mode = PHP_ROUND_HALF_UP) {
    $currency_code = $price->getCurrencyCode();
    $currency_storage = $this->entityTypeManager->getStorage('commerce_currency');
    /** @var \Drupal\commerce_price\Entity\CurrencyInterface $currency */
    $currency = $currency_storage->load($currency_code);
    if (!$currency) {
      throw new \InvalidArgumentException(sprintf('Could not load the "%s" currency.', $currency_code));
    }
    $rounded_number = Calculator::round($price->getNumber(), $currency->getFractionDigits(), $mode);

    return new Price($rounded_number, $currency_code);
  }

}

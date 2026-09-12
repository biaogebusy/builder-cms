<?php

namespace Drupal\commerce_price\Repository;

use CommerceGuys\Intl\Currency\Currency;
use CommerceGuys\Intl\Currency\CurrencyRepository as ExternalCurrencyRepository;
use CommerceGuys\Intl\Exception\UnknownCurrencyException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;

/**
 * Defines the currency repository.
 *
 * Provides currencies to the CurrencyFormatter in the expected format,
 * loaded from Drupal's currency storage (commerce_currency entities).
 *
 * Note: This repository doesn't support loading currencies in a non-default
 * locale, since it would be imprecise to map $locale to Drupal's languages.
 */
class CurrencyRepository extends ExternalCurrencyRepository implements CurrencyRepositoryInterface {

  /**
   * Constructs a new CurrencyRepository object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public function get($currency_code, $locale = NULL): Currency {
    /** @var \Drupal\commerce_price\Entity\CurrencyInterface $currency */
    $currency = $this->entityTypeManager->getStorage('commerce_currency')->load($currency_code);
    if (!$currency) {
      throw new UnknownCurrencyException($currency_code);
    }

    return $this->createValueObjectFromEntity($currency);
  }

  /**
   * {@inheritdoc}
   */
  public function getAll($locale = NULL): array {
    $all = [];
    /** @var \Drupal\commerce_price\Entity\CurrencyInterface[] $currencies */
    $currencies = $this->entityTypeManager->getStorage('commerce_currency')->loadMultiple();
    foreach ($currencies as $currency_code => $currency) {
      $all[$currency_code] = $this->createValueObjectFromEntity($currency);
    }

    return $all;
  }

  /**
   * {@inheritdoc}
   */
  public function getList($locale = NULL): array {
    $list = [];
    /** @var \Drupal\commerce_price\Entity\CurrencyInterface[] $entities */
    $currencies = $this->entityTypeManager->getStorage('commerce_currency')->loadMultiple();
    foreach ($currencies as $currency_code => $currency) {
      $list[$currency_code] = $currency->getName();
    }

    return $list;
  }

  /**
   * Creates a currency value object from the given entity.
   *
   * @param \Drupal\commerce_price\Entity\CurrencyInterface $currency
   *   The currency entity.
   *
   * @return \CommerceGuys\Intl\Currency\Currency
   *   The currency value object.
   */
  protected function createValueObjectFromEntity(CurrencyInterface $currency): Currency {
    return new Currency([
      'currency_code' => $currency->getCurrencyCode(),
      'name' => $currency->getName(),
      'numeric_code' => $currency->getNumericCode(),
      'symbol' => $currency->getSymbol(),
      'fraction_digits' => $currency->getFractionDigits(),
      'locale' => $currency->language()->getId(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultFractionDigits(string $currency_code): int {
    $base_definitions = $this->getBaseDefinitions();
    if (!isset($base_definitions[$currency_code])) {
      throw new UnknownCurrencyException($currency_code);
    }

    return $base_definitions[$currency_code][1];
  }

}

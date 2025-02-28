<?php

namespace Drupal\commerce_price;

use CommerceGuys\Intl\Currency\CurrencyRepositoryInterface;

/**
 * Default implementation of the minor units converter.
 */
class MinorUnitsConverter implements MinorUnitsConverterInterface {

  /**
   * The currency repository.
   *
   * @var \Drupal\commerce_price\Repository\CurrencyRepositoryInterface
   */
  protected $currencyRepository;

  /**
   * Constructs a new MinorUnitsConverter object.
   *
   * @param \Drupal\commerce_price\Repository\CurrencyRepositoryInterface $currency_repository
   *   The currency repository.
   */
  public function __construct(CurrencyRepositoryInterface $currency_repository) {
    $this->currencyRepository = $currency_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function fromMinorUnits($amount, $currency_code) : Price {
    $fraction_digits = $this->currencyRepository->getDefaultFractionDigits($currency_code);
    if ($fraction_digits > 0) {
      $amount = Calculator::divide((string) $amount, pow(10, $fraction_digits), $fraction_digits);
    }
    return new Price((string) $amount, $currency_code);
  }

  /**
   * {@inheritdoc}
   */
  public function toMinorUnits(Price $amount) : int {
    $fraction_digits = $this->currencyRepository->getDefaultFractionDigits($amount->getCurrencyCode());
    $number = $amount->getNumber();
    if ($fraction_digits > 0) {
      $number = Calculator::multiply($number, pow(10, $fraction_digits));
    }

    return round($number);
  }

}

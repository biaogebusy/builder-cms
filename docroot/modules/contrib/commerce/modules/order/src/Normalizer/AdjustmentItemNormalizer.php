<?php

namespace Drupal\commerce_order\Normalizer;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_order\Plugin\DataType\AdjustmentItem as AdjustmentItemDataType;
use Drupal\serialization\Normalizer\NormalizerBase;

class AdjustmentItemNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = AdjustmentItemDataType::class;

  /**
   * AdjustmentItemNormalizer constructor.
   *
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currencyFormatter
   *   The currency formatter.
   */
  public function __construct(protected CurrencyFormatterInterface $currencyFormatter) {}

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array | bool | string | int | float | null | \ArrayObject {
    assert($object instanceof AdjustmentItemDataType);
    $adjustment_array = $object->getValue()->toArray();
    $amount = &$adjustment_array['amount'];
    $formatted_price = $this->currencyFormatter->format($amount->getNumber(), $amount->getCurrencyCode());
    $amount = $amount->toArray();
    $amount['formatted'] = $formatted_price;
    return $adjustment_array;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [AdjustmentItemDataType::class => TRUE];
  }

}

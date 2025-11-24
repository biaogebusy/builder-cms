<?php

namespace Drupal\commerce_order\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'commerce_adjustment_total' formatter.
 */
#[FieldFormatter(
  id: "commerce_adjustment_total",
  label: new TranslatableMarkup("Adjustment Total"),
  field_types: ["commerce_adjustment"],
)]
class AdjustmentTotal extends AdjustmentDefault {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['separator']['#access'] = FALSE;
    $form['adjustment_label']['#access'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }
    $adjustment_price = NULL;
    $adjustment_types = array_filter($this->getSetting('adjustment_types')) ?: array_keys($this->getAdjustmentTypes());
    /** @var \Drupal\commerce_order\Plugin\Field\FieldType\AdjustmentItem[] $items */
    foreach ($items as $item) {
      /** @var \Drupal\commerce_order\Adjustment $adjustment */
      $adjustment = $item->getValue()['value'];
      if (!in_array($adjustment->getType(), $adjustment_types, TRUE)) {
        continue;
      }
      if ($adjustment_price === NULL) {
        $adjustment_price = $adjustment->getAmount();
      }
      else {
        $adjustment_price = $adjustment_price->add($adjustment->getAmount());
      }
    }
    if ($adjustment_price === NULL) {
      return [];
    }
    $elements[] = [
      '#markup' => $this->currencyFormatter->format($adjustment_price->getNumber(), $adjustment_price->getCurrencyCode(), $this->getFormattingOptions()),
      '#cache' => [
        'contexts' => [
          'languages:' . LanguageInterface::TYPE_INTERFACE,
          'country',
        ],
      ],
    ];
    return $elements;
  }

}

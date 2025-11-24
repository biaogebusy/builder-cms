<?php

namespace Drupal\commerce_order\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'commerce_adjustment_default' formatter.
 */
#[FieldFormatter(
  id: "commerce_adjustment_table",
  label: new TranslatableMarkup("Adjustment Table"),
  field_types: ["commerce_adjustment"],
)]
class AdjustmentTable extends AdjustmentDefault {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['separator']['#access'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    if ($items->isEmpty()) {
      return [];
    }
    $adjustments = $this->prepareAdjustments($items);
    if (empty($adjustments)) {
      return [];
    }
    if ($this->getSetting('adjustment_label')) {
      $header = [$this->t('Type'), $this->t('Amount')];
    }
    else {
      $header = [$this->t('Amount')];
    }

    $rows = [];
    foreach ($adjustments as $adjustment) {
      $rows[] = [
        'data' => $adjustment,
      ];
    }
    $elements[] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
    return $elements;
  }

}

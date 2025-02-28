<?php

namespace Drupal\fivestar\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'fivestar_percentage' formatter.
 *
 * @FieldFormatter(
 *   id = "fivestar_percentage",
 *   label = @Translation("Percentage (i.e. 92)"),
 *   field_types = {
 *     "fivestar"
 *   },
 *   weight = 2
 * )
 */
class PercentageFormatter extends FivestarFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $values = [
      'user' => 0,
      'average' => 0,
      'count' => 0,
    ];
    $rating = [];
    $users = [];

    $elements = [];
    if (!$items->isEmpty()) {
      /** @var \Drupal\fivestar\Plugin\Field\FieldType\FivestarItem $item */
      foreach ($items as $delta => $item) {
        $value = $item->getValue();
        $rating[] = $value['rating'];
        $users[] = $value['target'];
        $values['count'] += 1;
      }

      if (!empty($rating)) {
        $values['average'] = array_sum($rating) / $values['count'];
        $users = array_unique($users);
        $values['user'] = count($users);
      }

      $elements[] = [
        '#theme' => 'fivestar_formatter_percentage',
        '#instance_settings' => $item->getFieldDefinition()->getSettings(),
        '#item' => $values,
      ];
    }
    // Display a message ('No votes yet') if there are no items.
    else {
      $elements[] = [
        '#markup' => $this->t('No votes yet'),
      ];
    }

    return $elements;
  }

}

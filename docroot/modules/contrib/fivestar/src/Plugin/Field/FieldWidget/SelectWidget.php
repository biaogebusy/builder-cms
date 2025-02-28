<?php

namespace Drupal\fivestar\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'fivestar_select' widget.
 *
 * @FieldWidget(
 *   id = "fivestar_select",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "fivestar"
 *   }
 * )
 */
class SelectWidget extends FivestarWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $settings = $items[$delta]->getFieldDefinition()->getSettings();

    $options = [];
    for ($star = 1; $star <= $settings['stars']; $star++) {
      $this_value = ceil($star * 100 / $settings['stars']);
      $options[$this_value] = $this->t('Give @star/@count', [
        '@star' => $star,
        '@count' => $settings['stars'],
      ]);
    }

    $element += [
      '#type' => 'item',
    ];

    $element['rating'] = [
      '#type' => 'select',
      '#title' => $element['#title'],
      '#empty_option' => $this->t('Select rating:'),
      '#empty_value' => '-',
      '#options' => $options,
      '#required' => $items[$delta]->getFieldDefinition()->isRequired(),
      '#default_value' => $items[$delta]->rating ?? 0,
    ];

    return $element;
  }

}

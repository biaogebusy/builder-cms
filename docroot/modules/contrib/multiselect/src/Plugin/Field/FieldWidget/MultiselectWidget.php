<?php

namespace Drupal\multiselect\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsWidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'multiselect' widget.
 *
 * @FieldWidget(
 *   id = "multiselect",
 *   label = @Translation("Multiselect"),
 *   field_types = {
 *     "list_string",
 *     "list_float",
 *     "list_integer",
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class MultiselectWidget extends OptionsWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\multiselect\Element\Multiselect $element */
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Where parents exist, adjust the name appropriately.
    if (!empty($element["#field_parents"]) && is_array($element["#field_parents"])) {
      $parents = array_merge($element["#field_parents"], [$items->getName()]);
      $name = array_shift($parents);
      $name .= '[' . implode('][', $parents) . ']';
    }
    else {
      $name = $items->getName();
    }

    $element += [
      '#type' => 'multiselect',
      '#name' => $name,
      '#parents' => $element['#field_parents'],
      '#size' => $this->getSetting('size'),
      '#options' => $this->getOptions($items->getEntity()),
      '#default_value' => $this->getSelectedOptions($items, $delta),
      // Do not display a 'multiple' select box if there is only one option.
      '#multiple' => $this->multiple && count($this->options) > 1,
    ];
    return $element;
  }

  /**
   * Form validation handler for widget elements.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    // @todo fix multiselect validation.
    parent::validateElement($element, $form_state);

    // Massage submitted form values.
    // Drupal\Core\Field\WidgetBase::submit() expects values as
    // an array of values keyed by delta first, then by column, while our
    // widgets return the opposite.
    if (is_array($element['#value'])) {
      $values = array_values($element['#value']);
    }
    else {
      $values = [$element['#value']];
    }

    // Filter out the 'none' option. Use a strict comparison, because
    // 0 == 'any string'.
    $index = array_search('_none', $values, TRUE);
    if ($index !== FALSE) {
      unset($values[$index]);
    }

    // Transpose selections from field => delta to delta => field.
    $items = [];
    foreach ($values as $value) {
      $items[] = [$element['#key_column'] => $value];
    }
    $form_state->setValueForElement($element, $items);
  }

}

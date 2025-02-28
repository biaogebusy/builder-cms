<?php

namespace Drupal\charts\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Contains useful form state related methods to use by element plugins.
 */
trait ElementFormStateTrait {

  /**
   * Gets the element state.
   *
   * @param array $parents
   *   The element parents.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array|null
   *   The element state. Possibly NULL if the value is NULL or not all
   *   nested parent keys exist.
   */
  public static function getElementState(array $parents, FormStateInterface $form_state): ?array {
    $parents = array_merge(['element_state', '#parents'], $parents);
    return NestedArray::getValue($form_state->getStorage(), $parents);
  }

  /**
   * Sets the element state.
   *
   * @param array $parents
   *   The element parents.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $element_state
   *   The element state.
   */
  public static function setElementState(array $parents, FormStateInterface $form_state, array $element_state): void {
    $parents = array_merge(['element_state', '#parents'], $parents);
    NestedArray::setValue($form_state->getStorage(), $parents, $element_state);
  }

}

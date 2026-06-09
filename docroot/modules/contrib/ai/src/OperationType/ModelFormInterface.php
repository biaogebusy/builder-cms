<?php

namespace Drupal\ai\OperationType;

use Drupal\Core\Form\FormStateInterface;

/**
 * Model form interface class.
 */
interface ModelFormInterface {

  /**
   * Returns the setup form for the interface.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $config
   *   The configuration array.
   * @param string|null $operation_type
   *   The operation type.
   *
   * @return array
   *   The form array.
   */
  public static function form(&$form, FormStateInterface $form_state, array $config = [], string|NULL $operation_type = NULL): array;

}

<?php

namespace Drupal\field_widget_actions;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormInterface;

/**
 * Interface for field widget actions using a form structure for the modal form.
 */
interface FieldWidgetFormActionInterface extends FieldWidgetActionInterface, FormInterface {

  /**
   * Callback for opening the modal form.
   *
   * This is called by the controller which loads the form via the form wrapper.
   * It is required. Comparatively the <code>::openModalCallback()</code> in
   * the form action base class is triggered as a submit callback and is
   * not required if the submit callback is instead changed to some other
   * callback.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response.
   */
  public function openModalForm(): AjaxResponse;

}

<?php

namespace Drupal\acquia_connector\Event;

use Drupal\acquia_connector\Subscription;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Event to let individual products create configurations within Connector.
 */
class AcquiaProductSettingsEvent extends EventBase {

  use StringTranslationTrait;

  /**
   * The Connector Settings Form.
   *
   * @var array
   */
  protected $form;

  /**
   * The Connector Settings Form.
   *
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected $formState;

  /**
   * Acquia Subscription.
   *
   * @var \Drupal\acquia_connector\Subscription
   */
  protected $subscription;

  /**
   * Pass in connector config by default to all events.
   *
   * @param array $form
   *   The Connector Form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Form State.
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   Acquia Subscription Service.
   */
  public function __construct(array $form, FormStateInterface $form_state, Subscription $subscription) {
    $this->form = $form;
    $this->formState = $form_state;
    $this->subscription = $subscription;
  }

  /**
   * Gets the form attached to the Event.
   *
   * @return array
   *   The settings form.
   */
  public function getForm() {
    return $this->form;
  }

  /**
   * Gets the form state values attached to the Event.
   *
   * @return array
   *   The settings form_state values.
   */
  public function getFormState() {
    return $this->formState->getValues();
  }

  /**
   * Sets the 'product settings' key for the Connector form.
   *
   * @param string $product
   *   Product Name.
   * @param string $product_machine_name
   *   Product Machine Name.
   * @param array $product_setting_form
   *   Product Settings form.
   */
  public function setProductSettings($product, $product_machine_name, array $product_setting_form) {
    $this->form['product_settings'][$product_machine_name] = [
      '#type' => 'fieldset',
      '#title' => $this->t("%product_name", ['%product_name' => $product]),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    $this->form['product_settings'][$product_machine_name]['settings'] = $product_setting_form;
  }

  /**
   * Retreives third party setting from Connector.
   *
   * @return mixed
   *   A reference to the value for that property, or NULL if the property does
   *   not exist.
   */
  public function getThirdPartySetting($module_name, $form_value) {
    return $this->formState->get([
      'product_settings',
      $module_name,
      'settings',
      $form_value,
    ]);
  }

  /**
   * Retrieve the Acquia Subscription.
   *
   * @return \Drupal\acquia_connector\Subscription
   *   The Subscription.
   */
  public function getSubscription() {
    return $this->subscription;
  }

  /**
   * Alters the 'product settings' submission for the Connector form.
   *
   * @param array $form_value
   *   Form State values.
   */
  public function alterProductSettingsSubmit(array $form_value) {
    $this->formState->setValues($form_value);
  }

}

<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;

/**
 * Provides the contact information pane.
 */
#[CommerceCheckoutPane(
  id: "contact_information",
  label: new TranslatableMarkup('Contact information'),
  default_step: "order_information",
  wrapper_element: "fieldset",
)]
class ContactInformation extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'double_entry' => FALSE,
      'always_display' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $parent_summary = parent::buildConfigurationSummary();
    if (!empty($this->configuration['double_entry'])) {
      $summary = $this->t('Require double entry of email: Yes');
    }
    else {
      $summary = $this->t('Require double entry of email: No');
    }
    $summary .= '<br>';
    if (!empty($this->configuration['always_display'])) {
      $summary .= $this->t('Always display email fields: Yes');
    }
    else {
      $summary .= $this->t('Always display email fields: No');
    }

    return $parent_summary ? implode('<br>', [$parent_summary, $summary]) : $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['double_entry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require double entry of email'),
      '#description' => $this->t('Forces anonymous users to enter their email in two consecutive fields, which must have identical values.'),
      '#default_value' => $this->configuration['double_entry'],
    ];
    $form['always_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always display email fields'),
      '#description' => $this->t('Allows authenticated users to view and change their email address for the order.'),
      '#default_value' => $this->configuration['always_display'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['double_entry'] = !empty($values['double_entry']);
      $this->configuration['always_display'] = !empty($values['always_display']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return !empty($this->configuration['always_display']) || $this->order->getCustomer()->isAnonymous();
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    return [
      '#plain_text' => $this->order->getEmail(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $this->order->getEmail(),
      '#required' => TRUE,
    ];
    if ($this->configuration['double_entry']) {
      $pane_form['email_confirm'] = [
        '#type' => 'email',
        '#title' => $this->t('Confirm email'),
        '#default_value' => $this->order->getEmail(),
        '#required' => TRUE,
      ];
    }

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if ($this->configuration['double_entry'] && $values['email'] != $values['email_confirm']) {
      $form_state->setError($pane_form, $this->t('The specified emails do not match.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (!$this->order->getCustomer()->isAnonymous()) {
      // Custom flag used in the OrderRefresh service to ensure the email
      // isn't synced with the customer email.
      $this->order->setData('customer_email_overridden', TRUE);
    }
    $this->order->setEmail($values['email']);
  }

}

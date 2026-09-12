<?php

namespace Drupal\commerce_order\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to add/edit billing information.
 */
class OrderBillingInformation extends OrderStaticDisplayFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Don't hide the billing profile form behind
    // the "Add billing information" button.
    $form_state->set('hide_profile_form', FALSE);
    $form = parent::form($form, $form_state);
    $form['#title'] = $this->t('Billing information');
    // Hide Cancel button.
    if (isset($form['billing_profile']['widget'][0]['hide_profile_form'])) {
      $form['billing_profile']['widget'][0]['hide_profile_form']['#access'] = FALSE;
    }
    return $form;
  }

  /**
   * Returns the list fo required components on this form.
   */
  protected function getRequiredComponents(): array {
    return [
      'billing_profile' => [
        'type' => 'commerce_billing_profile',
        'settings' => [],
      ],
    ];
  }

}

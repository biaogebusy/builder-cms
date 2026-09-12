<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;

/**
 * Provides the customer comments pane.
 */
#[CommerceCheckoutPane(
  id: "customer_comments",
  label: new TranslatableMarkup("Comments"),
  admin_description: new TranslatableMarkup("Allows customers to enter a comment for the order."),
  default_step: "_disabled",
  wrapper_element: "fieldset",
)]
class CustomerComments extends CheckoutPaneBase implements CheckoutPaneInterface {

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $summary = parent::buildPaneSummary();
    if ($comments = $this->order->getCustomerComments()) {
      $summary[] = [
        '#type' => 'inline_template',
        '#template' => '{{ comments|nl2br }}',
        '#context' => [
          'comments' => $comments,
        ],
      ];
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['comments'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comments'),
      '#title_display' => 'invisible',
      '#default_value' => $this->order->getCustomerComments(),
    ];

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    parent::submitPaneForm($pane_form, $form_state, $complete_form);
    if (!empty($form_state->getValue('customer_comments')['comments'])) {
      $this->order->setCustomerComments($form_state->getValue('customer_comments')['comments']);
    }
    else {
      $this->order->set('customer_comments', NULL);
    }
  }

}

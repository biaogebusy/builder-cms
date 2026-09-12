<?php

namespace Drupal\commerce_payment\Plugin\Field\FieldFormatter;

use Drupal\commerce_order\Plugin\Field\FieldFormatter\BillingInformationFormatter as BaseBillingInformationFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Extend the BillingInformationFormatter to show the payment information.
 */
class BillingInformationFormatter extends BaseBillingInformationFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'show_payment_information' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['show_payment_information'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show payment information'),
      '#default_value' => $this->getSetting('show_payment_information'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Show payment information: @show_payment_information', [
      '@show_payment_information' => $this->getSetting('show_payment_information') ? $this->t('Yes') : $this->t('No'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    if (!$this->getSetting('show_payment_information')) {
      return $elements;
    }

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $items->getEntity();
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payments = $payment_storage->loadMultipleByOrder($order);

    $url = Url::fromRoute('entity.commerce_payment.collection', ['commerce_order' => $order->id()]);
    if (empty($payments)) {
      // Only output a link to add a payment if there is a billing profile.
      if ($order->getBillingProfile() && $url->access()) {
        $elements[0]['#slots']['card_footer']['add_payment_link'] = [
          '#type' => 'link',
          '#title' => $this->t('Add payment →'),
          '#url' => $url,
        ];
      }
      return $elements;
    }

    $payment = end($payments);
    $view_builder = $this->entityTypeManager->getViewBuilder('commerce_payment');
    $payment_information = [
      'payment' => $view_builder->view($payment, 'order_view'),
    ];

    if ($url->access()) {
      $elements[0]['#slots']['card_footer']['manage_payments_link'] = [
        '#type' => 'link',
        '#title' => $this->formatPlural(count($payments), 'Manage payment →', 'Manage payments →'),
        '#url' => $url,
      ];
    }
    $elements[0]['#slots']['card_content']['payment_information'] = $payment_information;

    return $elements;
  }

}

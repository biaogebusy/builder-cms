<?php

namespace Drupal\commerce_promotion\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the coupon redemption pane.
 */
#[CommerceCheckoutPane(
  id: "coupon_redemption",
  label: new TranslatableMarkup("Coupon redemption"),
  admin_description: new TranslatableMarkup("Provides the coupon redemption form."),
  default_step: "_sidebar",
  wrapper_element: "container",
)]
class CouponRedemption extends CheckoutPaneBase {

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    return $this->order->getState()->getId() === 'draft';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'allow_multiple' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    $parent_summary = parent::buildConfigurationSummary();
    if ($this->configuration['allow_multiple']) {
      $summary = $this->t('Allows multiple coupons: Yes');
    }
    else {
      $summary = $this->t('Allows multiple coupons: No');
    }

    return $parent_summary ? implode('<br>', [$parent_summary, $summary]) : $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['allow_multiple'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow multiple coupons to be redeemed'),
      '#default_value' => $this->configuration['allow_multiple'],
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
      $this->configuration['allow_multiple'] = $values['allow_multiple'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $inline_form = $this->inlineFormManager->createInstance('coupon_redemption', [
      'order_id' => $this->order->id(),
      'max_coupons' => $this->configuration['allow_multiple'] ? NULL : 1,
    ]);

    $pane_form['form'] = [
      '#parents' => array_merge($pane_form['#parents'], ['form']),
    ];
    $pane_form['form'] = $inline_form->buildInlineForm($pane_form['form'], $form_state);

    // If the details element is used, open it if there are any applied coupons:
    if ($this->getWrapperElement() === 'details' && !empty($this->order->get('coupons')->getValue())) {
      $pane_form['#open'] = TRUE;
    }
    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    // The form was submitted with a non-applied coupon in the input field,
    // mapped to a coupon ID in CouponRedemptionForm::validateForm().
    if (!empty($pane_form['form']['code']['#coupon_id'])) {
      $this->order->get('coupons')->appendItem($pane_form['form']['code']['#coupon_id']);
    }
  }

}

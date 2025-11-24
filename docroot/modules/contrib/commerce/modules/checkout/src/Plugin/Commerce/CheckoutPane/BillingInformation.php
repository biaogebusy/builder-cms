<?php

namespace Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the billing information pane.
 */
#[CommerceCheckoutPane(
  id: "billing_information",
  label: new TranslatableMarkup('Billing information'),
  default_step: "order_information",
  wrapper_element: "fieldset",
)]
class BillingInformation extends CheckoutPaneBase implements CheckoutPaneInterface {

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
  public function buildPaneSummary() {
    $summary = [];
    if ($profile = $this->order->getBillingProfile()) {
      $profile_view_builder = $this->entityTypeManager->getViewBuilder('profile');
      $summary = $profile_view_builder->view($profile, 'default');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $profile = $this->order->getBillingProfile();
    if (!$profile) {
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profile = $profile_storage->create([
        'type' => 'customer',
        'uid' => 0,
      ]);
    }
    $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
      'profile_scope' => 'billing',
      'available_countries' => $this->order->getStore()->getBillingCountries(),
      'address_book_uid' => $this->order->getCustomerId(),
      // Don't copy the profile to address book until the order is placed.
      'copy_on_save' => FALSE,
    ], $profile);

    $pane_form['profile'] = [
      '#parents' => array_merge($pane_form['#parents'], ['profile']),
      '#inline_form' => $inline_form,
    ];
    $pane_form['profile'] = $inline_form->buildInlineForm($pane_form['profile'], $form_state);

    return $pane_form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
    $inline_form = $pane_form['profile']['#inline_form'];
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $inline_form->getEntity();
    $this->order->setBillingProfile($profile);
  }

}

<?php

namespace Drupal\commerce_payment\PluginForm;

use Drupal\commerce\AjaxFormTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentMethodFormBase extends PaymentGatewayFormBase implements ContainerInjectionInterface {

  use AjaxFormTrait;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The inline form manager.
   *
   * @var \Drupal\commerce\InlineFormManager
   */
  protected $inlineFormManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The route admin context to determine whether a route is an admin one.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->currentStore = $container->get('commerce_store.current_store');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->inlineFormManager = $container->get('plugin.manager.commerce_inline_form');
    $instance->logger = $container->get('logger.channel.commerce_payment');
    $instance->adminContext = $container->get('router.admin_context');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    $form['#attached']['library'][] = 'commerce_payment/payment_method_form';
    $form['#tree'] = TRUE;
    if ($payment_gateway_plugin->collectsBillingInformation()) {
      $billing_profile = $payment_method->getBillingProfile();
      if (!$billing_profile) {
        $profile_storage = $this->entityTypeManager->getStorage('profile');
        $billing_profile = $profile_storage->create([
          'type' => 'customer',
          'uid' => 0,
        ]);
      }
      $store = $this->currentStore->getStore();
      $inline_form = $this->inlineFormManager->createInstance('customer_profile', [
        'profile_scope' => 'billing',
        'available_countries' => $store ? $store->getBillingCountries() : [],
        'address_book_uid' => $payment_method->getOwnerId(),
        'admin' => $this->adminContext->isAdminRoute(),
      ], $billing_profile);

      $form['billing_information'] = [
        '#parents' => array_merge($form['#parents'], ['billing_information']),
        '#inline_form' => $inline_form,
      ];
      $form['billing_information'] = $inline_form->buildInlineForm($form['billing_information'], $form_state);

      // Ensure the entire payment method form is refreshed when the selected
      // address changes.
      if (isset($form['billing_information']['select_address'])) {
        $form['billing_information']['select_address']['#ajax'] = [
          'callback' => [get_class($this), 'ajaxRefreshForm'],
          'element' => $form['#parents'],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $this->plugin;
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;

    if ($payment_gateway_plugin->collectsBillingInformation()) {
      /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\EntityInlineFormInterface $inline_form */
      $inline_form = $form['billing_information']['#inline_form'];
      /** @var \Drupal\profile\Entity\ProfileInterface $billing_profile */
      $billing_profile = $inline_form->getEntity();
      $payment_method->setBillingProfile($billing_profile);
    }
  }

}

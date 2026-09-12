<?php

namespace Drupal\commerce_checkout\Hook;

use Drupal\commerce\EntityHelper;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Form\ViewsForm;

/**
 * Hook implementations for Commerce Checkout.
 */
class CommerceCheckoutHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceCheckoutHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    if ($entity_type->id() === 'commerce_order') {
      $fields['checkout_flow'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel($this->t('Checkout flow'))
        ->setSetting('target_type', 'commerce_checkout_flow')
        ->setSetting('handler', 'default')
        ->setDisplayOptions('form', [
          'region' => 'hidden',
        ])
        ->setDisplayConfigurable('view', FALSE)
        ->setDisplayConfigurable('form', FALSE);

      // @todo Implement a custom widget that shows itself when the flow is set
      // and allows a step to be chosen from a dropdown.
      $fields['checkout_step'] = BaseFieldDefinition::create('string')
        ->setLabel($this->t('Checkout step'))
        ->setDisplayOptions('form', [
          'region' => 'hidden',
        ])
        ->setDisplayConfigurable('view', FALSE)
        ->setDisplayConfigurable('form', FALSE);
    }
    return $fields;
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state): void {
    if (!($form_state->getFormObject() instanceof ViewsForm)) {
      return;
    }
    /** @var \Drupal\views\ViewExecutable $view */
    $view = reset($form_state->getBuildInfo()['args']);
    $tags = Tags::explode($view->storage->get('tag'));
    // Only add the Checkout button if the cart form view has order items.
    if (in_array('commerce_cart_form', $tags, TRUE) && !empty($view->result)) {
      $form['actions']['checkout'] = [
        '#type' => 'submit',
        '#value' => $this->t('Checkout'),
        '#weight' => 5,
        '#access' => $this->currentUser->hasPermission('access checkout'),
        '#submit' => array_merge($form['#submit'], [[static::class, 'orderItemViewsFormSubmit']]),
        '#order_id' => $view->argument['order_id']->value[0],
        '#update_cart' => TRUE,
        '#show_update_message' => FALSE,
      ];
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'commerce_order_type_form'.
   */
  #[Hook('form_commerce_order_type_form_alter')]
  public function formCommerceOrderTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $form_state->getFormObject()->getEntity();
    $storage = $this->entityTypeManager->getStorage('commerce_checkout_flow');
    $checkout_flows = $storage->loadMultiple();

    $form['commerce_checkout'] = [
      '#type' => 'details',
      '#title' => $this->t('Checkout settings'),
      '#weight' => 5,
      '#open' => TRUE,
    ];
    $form['commerce_checkout']['checkout_flow'] = [
      '#type' => 'select',
      '#title' => $this->t('Checkout flow'),
      '#options' => EntityHelper::extractLabels($checkout_flows),
      '#default_value' => $order_type->getThirdPartySetting('commerce_checkout', 'checkout_flow', 'default'),
      '#required' => TRUE,
    ];
    $form['actions']['submit']['#submit'][] = [static::class, 'orderTypeFormSubmit'];
  }

  /**
   * Submission handler for commerce_checkout_form_commerce_order_type_form_alter().
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function orderTypeFormSubmit(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $form_state->getFormObject()->getEntity();
    $settings = $form_state->getValue(['commerce_checkout']);
    $order_type->setThirdPartySetting('commerce_checkout', 'checkout_flow', $settings['checkout_flow']);
    $order_type->save();
  }

  /**
   * Submit handler used to redirect to the checkout page.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function orderItemViewsFormSubmit(array $form, FormStateInterface $form_state): void {
    $order_id = $form_state->getTriggeringElement()['#order_id'];
    $form_state->setRedirect('commerce_checkout.form', ['commerce_order' => $order_id]);
  }

}

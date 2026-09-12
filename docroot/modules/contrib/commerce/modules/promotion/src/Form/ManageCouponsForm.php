<?php

namespace Drupal\commerce_promotion\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to manage coupons in the order.
 */
class ManageCouponsForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_order_manage_coupons_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_state->get('order') ?? $this->getRouteMatch()->getParameter('commerce_order');
    $form_state->set('order', $order);
    $form['errors'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'manage-coupons-form-errors',
      ],
      '#weight' => -10,
    ];

    $form['applied_coupons'] = $this->getAppliedCoupons($order);
    $form['add_coupon'] = $this->getApplyCouponWidget();

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxReloadForm',
      ],
      '#name' => 'save',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $order->toUrl(),
      '#attributes' => ['class' => ['button', 'dialog-cancel']],
    ];
    $form['#after_build'] = ['::clearValues'];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_state->get('order');
    $coupon_id = $form_state->getValue('coupon');
    if ($coupon_id) {
      $order->get('coupons')->appendItem($coupon_id);
    }
    $order->save();
  }

  /**
   * Clears values after the successful ajax submission.
   */
  public function clearValues(array $element, FormStateInterface $form_state) {
    if ($form_state->hasAnyErrors()) {
      return $element;
    }
    $user_input = &$form_state->getUserInput();
    $user_input['coupon'] = '';
    return $element;
  }

  /**
   * Returns the list of applied coupons.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order entity.
   */
  protected function getAppliedCoupons(OrderInterface $order): array {
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $coupons_field */
    $coupons_field = $order->get('coupons');
    if ($coupons_field->isEmpty()) {
      return [];
    }
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Applied coupons'),
    ];

    /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
    foreach ($coupons_field->referencedEntities() as $delta => $coupon) {
      $element[$delta] = [
        '#type' => 'container',
        'code' => [
          '#plain_text' => $coupon->label(),
        ],
        'remove_button' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove coupon'),
          '#name' => 'remove_coupon_' . $delta,
          '#coupon_id' => $coupon->id(),
          '#ajax' => [
            'callback' => '::ajaxReloadForm',
          ],
          '#submit' => ['::removeCoupon'],
          '#attributes' => [
            'class' => ['button--danger', 'button--small'],
          ],
          '#limit_validation_errors' => [],
        ],
      ];
    }

    return $element;
  }

  /**
   * Returns the "Apply coupon" element.
   */
  protected function getApplyCouponWidget(): array {
    $element = [
      '#type' => 'fieldset',
    ];
    $element['coupon'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Coupon code'),
      '#target_type' => 'commerce_promotion_coupon',
      '#selection_settings' => [
        'match_operator' => 'CONTAINS',
      ],
      '#placeholder' => $this->t('Enter coupon code'),
      '#size' => 30,
      '#element_validate' => [
        [EntityAutocomplete::class, 'validateEntityAutocomplete'],
        '::validateCouponCode',
      ],
    ];
    $element['apply_coupon'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply coupon'),
      '#ajax' => [
        'callback' => '::ajaxReloadForm',
      ],
      '#submit' => ['::applyCoupon'],
      '#name' => 'apply_coupon',
    ];
    return $element;
  }

  /**
   * Validates the coupon code.
   */
  public function validateCouponCode(array $element, FormStateInterface $form_state, array $form) {
    if (!empty($form_state->getError($element))) {
      return;
    }

    // Skip validation for the "Remove coupon" action or empty coupon ID.
    $name = $form_state->getTriggeringElement()['#name'] ?? NULL;
    $coupon_id = $form_state->getValue('coupon');
    if (str_starts_with($name, 'remove_coupon') || empty($coupon_id)) {
      return;
    }
    /** @var \Drupal\commerce_promotion\CouponStorageInterface $coupon_storage */
    $coupon_storage = $this->entityTypeManager->getStorage('commerce_promotion_coupon');
    /** @var \Drupal\commerce_promotion\Entity\CouponInterface|null $coupon */
    $coupon = $coupon_storage->load($coupon_id);
    if (!$coupon?->isEnabled()) {
      $form_state->setError($element, $this->t('The provided coupon code is invalid.'));
      return;
    }

    $order = $form_state->get('order');
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $coupons_field */
    $coupons_field = $order->get('coupons');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order_coupon */
    foreach ($coupons_field->referencedEntities() as $order_coupon) {
      $promotion = $order_coupon->getPromotion();
      if (!$promotion->isMultipleCouponsAllowed() && $promotion->id() == $coupon->getPromotionId()) {
        $form_state->setError($element, $this->t('The provided coupon code cannot be applied to your order.'));
        return;
      }
    }

    if (!$coupon->available($order)) {
      $form_state->setError($element, $this->t('The provided coupon code is not available. It may have expired or already been used.'));
      return;
    }
    if (!$coupon->getPromotion()->applies($order)) {
      $form_state->setError($element, $this->t('The provided coupon code cannot be applied to your order.'));
    }
  }

  /**
   * Ajax callback to refresh the form.
   */
  public function ajaxReloadForm(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('form[id^="commerce-order-manage-coupons-form"]', $form));
    if ($form_state->hasAnyErrors()) {
      foreach ($form_state->getErrors() as $error) {
        $response->addCommand(new MessageCommand($error, '#manage-coupons-form-errors', ['type' => 'error']));
      }

      // Clear errors.
      $this->messenger()->deleteByType('error');
      $form_state->clearErrors();
    }
    else {
      $name = $form_state->getTriggeringElement()['#name'];
      if ($name === 'apply_coupon' && !empty($form_state->getValue('coupon'))) {
        $response->addCommand(new MessageCommand($this->t('Coupon successfully redeemed!'), '#manage-coupons-form-errors'));
      }
      elseif ($name === 'save') {
        $order = $form_state->get('order');
        $response->addCommand(new RedirectCommand($order->toUrl()->toString()));
      }
    }

    return $response;
  }

  /**
   * Submit callback for the "Apply coupon" button.
   */
  public function applyCoupon(array $form, FormStateInterface $form_state): void {
    if ($form_state->hasAnyErrors()) {
      return;
    }
    $coupon_id = $form_state->getValue('coupon');
    if ($coupon_id) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $form_state->get('order');
      $order->get('coupons')->appendItem($coupon_id);
      $form_state->set('order', $order);
      $form_state->setRebuild();
    }
  }

  /**
   * Submit callback for the "Remove coupon" button.
   */
  public function removeCoupon(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $form_state->get('order');
    $coupon_ids = array_column($order->get('coupons')->getValue(), 'target_id');
    $coupon_index = array_search($triggering_element['#coupon_id'], $coupon_ids);
    $order->get('coupons')->removeItem($coupon_index);
    $form_state->set('order', $order);
    $form_state->setRebuild();
  }

}

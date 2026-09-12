<?php

namespace Drupal\commerce_payment\Hook;

use Drupal\commerce_payment\Plugin\Field\FieldFormatter\BillingInformationFormatter;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for Commerce Payment.
 */
class CommercePaymentHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_commerce_checkout_pane_info_alter().
   */
  #[Hook('commerce_checkout_pane_info_alter')]
  public function commerceCheckoutPaneInfoAlter(&$definitions): void {
    // The payment_information pane replaces the billing_information one.
    unset($definitions['billing_information']);
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() === 'commerce_order') {
      $fields['payment_gateway'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Payment gateway'))
        ->setDescription(t('The payment gateway.'))
        ->setSetting('target_type', 'commerce_payment_gateway');

      $fields['payment_method'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Payment method'))
        ->setDescription(t('The payment method.'))
        ->setSetting('target_type', 'commerce_payment_method');

      return $fields;
    }

    return [];
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): array {
    $operations = [];
    if ($entity->getEntityTypeId() === 'commerce_order') {
      $url = Url::fromRoute('entity.commerce_payment.collection', [
        'commerce_order' => $entity->id(),
      ]);
      if ($url->access()) {
        $operations['payments'] = [
          'title' => $this->t('Payments'),
          'url' => $url,
          'weight' => 50,
        ];
      }
    }

    return $operations;
  }

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    $data['views']['commerce_payment_total_summary'] = [
      'title' => $this->t('Payment total summary'),
      'area' => [
        'id' => 'commerce_payment_total_summary',
      ],
    ];
    return $data;
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('commerce_payment_method_view')]
  public function commercePaymentMethodView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, $view_mode): void {
    if ($entity->bundle() == 'credit_card') {
      $build['#attached']['library'][] = 'commerce_payment/payment_method_icons';
    }
  }

  /**
   * Implements hook_field_formatter_info_alter().
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    $info['commerce_billing_information']['class'] = BillingInformationFormatter::class;
    $info['commerce_billing_information']['provider'] = 'commerce_payment';
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(&$definitions): void {
    $definitions['field.formatter.settings.commerce_billing_information']['mapping']['show_payment_information'] = [
      'type' => 'boolean',
      'label' => 'Show payment information',
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_commerce_checkout_flow_edit_form_alter')]
  public function formCommerceCheckoutFlowEditFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['#validate'][] = [static::class, 'checkoutFlowFormValidate'];
  }

  /**
   * Validate callback for the checkout flow form.
   *
   * Prevents users from putting the PaymentInformation and PaymentProcess panes
   * on the same step, which would result in an infinite loop.
   */
  public static function checkoutFlowFormValidate(array $form, FormStateInterface $form_state): void {
    $pane_configuration = $form_state->getValue(['configuration', 'panes']);
    if (!isset($pane_configuration['payment_information'], $pane_configuration['payment_process'])) {
      return;
    }
    $payment_information_step = $pane_configuration['payment_information']['step_id'];
    $payment_process_step = $pane_configuration['payment_process']['step_id'];
    if ($payment_information_step !== '_disabled' && $payment_information_step === $payment_process_step) {
      $form_state->setError($form, t('<em>Payment information</em> and <em>Payment process</em> panes need to be on separate steps.'));
    }
  }

}

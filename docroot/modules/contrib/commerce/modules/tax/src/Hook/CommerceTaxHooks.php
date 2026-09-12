<?php

namespace Drupal\commerce_tax\Hook;

use Drupal\commerce_tax\TaxableType;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldStorageConfigInterface;

/**
 * Hook implementations for Commerce Tax.
 */
class CommerceTaxHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceTaxHooks object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() === 'commerce_store') {
      $fields['prices_include_tax'] = BaseFieldDefinition::create('boolean')
        ->setLabel($this->t('Prices are entered with taxes included.'))
        ->setDisplayOptions('form', [
          'type' => 'boolean_checkbox',
          'settings' => [
            'display_label' => TRUE,
          ],
          'weight' => 3,
        ])
        ->setDisplayConfigurable('view', TRUE)
        ->setDisplayConfigurable('form', TRUE)
        ->setDefaultValue(FALSE);

      $fields['tax_registrations'] = BaseFieldDefinition::create('list_string')
        ->setLabel($this->t('Tax registrations'))
        ->setDescription($this->t('The countries where the store is additionally registered to collect taxes. For further details see the <a href="https://docs.drupalcommerce.org/v2/user-guide/taxes/" target="_blank">Commerce Tax documentation</a>.'))
        ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
        ->setSetting('display_description', TRUE)
        ->setSetting('allowed_values_function', ['\Drupal\commerce_store\Entity\Store', 'getAvailableCountries'])
        ->setDisplayOptions('form', [
          'type' => 'options_select',
          'weight' => 4,
        ])
        ->setDisplayConfigurable('view', TRUE)
        ->setDisplayConfigurable('form', TRUE);

      return $fields;
    }

    return [];
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   *
   * Forbids the profile "tax_number" field from being deletable.
   * This is an alternative to locking the field which still leaves
   * the field editable.
   */
  #[Hook('field_storage_config_access')]
  public function fieldStorageConfigAccess(FieldStorageConfigInterface $field_storage, $operation): AccessResultInterface {
    if ($field_storage->id() === 'profile.tax_number' && $operation === 'delete') {
      // Allow deleting the tax number field if there are no profiles with tax
      // numbers set in the DB.
      $profiles_count = $this->entityTypeManager->getStorage('profile')
        ->getQuery()
        ->accessCheck(FALSE)
        ->exists('tax_number')
        ->count()
        ->execute();
      return AccessResult::allowedIf($profiles_count === 0);
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for 'commerce_store_form'.
   */
  #[Hook('form_commerce_store_form_alter')]
  public function formCommerceStoreFormAlter(&$form, FormStateInterface $form_state): void {
    if (isset($form['prices_include_tax']) || isset($form['tax_registrations'])) {
      $form['tax_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Tax settings'),
        '#weight' => 90,
        '#open' => TRUE,
        '#group' => 'advanced',
      ];
      $form['prices_include_tax']['#group'] = 'tax_settings';
      $form['tax_registrations']['#group'] = 'tax_settings';
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'commerce_order_item_type_form'.
   */
  #[Hook('form_commerce_order_item_type_form_alter')]
  public function formCommerceOrderItemTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_item_type */
    $order_item_type = $form_state->getFormObject()->getEntity();

    $form['commerce_tax'] = [
      '#type' => 'container',
      '#weight' => 5,
    ];
    $form['commerce_tax']['taxable_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Taxable type'),
      '#options' => TaxableType::getLabels(),
      '#default_value' => $order_item_type->getThirdPartySetting('commerce_tax', 'taxable_type', TaxableType::getDefault()),
      '#required' => TRUE,
    ];
    $form['actions']['submit']['#submit'][] = [static::class, 'orderItemTypeFormSubmit'];
  }

  /**
   * Submission handler for commerce_tax_form_commerce_order_item_type_form_alter().
   */
  public static function orderItemTypeFormSubmit(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_item_type */
    $order_item_type = $form_state->getFormObject()->getEntity();
    $settings = $form_state->getValue(['commerce_tax']);
    $order_item_type->setThirdPartySetting('commerce_tax', 'taxable_type', $settings['taxable_type']);
    $order_item_type->save();
  }

  /**
   * Implements hook_field_formatter_info_alter().
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    $info['string']['field_types'][] = 'commerce_tax_number';
  }

  /**
   * Implements hook_field_widget_info_alter().
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    $info['string_textfield']['field_types'][] = 'commerce_tax_number';
  }

}

<?php

namespace Drupal\commerce_promotion\Hook;

use Drupal\commerce\CronInterface;
use Drupal\commerce_promotion\PromotionUsageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for Commerce Promotion.
 */
class CommercePromotionHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommercePromotionHooks object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\commerce\CronInterface $cron
   *   The promotion cron.
   * @param \Drupal\commerce_promotion\PromotionUsageInterface $promotionUsage
   *   The promotion usage.
   */
  public function __construct(
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    #[Autowire(service: 'commerce_promotion.cron')]
    protected readonly CronInterface $cron,
    protected readonly PromotionUsageInterface $promotionUsage,
  ) {
  }

  /**
   * Implements hook_commerce_condition_info_alter().
   */
  #[Hook('commerce_condition_info_alter')]
  public function commerceConditionInfoAlter(&$definitions): void {
    foreach ($definitions as &$definition) {
      // Force all order item conditions to have the same category.
      // This prevents them from accidentally showing in vertical tabs
      // in the promotion offer UI.
      if ($definition['entity_type'] === 'commerce_order_item') {
        $definition['category'] = $this->t('Products');
      }
    }
  }

  /**
   * Implements hook_user_presave().
   */
  #[Hook('user_presave')]
  public function userPresave(UserInterface $account): void {
    if ($account->isNew()) {
      return;
    }

    $old_mail = $account->original->getEmail();
    $new_mail = $account->getEmail();
    if ($old_mail && $new_mail && $old_mail != $new_mail) {
      $this->promotionUsage->reassign($old_mail, $new_mail);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Removes core's built-in formatters from views field options for
   * promotion start_date and end_date fields, since they perform timezone
   * conversion. The "Default (Store timezone)" formatter should be used instead.
   */
  #[Hook('form_views_ui_config_item_form_alter')]
  public function formViewsUiConfigItemFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\views\Plugin\views\field\EntityField $handler */
    $handler = $form_state->get('handler');
    if ($handler instanceof EntityField && !empty($handler->definition['entity_type'])) {
      $entity_type_id = $handler->definition['entity_type'];
      $field_name = $handler->definition['field_name'] ?? NULL;
      $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      $field_definition = $field_definitions[$field_name] ?? NULL;
      if ($entity_type_id == 'commerce_promotion' && $field_definition?->getType() == 'datetime') {
        unset($form['options']['type']['#options']['datetime_custom']);
        unset($form['options']['type']['#options']['datetime_default']);
        unset($form['options']['type']['#options']['datetime_plain']);
      }
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
    $field_definition = $context['items']->getFieldDefinition();
    $field_name = $field_definition->getName();
    $entity_type = $field_definition->getTargetEntityTypeId();
    $widget_name = $context['widget']->getPluginId();
    if ($field_name == 'condition_operator' && $entity_type == 'commerce_promotion' && $widget_name == 'options_buttons') {
      // Hide the label.
      $element['#title_display'] = 'invisible';
    }
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() === 'commerce_order') {
      $fields['coupons'] = BaseFieldDefinition::create('entity_reference')
        ->setLabel(t('Coupons'))
        ->setDescription(t('Coupons which have been applied to order.'))
        ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
        ->setRequired(FALSE)
        ->setSetting('target_type', 'commerce_promotion_coupon')
        ->setSetting('handler', 'default')
        ->setTranslatable(FALSE)
        ->addConstraint('CouponValid')
        ->setDisplayConfigurable('view', TRUE)
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'entity_reference_label',
          'settings' => [
            'link' => TRUE,
          ],
        ])
        ->setDisplayConfigurable('form', TRUE);

      return $fields;
    }

    return [];
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->cron->run();
  }

  /**
   * Implements hook_gin_content_form_routes().
   */
  #[Hook('gin_content_form_routes')]
  public function ginContentFormRoutes(): array {
    return [
      'entity.commerce_promotion.edit_form',
      'entity.commerce_promotion.add_form',
      'entity.commerce_promotion_coupon.edit_form',
      'entity.commerce_promotion_coupon.add_form',
    ];
  }

}

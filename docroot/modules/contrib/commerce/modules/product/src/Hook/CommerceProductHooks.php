<?php

namespace Drupal\commerce_product\Hook;

use Drupal\commerce_product\ConfigTranslation\ProductAttributeMapper;
use Drupal\commerce_product\Plugin\Block\VariationFieldBlock;
use Drupal\commerce_product\ProductAttributeFieldManagerInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for Commerce Product.
 */
class CommerceProductHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceProductHooks object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\commerce_product\ProductAttributeFieldManagerInterface $productAttributeFieldManager
   *   The product attribute field manager.
   */
  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ProductAttributeFieldManagerInterface $productAttributeFieldManager,
  ) {
  }

  /**
   * Implements hook_config_translation_info_alter().
   */
  #[Hook('config_translation_info_alter')]
  public function configTranslationInfoAlter(&$info): void {
    $info['commerce_product_attribute']['class'] = ProductAttributeMapper::class;
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('entity_form_display_update')]
  public function entityFormDisplayUpdate(EntityFormDisplayInterface $form_display): void {
    // Reset the cached attribute field map when the 'default' product variation
    // form mode is updated, since the map ordering is based on it.
    if ($form_display->getTargetEntityTypeId() == 'commerce_product_variation' && $form_display->getMode() == 'default') {
      $this->productAttributeFieldManager->clearCaches();
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update().
   */
  #[Hook('entity_view_display_update')]
  public function entityViewDisplayUpdate(EntityViewDisplayInterface $entity): void {
    // The product view uses the variation view and needs to be cleared, which doesn't
    // happen automatically because we're editing the variation, not the product.
    if (str_starts_with($entity->getConfigTarget(), 'commerce_product_variation.')) {
      Cache::invalidateTags(['commerce_product_view']);
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
    $field_definition = $context['items']->getFieldDefinition();
    if ($field_definition instanceof BaseFieldOverride) {
      // Reach into the original BaseFieldDefinition, which base fields are both
      // a field definition and storage definition.
      $storage_definition = $field_definition->getFieldStorageDefinition();
      $field_definition = $storage_definition;
    }
    $field_name = $field_definition->getName();
    $entity_type = $field_definition->getTargetEntityTypeId();
    $widget_name = $context['widget']->getPluginId();
    $required = $field_definition->isRequired();
    if ($field_name == 'path' && $entity_type == 'commerce_product' && $widget_name == 'path') {
      $element['alias']['#description'] = $this->t('The alternative URL for this product. Use a relative path. For example, "/my-product".');
    }
    elseif ($field_name == 'title' && $entity_type == 'commerce_product_variation' && !$required) {
      // The title field is optional only when its value is automatically
      // generated, in which case the widget needs to be hidden.
      $element['#access'] = FALSE;
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'entity_form_display_edit_form'.
   *
   * Don't allow referencing existing variations, since a variation must
   * always belong to a single product only.
   */
  #[Hook('form_entity_form_display_edit_form_alter')]
  public function formEntityFormDisplayEditFormAlter(array &$form, FormStateInterface $form_state): void {
    if ($form['#entity_type'] !== 'commerce_product') {
      return;
    }

    if (isset($form['fields']['variations']['plugin']['settings_edit_form']['settings'])) {
      $settings = &$form['fields']['variations']['plugin']['settings_edit_form']['settings'];
      if (isset($settings['allow_existing'])) {
        $settings['allow_existing']['#access'] = FALSE;
        $settings['match_operator']['#access'] = FALSE;
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'field_storage_config_edit_form'.
   *
   * Hide the cardinality setting for attribute fields.
   */
  #[Hook('form_field_storage_config_edit_form_alter')]
  public function formFieldStorageConfigEditFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\field\FieldStorageConfigInterface $field_storage */
    $field_storage = $form_state->getFormObject()->getEntity();
    $entity_type_id = $field_storage->getTargetEntityTypeId();
    $target_type = $field_storage->getSetting('target_type');
    if ($entity_type_id === 'commerce_product_variation' && $target_type === 'commerce_product_attribute_value') {
      $form['cardinality_container']['#access'] = FALSE;
      $form['cardinality_container']['cardinality']['#value'] = 'number';
      $form['cardinality_container']['cardinality_number']['#value'] = '1';
    }
  }

  /**
   * Implements hook_search_api_views_handler_mapping_alter().
   *
   * Search API views filters do not use the options filter by default
   * for all entity bundle fields.
   *
   * @see https://www.drupal.org/project/search_api/issues/2847994
   */
  #[Hook('search_api_views_handler_mapping_alter')]
  public function searchApiViewsHandlerMappingAlter(array &$mapping): void {
    $mapping['entity:commerce_product_type'] = [
      'argument' => [
        'id' => 'search_api',
      ],
      'filter' => [
        'id' => 'search_api_options',
        'options callback' => 'commerce_product_type_labels',
      ],
      'sort' => [
        'id' => 'search_api',
      ],
    ];
  }

  /**
   * Implements hook_config_schema_info_alter().
   *
   * This method provides a compatibility layer to allow new config schemas to
   * be used with older versions of Drupal.
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(&$definitions): void {
    if (!isset($definitions['field.widget.settings.entity_reference_autocomplete']['mapping']['match_limit'])) {
      $definitions['field.widget.settings.entity_reference_autocomplete']['mapping']['match_limit'] = [
        'type' => 'integer',
        'label' => 'Maximum number of autocomplete suggestions.',
      ];
    }
  }

  /**
   * Implements hook_commerce_condition_info_alter().
   */
  #[Hook('commerce_condition_info_alter')]
  public function commerceConditionInfoAlter(array &$definitions): void {
    if (isset($definitions['order_purchased_entity:commerce_product_variation'])) {
      $definitions['order_purchased_entity:commerce_product_variation']['category'] = $this->t('Products');
    }
    if (isset($definitions['order_item_purchased_entity:commerce_product_variation'])) {
      $definitions['order_item_purchased_entity:commerce_product_variation']['category'] = $this->t('Products');
    }
  }

  /**
   * Implements hook_jsonapi_ENTITY_TYPE_filter_access().
   *
   * Product variations do not have a query access handler, so we must define
   * the access for JSON:API filter access here.
   */
  #[Hook('jsonapi_commerce_product_variation_filter_access')]
  public function jsonApiCommerceProductVariationFilterAccess(EntityTypeInterface $entity_type, AccountInterface $account): array {
    return [
      JSONAPI_FILTER_AMONG_OWN => AccessResult::allowedIfHasPermission($account, 'view own unpublished commerce_product'),
      JSONAPI_FILTER_AMONG_PUBLISHED => AccessResult::allowedIfHasPermission($account, 'view commerce_product'),
    ];
  }

  /**
   * Implements hook_block_alter().
   */
  #[Hook('block_alter')]
  public function blockAlter(array &$info): void {
    if ($this->moduleHandler->moduleExists('layout_builder')) {
      $base_plugin_id = 'field_block' . PluginBase::DERIVATIVE_SEPARATOR . 'commerce_product_variation' . PluginBase::DERIVATIVE_SEPARATOR;
      foreach ($info as $block_plugin_id => $block_definition) {
        if (str_starts_with($block_plugin_id, $base_plugin_id)) {
          $info[$block_plugin_id]['class'] = VariationFieldBlock::class;
        }
      }
    }
  }

  /**
   * Implements hook_field_group_content_element_keys_alter().
   *
   * Allow products to render fields groups defined from Fields UI.
   */
  #[Hook('field_group_content_element_keys_alter')]
  public function fieldGroupContentElementKeysAlter(&$keys): void {
    $keys['commerce_product'] = 'product';
    $keys['commerce_product_variation'] = 'product_variation';
  }

  /**
   * Implements hook_entity_operation_alter().
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): void {
    // For the 'commerce_product_attribute' entity type when the 'translate'
    // operation does not exist, we need to check if the user has access to
    // manage translations.
    if ($entity->getEntityTypeId() !== 'commerce_product_attribute'
      || !$entity->hasLinkTemplate('config-translation-overview')
      || isset($operations['translate'])
    ) {
      return;
    }

    $url = $entity->toUrl('config-translation-overview');
    if ($url->access()) {
      $operations['translate'] = [
        'title' => $this->t('Translate'),
        'weight' => 50,
        'url' => $url,
      ];
    }
  }

  /**
   * Implements hook_gin_content_form_routes().
   */
  #[Hook('gin_content_form_routes')]
  public function ginContentFormRoutes(): array {
    return [
      'entity.commerce_product.edit_form',
      'entity.commerce_product.add_form',
      'entity.commerce_product_variation.edit_form',
      'entity.commerce_product_variation.add_form',
    ];
  }

}

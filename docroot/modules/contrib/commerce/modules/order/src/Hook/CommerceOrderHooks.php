<?php

namespace Drupal\commerce_order\Hook;

use Drupal\commerce\ConfigurableFieldManagerInterface;
use Drupal\commerce_order\AddressBookInterface;
use Drupal\commerce_order\Entity\CustomerProfile;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderTypeInterface;
use Drupal\commerce_order\Form\DashboardMetricsForm;
use Drupal\commerce_order\Form\ProfileAddressBookDeleteForm;
use Drupal\commerce_order\Form\ProfileAddressBookForm;
use Drupal\commerce_order\Plugin\Field\FieldFormatter\PriceCalculatedFormatter;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\profile\Entity\ProfileType;
use Drupal\profile\Entity\ProfileTypeInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Hook implementations for Commerce Order.
 */
class CommerceOrderHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceOrderHooks object.
   *
   * @param \Drupal\commerce_order\AddressBookInterface $addressBook
   *   The Addressbook service.
   * @param \Drupal\commerce\ConfigurableFieldManagerInterface $configurableFieldManager
   *   The configurable field manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Closure $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   */
  public function __construct(
    protected readonly AddressBookInterface $addressBook,
    protected readonly ConfigurableFieldManagerInterface $configurableFieldManager,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[AutowireServiceClosure('form_builder')]
    protected \Closure $formBuilder,
    protected readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_entity_extra_field_info_alter().
   */
  #[Hook('entity_extra_field_info_alter')]
  public function entityExtraFieldInfoAlter(array &$info): void {
    if (isset($info['commerce_order'])) {
      // Show the 'View PDF' link by default.
      foreach ($info['commerce_order'] as &$fields) {
        if (isset($fields['display']['entity_print_view_pdf'])) {
          $fields['display']['entity_print_view_pdf']['visible'] = TRUE;
        }
      }
    }
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
    // During tests, always have order versioning throw an exception.
    if (drupal_valid_test_ua()) {
      $entity_types['commerce_order']->set('log_version_mismatch', FALSE);
    }
    else {
      $config = $this->configFactory->get('commerce_order.settings');
      $entity_types['commerce_order']->set('log_version_mismatch', $config->get('log_version_mismatch'));
    }
    // Remove the "EntityChanged" constraint, our "OrderVersion" constraint
    // replaces it.
    $constraints = $entity_types['commerce_order']->getConstraints();
    unset($constraints['EntityChanged']);
    $entity_types['commerce_order']->setConstraints($constraints);
  }

  /**
   * Implements hook_entity_type_build().
   *
   * Adds the address book form classes to profile entities.
   * Referenced in commerce_order.routing.yml.
   */
  #[Hook('entity_type_build')]
  public function entityTypeBuild(array &$entity_types): void {
    if (isset($entity_types['profile'])) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entity_types */
      $entity_types['profile']->setFormClass('address-book-add', ProfileAddressBookForm::class);
      $entity_types['profile']->setFormClass('address-book-edit', ProfileAddressBookForm::class);
      $entity_types['profile']->setFormClass('address-book-delete', ProfileAddressBookDeleteForm::class);
    }
  }

  /**
   * Implements hook_field_formatter_info_alter().
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    // Replaces the commerce_price PriceCalculatedFormatter with the
    // expanded commerce_order one.
    $info['commerce_price_calculated']['class'] = PriceCalculatedFormatter::class;
    $info['commerce_price_calculated']['provider'] = 'commerce_order';
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * - Changes the label of the purchased_entity field to the label of the
   *   target type (e.g. 'Product variation').
   * - Forbids editing the purchased_entity once the order item is no longer
   * new.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $field_definition = $context['items']->getFieldDefinition();
    $field_name = $field_definition->getName();
    $entity_type = $field_definition->getTargetEntityTypeId();
    if ($field_name == 'purchased_entity' && $entity_type == 'commerce_order_item') {
      if (!empty($element['target_id']['#target_type'])) {
        $target_type = $this->entityTypeManager->getDefinition($element['target_id']['#target_type']);
        $element['target_id']['#title'] = $target_type->getLabel();
        if (!$context['items']->getEntity()->isNew()) {
          $element['#disabled'] = TRUE;
        }
      }
    }
    if ($field_name === 'address' && $entity_type === 'profile') {
      $element['#after_build'][] = 'commerce_order_address_field_after_build';
    }
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(array &$definitions): void {
    if (!$this->addressBook->hasUi()) {
      return;
    }

    $profile_types = $this->addressBook->loadTypes();
    foreach ($profile_types as $profile_type) {
      $derivative_key = 'profile.user_page:' . $profile_type->id();
      if (isset($definitions[$derivative_key])) {
        unset($definitions[$derivative_key]);
      }
    }
  }

  /**
   * Implements hook_jsonapi_ENTITY_TYPE_filter_access().
   */
  #[Hook('jsonapi_commerce_order_filter_access')]
  public function jsonapiCommerceOrderFilterAccess(EntityTypeInterface $entity_type, AccountInterface $account): array {
    // Entity API automatically hooks into the JSON:API query filter system for
    // entities that has a permission_provider and query_handler. However, orders
    // do not have an `owner` key and are not evaluated for the
    // JSONAPI_FILTER_AMONG_OWN check. This means only JSONAPI_FILTER_AMONG_ALL is
    // evaluated, which defaults to the admin permission.
    //
    // Since we have a query_handler configured and inaccessible entities will
    // be filtered out automatically, we set it to allowed.
    return [
      JSONAPI_FILTER_AMONG_ALL => AccessResult::allowed(),
    ];
  }

  /**
   * Implements hook_field_group_content_element_keys_alter().
   *
   * Allow orders to render fields groups defined from Fields UI.
   */
  #[Hook('field_group_content_element_keys_alter')]
  public function fieldGroupContentElementKeysAlter(&$keys): void {
    $keys['commerce_order'] = 'order';
    $keys['commerce_order_item'] = 'order_item';
  }

  /**
   * Implements hook_commerce_dashboard_page_build_alter().
   */
  #[Hook('commerce_dashboard_page_build_alter')]
  public function commerceDashboardPageBuildAlter(&$build): void {
    /** @var \Drupal\Core\Form\FormBuilderInterface $form_builder */
    $form_builder = ($this->formBuilder)();
    $form = $form_builder->getForm(DashboardMetricsForm::class);
    // If all periods are disabled, skip rendering the form.
    if (isset($form['periods'])) {
      $build['metrics_form'] = $form;
    }
  }

  /**
   * Implements hook_entity_reference_selection_alter().
   */
  #[Hook('entity_reference_selection_alter')]
  public function entityReferenceSelectionAlter(&$definitions): void {
    // Drupal core assumes our custom entity reference selection plugin for users
    // is defined by a deriver and expect a "base_plugin_label" key to be present.
    // Artificially set the base plugin label to the label.
    if (isset($definitions['commerce:user'])) {
      $definitions['commerce:user']['base_plugin_label'] = $definitions['commerce:user']['label'];
    }
  }

  /**
   * Implements hook_gin_content_form_routes().
   */
  #[Hook('gin_content_form_routes')]
  public function ginContentFormRoutes(): array {
    return [
      'entity.commerce_order.edit_form',
    ];
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    // Add our own bundle class for `customer` to better handle the 4xx-response
    // cache tag invalidation. To prevent overwriting existing custom bundle
    // classes, we only add the custom bundle class if non is set.
    if (isset($bundles['profile']['customer']) && !isset($bundles['profile']['customer']['class'])) {
      $bundles['profile']['customer']['class'] = CustomerProfile::class;
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_build_defaults_alter().
   */
  #[Hook('profile_build_defaults_alter')]
  public function profileBuildDefaultsAlter(array &$build, EntityInterface $entity, $view_mode): void {
    // Contextual links are removed for profiles viewed from order routes.
    $build['#cache']['contexts'][] = 'route';
  }

  /**
   * Implements hook_ENTITY_TYPE_view_alter().
   */
  #[Hook('profile_view_alter')]
  public function profileViewAlter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
    // Remove contextual links for profiles on order routes.
    if ($this->routeMatch->getParameter('commerce_order')) {
      unset($build['#contextual_links']);
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'profile_type_form'.
   */
  #[Hook('form_profile_type_form_alter')]
  public function formProfileTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $form_state->getFormObject()->getEntity();
    $customer_flag = $profile_type->getThirdPartySetting('commerce_order', 'customer_profile_type');
    $address_has_data = FALSE;
    if ($customer_flag && !$profile_type->isNew()) {
      $address_field_definition = commerce_order_build_address_field_definition($profile_type->id());
      $address_has_data = $this->configurableFieldManager->hasData($address_field_definition);
    }

    $form['#tree'] = TRUE;
    $form['commerce_order'] = [
      '#type' => 'container',
      '#weight' => 1,
    ];
    $form['commerce_order']['customer_profile_type'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Profiles of this type represent Commerce customer profiles'),
      '#description' => $this->t("Used to store the customer's billing or shipping information."),
      '#default_value' => $customer_flag,
      // The flag is always TRUE for the profile type provided by Commerce.
      '#disabled' => $profile_type->id() == 'customer' || $address_has_data,
    ];
    $form['actions']['submit']['#submit'][] = [static::class, 'profileTypeFormSubmit'];
  }

  /**
   * Submission handler for commerce_order_form_profile_type_form_alter().
   */
  public static function profileTypeFormSubmit(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\profile\Entity\ProfileTypeInterface $profile_type */
    $profile_type = $form_state->getFormObject()->getEntity();
    $customer_flag = $form_state->getValue(['commerce_order', 'customer_profile_type']);
    $previous_customer_flag = $profile_type->getThirdPartySetting('commerce_order', 'customer_profile_type');
    /** @var \Drupal\commerce\ConfigurableFieldManagerInterface $configurable_field_manager */
    $configurable_field_manager = \Drupal::service('commerce.configurable_field_manager');
    $address_field_definition = commerce_order_build_address_field_definition($profile_type->id());
    if ($customer_flag && !$previous_customer_flag) {
      $configurable_field_manager->createField($address_field_definition, FALSE);
    }
    elseif (!$customer_flag && $previous_customer_flag) {
      $configurable_field_manager->deleteField($address_field_definition);
    }

    $profile_type->setThirdPartySetting('commerce_order', 'customer_profile_type', $customer_flag);
    $profile_type->save();
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   *
   * Forbids the "customer" profile type from being deletable.
   */
  #[Hook('profile_type_access')]
  public function profileTypeAccess(ProfileTypeInterface $profile_type, $operation): AccessResultInterface {
    if ($profile_type->id() === 'customer' && $operation === 'delete') {
      return AccessResult::forbidden();
    }
    return AccessResult::neutral();
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   *
   * Forbids the profile "address" field from being deletable.
   * This is an alternative to locking the field which still leaves
   * the field editable.
   */
  #[Hook('field_storage_config_access')]
  public function fieldStorageConfigAccess(FieldStorageConfigInterface $field_storage, $operation): AccessResultInterface {
    if ($field_storage->id() === 'profile.address' && $operation === 'delete') {
      return AccessResult::forbidden();
    }

    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_operation_alter().
   *
   * Hides the "Storage settings" operation for the profile "address" field.
   */
  #[Hook('entity_operation_alter')]
  public function entityOperationAlter(array &$operations, EntityInterface $entity, ?CacheableMetadata $cacheability = NULL): void {
    if ($entity->getEntityTypeId() === 'commerce_order') {
      $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($entity->bundle());
      if ($order_type instanceof OrderTypeInterface &&
        !$order_type->shouldShowOrderEditLinks()) {
        unset($operations['edit']);
      }
    }
    if ($entity->getEntityTypeId() === 'field_config') {
      /** @var \Drupal\Core\Field\FieldConfigInterface $entity */
      if ($entity->getTargetEntityTypeId() == 'profile' && $entity->getName() == 'address') {
        unset($operations['storage-settings']);
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'field_config_edit_form'.
   *
   * Hides the "Required" and "Available countries" settings for the customer
   * profile "address" field.
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function fieldConfigEditFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Field\FieldConfigInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();
    if ($entity->getTargetEntityTypeId() == 'profile' && $entity->getName() == 'address') {
      // Make sure that the profile type is a customer profile type, to avoid
      // affecting other types which just reuse the address field.
      $profile_type = ProfileType::load($entity->getTargetBundle());
      if ($profile_type->getThirdPartySetting('commerce_order', 'customer_profile_type')) {
        // The field must always be required.
        $form['required']['#default_value'] = TRUE;
        $form['required']['#access'] = FALSE;
        // Available countries are taken from the store.
        $form['settings']['available_countries']['#access'] = FALSE;
      }
    }
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   *
   * Hides the "Edit" tab for orders.
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name, RefinableCacheableDependencyInterface &$cacheability): void {
    if (!isset($data['tabs'][0]['entity.entity_tasks:entity.commerce_order.edit_form'])) {
      return;
    }
    $order = $this->routeMatch->getParameter('commerce_order');
    if (is_null($order)) {
      return;
    }
    if (!($order instanceof OrderInterface)) {
      $order = $this->entityTypeManager->getStorage('commerce_order')->load($order);
    }
    $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());
    if ($order_type instanceof OrderTypeInterface &&
      !$order_type->shouldShowOrderEditLinks()) {
      unset($data['tabs'][0]['entity.entity_tasks:entity.commerce_order.edit_form']);
    }
  }

}

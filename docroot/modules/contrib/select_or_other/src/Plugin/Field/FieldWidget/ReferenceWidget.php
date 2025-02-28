<?php

namespace Drupal\select_or_other\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionWithAutocreateInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;

/**
 * Plugin implementation of the 'select_or_other_reference' widget.
 *
 * @FieldWidget(
 *   id = "select_or_other_reference",
 *   label = @Translation("Select or Other"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = TRUE
 * )
 */
class ReferenceWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfoService;

  /**
   * The account interface service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('current_user'),
    );
  }

  /**
   * Constructs a ReferenceWidget object.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type = NULL, EntityTypeBundleInfoInterface $bundle_info_service = NULL, AccountInterface $current_user = NULL) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type;
    $this->bundleInfoService = $bundle_info_service;
    $this->currentUser = $current_user;
  }

  /**
   * Helper method which prepares element values for validation.
   *
   * EntityAutocomplete::validateEntityAutocomplete expects a string when
   * validating taxonomy terms.
   *
   * @param array $element
   *   The element to prepare.
   */
  protected static function prepareElementValuesForValidation(array &$element) {
    if ($element['#tags']) {
      $element['#value'] = Tags::implode($element['#value']);
    }
  }

  /**
   * Retrieves the entityStorage object.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   EntityStorage for entity types that can be referenced by this widget.
   *
   * @codeCoverageIgnore
   *   Ignore this method because if ::getFieldSetting() or entityTypeManager
   *   does not return the expected result, we have other problems on our hands.
   */
  protected function getEntityStorage() {
    $target_type = $this->getFieldSetting('target_type');
    return $this->entityTypeManager->getStorage($target_type);
  }

  /**
   * Retrieves the key used to indicate a bundle for the entity type.
   *
   * @return string
   *   The key used to indicate a bundle for the entity type referenced by this
   *   widget's field.
   *
   * @codeCoverageIgnore
   *   Ignore this method because if any of the called core functions does not
   *   return the expected result, we've got other problems on our hands.
   */
  protected function getBundleKey() {
    $entity_keys = $this->getEntityStorage()
      ->getEntityType()
      ->get('entity_keys');
    return $entity_keys['bundle'];
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity = NULL) {
    $options = [];

    // Prepare properties to use for loading.
    $entity_storage = $this->getEntityStorage();
    if (is_a($entity_storage, '\Drupal\taxonomy\TermStorage')) {
      $target_bundles = $this->getSelectionHandlerSetting('target_bundles');

      $entities = [];
      foreach ($target_bundles as $target) {
        $entities = array_merge($entities, $entity_storage->loadTree($target));
      }

      foreach ($entities as $entity) {
        $options["{$entity->name} ({$entity->tid})"] = str_repeat("-", $entity->depth) . $entity->name;
      }
    }
    else {
      $bundle_key = $this->getBundleKey();
      $target_bundles = $this->getSelectionHandlerSetting('target_bundles');
      $properties = [$bundle_key => $target_bundles];
      $entities = $entity_storage->loadByProperties($properties);

      // Prepare the options.
      foreach ($entities as $entity) {
        $options["{$entity->label()} ({$entity->id()})"] = $entity->label();
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSelectedOptions(array $options) {
    $prepared_options = [];
    $entities = $this->getEntityStorage()->loadMultiple($options);

    foreach ($entities as $entity) {
      $prepared_options[] = "{$entity->label()} ({$entity->id()})";
    }

    return $prepared_options;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $target_entity_type_id = $this->getFieldSetting('target_type');
    $element = $element + [
      '#target_type' => $target_entity_type_id,
      '#selection_handler' => $this->getFieldSetting('handler'),
      '#selection_settings' => $this->getFieldSetting('handler_settings'),
      '#validate_reference' => TRUE,
      '#tags' => $target_entity_type_id === 'taxonomy_term',
      '#merged_values' => TRUE,
    ];
    $autocreate_bundle = $this->getAutocreateBundle();
    $user = $this->currentUser;
    $access = $this->getCreateAccess($target_entity_type_id, $autocreate_bundle, $user);
    // Check whether access is allowed and if yes, fill out #autocreate.
    if ($access->isAllowed()) {
      $element['#autocreate'] = [
        'bundle' => $autocreate_bundle,
        'uid' => $user->id(),
      ];
    }
    else {
      // Otherwise do not autocreate and do not allow other.
      $element['#autocreate'] = NULL;
      $element['#other_allowed'] = FALSE;
    }

    $element['#element_validate'] = [
      [
        get_class($this),
        'validateReferenceWidget',
      ],
    ];

    return $element;
  }

  /**
   * Returns the access result object.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle name.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account interface.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|bool
   *   The access result. Returns a boolean if $return_as_object is FALSE (this
   *   is the default) and otherwise an AccessResultInterface object.
   */
  protected function getCreateAccess($entity_type_id, $bundle, AccountInterface $user) {
    return $this->entityTypeManager
      ->getAccessControlHandler($entity_type_id)
      ->createAccess($bundle, $user, [], TRUE);
  }

  /**
   * Returns the value of a setting for the entity reference selection handler.
   *
   * @todo This is shamelessly copied from EntityAutocomplete. We should
   * probably file a core issue to turn this into a trait. The same goes for
   * $this::getAutoCreateBundle()
   *
   * @param string $setting_name
   *   The setting name.
   *
   * @return mixed
   *   The setting value.
   *
   * @codeCoverageIgnore
   *   Ignore this function because it is a straight copy->paste.
   */
  protected function getSelectionHandlerSetting($setting_name) {
    $settings = $this->getFieldSetting('handler_settings');
    return $settings[$setting_name] ?? NULL;
  }

  /**
   * Returns the name of the bundle which will be used for autocreated entities.
   *
   * @todo This is shamelessly copied from EntityAutocomplete. We should
   * probably file a core issue to turn this into a trait. The same goes for
   * $this::getSelectionHandlerSetting()
   *
   * @return string
   *   The bundle name.
   *
   * @codeCoverageIgnore
   *   Ignore this function because it is a straight copy->paste.
   */
  protected function getAutocreateBundle() {
    $bundle = NULL;
    if ($this->getSelectionHandlerSetting('auto_create')) {
      // If the 'target_bundles' setting is restricted to a single choice, we
      // can use that.
      if (($target_bundles = $this->getSelectionHandlerSetting('target_bundles')) && count($target_bundles) == 1) {
        $bundle = reset($target_bundles);
      }
      // Otherwise use the first bundle as a fallback.
      else {
        // @todo Expose a proper UI for choosing the bundle for autocreated
        // entities in https://www.drupal.org/node/2412569.
        $bundles = $this->bundleInfoService
          ->getBundleInfo($this->getFieldSetting('target_type'));
        $bundle = key($bundles);
      }
    }

    return $bundle;
  }

  /**
   * Form element validation handler for select_or_other_reference elements.
   *
   * @codeCoverageIgnore
   *   Ignore
   */
  public static function validateReferenceWidget(array &$element, FormStateInterface $form_state, array &$complete_form) {
    self::prepareElementValuesForValidation($element);
    if (!empty($element['#value'])) {
      EntityAutocomplete::validateEntityAutocomplete($element, $form_state, $complete_form);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $options = $field_definition->getSettings();
    $handler_settings = $options['handler_settings'] ?? NULL;
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')
      ->getInstance($options);
    return $handler instanceof SelectionWithAutocreateInterface
    && isset($handler_settings['auto_create'])
    && $handler_settings['auto_create'];
  }

}

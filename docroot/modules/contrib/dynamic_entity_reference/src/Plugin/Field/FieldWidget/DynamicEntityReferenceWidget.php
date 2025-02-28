<?php

namespace Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Render\ElementInfoManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'dynamic_entity_reference autocomplete' widget.
 *
 * @FieldWidget(
 *   id = "dynamic_entity_reference_default",
 *   label = @Translation("Autocomplete"),
 *   description = @Translation("An autocomplete text field."),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   }
 * )
 */
class DynamicEntityReferenceWidget extends EntityReferenceAutocompleteWidget {

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected EntityTypeRepositoryInterface $entityTypeRepository;

  /**
   * The element info manager.
   *
   * @var \Drupal\Core\Render\ElementInfoManagerInterface
   */
  protected ElementInfoManagerInterface $elementInfo;

  /**
   * The current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The key value manager.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->setEntityTypeRepository($container->get('entity_type.repository'));
    $instance->setElementInfo($container->get('element_info'));
    $instance->setCurrentUser($container->get('current_user'));
    $instance->setKeyValue($container->get('keyvalue'));
    return $instance;
  }

  /**
   * Sets entity type repository service.
   */
  public function setEntityTypeRepository(EntityTypeRepositoryInterface $entity_type_repository) {
    $this->entityTypeRepository = $entity_type_repository;
  }

  /**
   * Sets element info manager.
   */
  public function setElementInfo(ElementInfoManagerInterface $element_info) {
    $this->elementInfo = $element_info;
  }

  /**
   * Sets current user.
   */
  public function setCurrentUser(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Sets key value manager.
   */
  public function setKeyValue(KeyValueFactoryInterface $key_value) {
    $this->keyValue = $key_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
      'size' => 40,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $referenced_entities = $items->referencedEntities();

    $settings = $this->getFieldSettings();
    $labels = $this->entityTypeRepository->getEntityTypeLabels();
    $available = DynamicEntityReferenceItem::getTargetTypes($settings);
    $cardinality = $items->getFieldDefinition()->getFieldStorageDefinition()->getCardinality();
    $target_type = $items->get($delta)->target_type ?: reset($available);

    // Append the match operation to the selection settings.
    $selection_settings = $settings[$target_type]['handler_settings'] + [
      'match_operator' => $this->getSetting('match_operator'),
      'match_limit' => $this->getSetting('match_limit'),
    ];

    $element += [
      '#type' => 'entity_autocomplete',
      '#target_type' => $target_type,
      '#selection_handler' => $settings[$target_type]['handler'],
      '#selection_settings' => $selection_settings,
      // Dynamic entity reference field items are handling validation themselves
      // via the 'ValidDynamicReference' constraint.
      '#validate_reference' => FALSE,
      '#maxlength' => 1024,
      '#default_value' => isset($referenced_entities[$delta]) ? $referenced_entities[$delta] : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#element_validate' => array_merge(
        [[$this, 'elementValidate']],
        $this->elementInfo->getInfoProperty('entity_autocomplete', '#element_validate', [])
      ),
      '#field_name' => $items->getName(),
    ];

    if ($this->getSelectionHandlerSetting('auto_create', $target_type)) {
      $element['#autocreate'] = [
        'bundle' => $this->getAutocreateBundle($target_type),
        'uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : $this->currentUser->id(),
      ];
    }

    $element['#title'] = $this->t('Label');

    if (count($available) > 1) {
      $target_type_element = [
        '#type' => 'select',
        '#options' => array_intersect_key($labels, array_combine($available, $available)),
        '#title' => $this->t('Type'),
        '#default_value' => $target_type,
        '#weight' => -50,
        '#attributes' => [
          'class' => [
            'dynamic-entity-reference-entity-type',
          ],
        ],
      ];
    }
    else {
      $target_type_element = [
        '#type' => 'value',
        '#value' => reset($available),
      ];
    }

    $form_element = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
      'target_type' => $target_type_element,
      'target_id' => $element,
      '#process' => [[$this, 'processFormElement']],
      '#attached' => [
        'library' => [
          'dynamic_entity_reference/drupal.dynamic_entity_reference_widget',
        ],
        'drupalSettings' => [
          'dynamic_entity_reference' => [
            'auto_complete_paths' => $this->createAutoCompletePaths($available),
          ],
        ],
      ],
    ];
    // Render field as details.
    if ($cardinality == 1) {
      $form_element['#type'] = 'details';
      $form_element['#title'] = $items->getFieldDefinition()->getLabel();
      $form_element['#open'] = TRUE;
    }
    return $form_element;
  }

  /**
   * Adds entity autocomplete paths to a form element.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   */
  public static function processFormElement(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $name = implode('-', $element['#parents']);
    $js_class = Html::cleanCssIdentifier("js-dynamic-entity-reference-{$name}-target_type");
    $element['target_type']['#attributes']['data-dynamic-entity-reference'] = $element['target_type']['#attributes']['class'][] = $js_class;
    $auto_complete_paths = $element['#attached']['drupalSettings']['dynamic_entity_reference']['auto_complete_paths'];
    unset($element['#attached']['drupalSettings']['dynamic_entity_reference']['auto_complete_paths']);
    $element['#attached']['drupalSettings']['dynamic_entity_reference'][$js_class] = $auto_complete_paths;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function elementValidate(&$element, FormStateInterface $form_state, &$form) {
    if (!empty($element['#value'])) {
      // If this is the default value of the field.
      if ($form_state->hasValue('default_value_input')) {
        $values = $form_state->getValue([
          'default_value_input',
          $element['#field_name'],
          $element['#delta'],
        ]);
      }
      else {
        $parents = $element['#parents'];
        // Remove the 'target_id' key.
        array_pop($parents);
        $values = $form_state->getValue($parents);
      }
      $settings = $this->getFieldSettings();
      $element['#target_type'] = $values['target_type'];
      $element['#selection_handler'] = $settings[$values['target_type']]['handler'];
      $element['#selection_settings'] = $settings[$values['target_type']]['handler_settings'];
      if ($this->getSelectionHandlerSetting('auto_create', $values['target_type'])) {
        $form_object = $form_state->getFormObject();
        $entity = $form_object instanceof EntityFormInterface ? $form_object->getEntity() : '';
        $element['#autocreate'] = [
          'bundle' => $this->getAutocreateBundle($values['target_type']),
          'uid' => ($entity instanceof EntityOwnerInterface) ? $entity->getOwnerId() : $this->currentUser->id(),
        ];
      }
      else {
        $element['#autocreate'] = NULL;
      }

    }
  }

  /**
   * Returns the value of a setting for the dynamic entity reference handler.
   *
   * @param string $setting_name
   *   The setting name.
   * @param string $target_type
   *   The id of the target entity type.
   *
   * @return mixed
   *   The setting value.
   */
  protected function getSelectionHandlerSetting($setting_name, $target_type = NULL) {
    if ($target_type === NULL) {
      return parent::getSelectionHandlerSetting($setting_name);
    }
    $settings = $this->getFieldSettings();
    return isset($settings[$target_type]['handler_settings'][$setting_name]) ? $settings[$target_type]['handler_settings'][$setting_name] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getAutocreateBundle($target_type = NULL) {
    if ($target_type === NULL) {
      return parent::getAutocreateBundle();
    }
    if ($this->getSelectionHandlerSetting('auto_create', $target_type)) {
      $target_bundles = $this->getSelectionHandlerSetting('target_bundles', $target_type);
      // If there's no target bundle at all, use the target_type. It's the
      // default for bundleless entity types.
      if (empty($target_bundles)) {
        return $target_type;
      }
      // If there's only one target bundle, use it.
      if (count($target_bundles) == 1) {
        return reset($target_bundles);
      }
      // If there's more than one target bundle, use the autocreate bundle
      // stored in selection handler settings.
      if (!$this->getSelectionHandlerSetting('auto_create_bundle', $target_type)) {
        // If no bundle has been set as auto create target means that there is
        // an inconsistency in entity reference field settings.
        trigger_error(sprintf(
          "The 'Create referenced entities if they don't already exist' option is enabled but a specific destination bundle is not set. You should re-visit and fix the settings of the '%s' (%s) field.",
          $this->fieldDefinition->getLabel(),
          $this->fieldDefinition->getName()
        ), E_USER_WARNING);
      }
    }

    return NULL;
  }

  /**
   * Creates auto complete path for all the given target types.
   *
   * @param string[] $target_types
   *   All the referenceable target types.
   *
   * @return array
   *   Auto complete paths for all the referenceable target types.
   */
  protected function createAutoCompletePaths(array $target_types) {
    $auto_complete_paths = [];
    $settings = $this->getFieldSettings();
    foreach ($target_types as $target_type) {
      // Store the selection settings in the key/value store and pass a hashed
      // key in the route parameters.
      $selection_settings = $settings[$target_type]['handler_settings'] ?: [];
      $selection_settings += [
        'match_operator' => $this->getSetting('match_operator'),
        'match_limit' => $this->getSetting('match_limit'),
      ];
      $data = serialize($selection_settings) . $target_type . $settings[$target_type]['handler'];
      $selection_settings_key = Crypt::hmacBase64($data, Settings::getHashSalt());
      $key_value_storage = $this->keyValue->get('entity_autocomplete');
      if (!$key_value_storage->has($selection_settings_key)) {
        $key_value_storage->set($selection_settings_key, $selection_settings);
      }
      $auto_complete_paths[$target_type] = Url::fromRoute('system.entity_autocomplete', [
        'target_type' => $target_type,
        'selection_handler' => $settings[$target_type]['handler'],
        'selection_settings_key' => $selection_settings_key,
      ])->toString();
    }
    return $auto_complete_paths;
  }

}

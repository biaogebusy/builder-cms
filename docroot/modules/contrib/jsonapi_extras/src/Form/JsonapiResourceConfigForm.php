<?php

namespace Drupal\jsonapi_extras\Form;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_extras\Entity\JsonapiResourceConfig;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base form for jsonapi_resource_config.
 */
class JsonapiResourceConfigForm extends EntityForm {

  /**
   * The bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * The field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * The field enhancer manager.
   *
   * @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager
   */
  protected $enhancerManager;

  /**
   * The JSON:API extras config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current route match.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfigManager;

  /**
   * JsonapiResourceConfigForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   Bundle information service.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeRepositoryInterface $entity_type_repository
   *   The entity type repository.
   * @param \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerManager $enhancer_manager
   *   The plugin manager for the resource field enhancer.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The config instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   */
  public function __construct(EntityTypeBundleInfoInterface $bundle_info, ResourceTypeRepositoryInterface $resource_type_repository, EntityFieldManager $field_manager, EntityTypeRepositoryInterface $entity_type_repository, ResourceFieldEnhancerManager $enhancer_manager, ImmutableConfig $config, Request $request, TypedConfigManagerInterface $typed_config_manager) {
    $this->bundleInfo = $bundle_info;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->fieldManager = $field_manager;
    $this->entityTypeRepository = $entity_type_repository;
    $this->enhancerManager = $enhancer_manager;
    $this->config = $config;
    $this->request = $request;
    $this->typedConfigManager = $typed_config_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.repository'),
      $container->get('plugin.manager.resource_field_enhancer'),
      $container->get('config.factory')->get('jsonapi_extras.settings'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('config.typed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    // Disable caching on this form.
    $form_state->setCached(FALSE);

    $entity_type_id = $this->request->get('entity_type_id');
    $bundle = $this->request->get('bundle');

    /** @var \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $entity */
    $entity = $this->getEntity();
    $resource_id = $entity->get('id');
    // If we are editing an entity we don't want the Entity Type and Bundle
    // picker, that info is locked.
    if (!$entity_type_id || !$bundle) {
      if (!$resource_id) {
        // We can't build the form without an entity type and bundle.
        throw new \InvalidArgumentException('Unable to load entity type or bundle for the overrides form.');
      }
      [$entity_type_id, $bundle] = explode('--', $resource_id);
      $form['#title'] = $this->t('Edit %label resource config', ['%label' => $resource_id]);
    }

    if ($entity_type_id && $resource_type = $this->resourceTypeRepository->get($entity_type_id, $bundle)) {
      // Get the JSON:API resource type.
      $resource_config_id = sprintf('%s--%s', $entity_type_id, $bundle);
      $existing_entity = $this->entityTypeManager
        ->getStorage('jsonapi_resource_config')->load($resource_config_id);
      if ($existing_entity && $entity->isNew()) {
        $this->messenger()->addStatus($this->t('This override already exists, please edit it instead.'));
        return $form;
      }
      try {
        $fields_wrapper = $this->buildOverridesForm($resource_type, $entity);
        $form['bundle_wrapper']['fields_wrapper'] = $fields_wrapper;
      }
      catch (PluginNotFoundException $exception) {
        // Log the exception and continue.
        watchdog_exception('jsonapi_extras', $exception);
      }
      $form['id'] = ['#type' => 'hidden', '#value' => $resource_config_id];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!method_exists($this->typedConfigManager, 'createFromNameAndData')) {
      // Versions of Drupal before 8.4 have poor support for constraints. In
      // those scenarios we don't validate the form submission.
      return;
    }
    $typed_config = $this->typedConfigManager
      ->createFromNameAndData($this->entity->id(), $this->entity->toArray());
    $constraints = $typed_config->validate();
    /** @var \Symfony\Component\Validator\ConstraintViolation $violation */
    foreach ($constraints as $violation) {
      $form_path = str_replace('.', '][', $violation->getPropertyPath());
      $form_state->setErrorByName($form_path, $violation->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $resource_config = $this->entity;
    $status = $resource_config->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label JSON:API Resource overwrites.', [
          '%label' => $resource_config->id(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label JSON:API Resource overwrites.', [
          '%label' => $resource_config->id(),
        ]));
    }
    $form_state->setRedirectUrl($resource_config->toUrl('collection'));
  }

  /**
   * Builds the part of the form that contains the overrides.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type being overridden.
   * @param \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $entity
   *   The configuration entity backing this form.
   *
   * @return array
   *   The partial form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildOverridesForm(ResourceType $resource_type, JsonapiResourceConfig $entity) {
    $entity_type_id = $resource_type->getEntityTypeId();
    /** @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundle = $resource_type->getBundle();
    $field_names = $this->getAllFieldNames($entity_type, $bundle);

    $overrides_form['overrides']['entity'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Resource'),
      '#description' => $this->t('Override configuration for the resource entity.'),
      '#open' => !$entity->get('resourceType') || !$entity->get('path'),
      '#weight' => 0,
    ];

    $overrides_form['overrides']['entity']['disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disabled'),
      '#description' => $this->t('Check this if you want to disable this resource. Disabling a resource can have unexpected results when following relationships belonging to that resource.'),
      '#default_value' => $entity->get('disabled'),
    ];

    $resource_type_name = $entity->get('resourceType');
    if (!$resource_type_name) {
      $resource_type_name = sprintf('%s--%s', $entity_type_id, $bundle);
    }
    $overrides_form['overrides']['entity']['resourceType'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource Type'),
      '#description' => $this->t('Overrides the type of the resource. Example: Change "node--article" to "articles".'),
      '#default_value' => $resource_type_name,
      '#states' => [
        'visible' => [
          ':input[name="disabled"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $path = $entity->get('path');
    if (!$path) {
      $path = sprintf('%s/%s', $entity_type_id, $bundle);
    }

    $prefix = $this->config->get('path_prefix');
    $overrides_form['overrides']['entity']['path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource Path'),
      '#field_prefix' => sprintf('/%s/', $prefix),
      '#description' => $this->t('Overrides the path of the resource. Example: Use "articles" to change "/@prefix/node/article" to "/@prefix/articles".', [
        '@prefix' => $prefix,
      ]),
      '#default_value' => $path,
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="disabled"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $overrides_form['overrides']['fields'] = [
      '#type' => 'details',
      '#title' => $this->t('Fields'),
      '#open' => TRUE,
      '#weight' => 1,
    ];

    $markup = '';
    $markup .= '<dl>';
    $markup .= '<dt>' . $this->t('Disabled') . '</dt>';
    $markup .= '<dd>' . $this->t('Check this if you want to disable this field completely. Disabling required fields will cause problems when writing to the resource.') . '</dd>';
    $markup .= '<dt>' . $this->t('Alias') . '</dt>';
    $markup .= '<dd>' . $this->t('Overrides the field name with a custom name. Example: Change "field_tags" to "tags".') . '</dd>';
    $markup .= '<dt>' . $this->t('Enhancer') . '</dt>';
    $markup .= '<dd>' . $this->t('Select an enhancer to manipulate the public output coming in and out.') . '</dd>';
    $markup .= '</dl>';
    $overrides_form['overrides']['fields']['info'] = [
      '#markup' => $markup,
    ];

    $overrides_form['overrides']['fields']['resourceFields'] = [
      '#type' => 'table',
      '#theme' => 'expandable_rows_table',
      '#header' => [
        'disabled' => $this->t('Disabled'),
        'fieldName' => $this->t('Field name'),
        'publicName' => $this->t('Alias'),
        'advancedOptions' => '',
      ],
      '#empty' => $this->t('No fields available.'),
      '#states' => [
        'visible' => [
          ':input[name="disabled"]' => ['checked' => FALSE],
        ],
      ],
      '#attached' => [
        'library' => [
          'jsonapi_extras/expandable_rows_table',
        ],
      ],
    ];

    foreach ($field_names as $field_name) {
      try {
        $overrides = $this->buildOverridesField($field_name, $entity);
      }
      catch (PluginException $exception) {
        // Log exception and continue.
        watchdog_exception('jsonapi_extras', $exception);
        continue;
      }
      NestedArray::setValue(
        $overrides_form,
        ['overrides', 'fields', 'resourceFields', $field_name],
        $overrides
      );
    }

    return $overrides_form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $entity */
    $entity = parent::buildEntity($form, $form_state);

    // Trim slashes from path.
    $path = trim($form_state->getValue('path'), '/');
    if (strlen($path) > 0) {
      $entity->set('path', $path);
    }

    return $entity;
  }

  /**
   * Builds the part of the form that overrides the field.
   *
   * @param string $field_name
   *   The field name of the field being overridden.
   * @param \Drupal\jsonapi_extras\Entity\JsonapiResourceConfig $entity
   *   The config entity backed by this form.
   *
   * @return array
   *   The partial form.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function buildOverridesField($field_name, JsonapiResourceConfig $entity) {
    $rfs = $entity->get('resourceFields') ?: [];
    $resource_fields = array_filter($rfs, function (array $resource_field) use ($field_name) {
      return $resource_field['fieldName'] == $field_name;
    });
    $resource_field = array_shift($resource_fields);
    $overrides_form = [];
    $overrides_form['disabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disabled'),
      '#title_display' => 'invisible',
      '#default_value' => empty($resource_field['disabled']) ? NULL : $resource_field['disabled'],
    ];
    $overrides_form['fieldName'] = [
      '#type' => 'hidden',
      '#value' => $field_name,
      '#prefix' => $field_name,
    ];
    $overrides_form['publicName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Override Public Name'),
      '#title_display' => 'hidden',
      '#default_value' => empty($resource_field['publicName']) ? $field_name : $resource_field['publicName'],
      '#states' => [
        'visible' => [
          ':input[name="resourceFields[' . $field_name . '][disabled]"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];
    $overrides_form['advancedOptions'] = [
      '#markup' => t('Advanced'),
    ];

    $overrides_form['advancedOptionsIcon'] = [
      // Here we are just printing an arrow.
      '#markup' => '&#x21B3;',
    ];

    $overrides_form['enhancer_label'] = [
      '#markup' => $this->t('Enhancer for: %name', ['%name' => $field_name]),
    ];

    // Build the select field for the list of enhancers.
    $overrides_form['enhancer'] = [
      '#wrapper_attributes' => ['colspan' => 2],
      '#type' => 'fieldgroup',
      '#states' => [
        'visible' => [
          ':input[name="resourceFields[' . $field_name . '][disabled]"]' => [
            'checked' => FALSE,
          ],
        ],
      ],
    ];
    $options = array_reduce(
      $this->enhancerManager->getDefinitions(),
      function (array $carry, array $definition) {
        $carry[$definition['id']] = $definition['label'];
        return $carry;
      },
      ['' => $this->t('- None -')]
    );
    $id = empty($resource_field['enhancer']['id'])
      ? ''
      : $resource_field['enhancer']['id'];
    $overrides_form['enhancer']['id'] = [
      '#type' => 'select',
      '#options' => $options,
      '#ajax' => [
        'callback' => '::getEnhancerSettings',
        'wrapper' => $field_name . '-settings-wrapper',
      ],
      '#default_value' => $id,
    ];
    $overrides_form['enhancer']['settings'] = [
      '#type' => 'container',
      '#attributes' => ['id' => $field_name . '-settings-wrapper'],
    ];
    if (!empty($resource_field['enhancer']['id'])) {
      /** @var \Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerInterface $enhancer */
      $enhancer = $this->enhancerManager
        ->createInstance($resource_field['enhancer']['id'], []);
      $overrides_form['enhancer']['settings'] += $enhancer
        ->getSettingsForm($resource_field);
    }
    return $overrides_form;
  }

  /**
   * AJAX callback to get the form settings for the enhancer for a field.
   *
   * @param array $form
   *   The reference to the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The specific form sub-tree in the form.
   */
  public static function getEnhancerSettings(array &$form, FormStateInterface $form_state) {
    // Find what is the field name that triggered the AJAX request.
    $user_input = $form_state->getUserInput();
    $parts = explode('[', $user_input['_triggering_element_name']);
    $field_name = rtrim($parts[1], ']');
    // Now return the sub-tree for the settings on the enhancer plugin.
    return $form['bundle_wrapper']['fields_wrapper']['overrides']['fields']['resourceFields'][$field_name]['enhancer']['settings'];
  }

  /**
   * {@inheritdoc}
   */
  protected function actionsElement(array $form, FormStateInterface $form_state) {
    // We want to display "Revert" instead of "Delete" on the Resource Config
    // Form.
    $element = parent::actionsElement($form, $form_state);
    if (isset($element['delete'])) {
      $element['delete']['#title'] = $this->t('Revert');
    }
    return $element;
  }

  /**
   * Gets all field names for a given entity type and bundle.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type for which to get all field names.
   * @param string $bundle
   *   The bundle for which to get all field names.
   *
   * @todo This is a copy of ResourceTypeRepository::getAllFieldNames. We can't
   * reuse that code because it's protected.
   *
   * @return string[]
   *   All field names.
   */
  protected function getAllFieldNames(EntityTypeInterface $entity_type, $bundle) {
    if (is_a($entity_type->getClass(), FieldableEntityInterface::class, TRUE)) {
      $field_definitions = $this->fieldManager->getFieldDefinitions(
        $entity_type->id(),
        $bundle
      );
      return array_keys($field_definitions);
    }
    elseif (is_a($entity_type->getClass(), ConfigEntityInterface::class, TRUE)) {
      // @todo Uncomment the first line, remove everything else once https://www.drupal.org/project/drupal/issues/2483407 lands.
      // return array_keys($entity_type->getPropertiesToExport());
      $export_properties = $entity_type->getPropertiesToExport();
      if ($export_properties !== NULL) {
        return array_keys($export_properties);
      }
      else {
        return ['id', 'type', 'uuid', '_core'];
      }
    }

    return [];
  }

}

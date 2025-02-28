<?php

namespace Drupal\jsonapi_extras;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Drupal\jsonapi_extras\ResourceType\NullJsonapiResourceConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of JSON:API Resource Config entities.
 */
class JsonapiResourceConfigListBuilder extends ConfigEntityListBuilder {

  /**
   * The JSON:API configurable resource type repository.
   *
   * @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceTypeRepository
   */
  protected $resourceTypeRepository;

  /**
   * The JSON:API extras config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * Constructs new JsonapiResourceConfigListBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The storage.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The config instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   Entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, ResourceTypeRepositoryInterface $resource_type_repository, ImmutableConfig $config, EntityTypeManagerInterface $entityTypeManager = NULL) {
    parent::__construct($entity_type, $storage);
    $this->resourceTypeRepository = $resource_type_repository;
    $this->config = $config;
    if ($entityTypeManager === NULL) {
      $entityTypeManager = \Drupal::entityTypeManager();
      @trigger_error('Calling ' . __METHOD__ . ' without the $entityTypeManager argument is deprecated in jsonapi_extras:8.x-3.20 and will be required in jsonapi_extras:8.x-4.0. See https://www.drupal.org/node/3242791', E_USER_DEPRECATED);
    }
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('jsonapi.resource_type.repository'),
      $container->get('config.factory')->get('jsonapi_extras.settings'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'name' => $this->t('Name'),
      'path' => $this->t('Path'),
      'state' => $this->t('State'),
      'operations' => $this->t('Operations'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $list = [];
    $resource_status = [
      'enabled' => t('Enabled Resources'),
      'disabled' => t('Disabled resources'),
    ];

    $title = $this->t('Filter resources by name, entity type, bundle or path.');
    $list['status']['filters']['text'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter'),
      '#title_display' => 'invisible',
      '#size' => 60,
      '#placeholder' => $title,
      '#attributes' => [
        'class' => ['jsonapi-resources-filter-text'],
        'data-table' => '.jsonapi-resources-table',
        'autocomplete' => 'off',
        'title' => $title,
      ],
    ];

    foreach ($resource_status as $status => $label) {
      $list[$status] = [
        '#type' => 'details',
        '#title' => $label,
        '#open' => $status === 'enabled',
        '#attributes' => [
          'id' => 'jsonapi-' . $status . '-resources-list',
        ],
        '#attached' => [
          'library' => [
            'jsonapi_extras/admin',
          ],
        ],
      ];

      $list[$status]['table'] = [
        '#type' => 'table',
        '#header' => [
          'name' => $this->t('Name'),
          'path' => $this->t('Path'),
          'state' => $this->t('State'),
          'operations' => $this->t('Operations'),
        ],
        '#attributes' => [
          'class' => [
            'jsonapi-resources-table',
          ],
        ],
        '#attached' => [
          'library' => [
            'jsonapi_extras/admin',
          ],
        ],
      ];
    }

    $prefix = $this->config->get('path_prefix');
    /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType[] $resource_types */
    $resource_types = $this->resourceTypeRepository->all();
    $default_disabled = $this->config->get('default_disabled');
    foreach ($resource_types as $resource_type) {
      // Other modules may create resource types, e.g. jsonapi_cross_bundles.
      $resource_config = $resource_type instanceof ConfigurableResourceType
        ? $resource_type->getJsonapiResourceConfig()
        : NULL;

      /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type */
      $entity_type_id = $resource_type->getEntityTypeId();
      $bundle = $resource_type->getBundle();

      $default_group = 'enabled';
      if ($resource_config && $resource_type->isInternal() && !$resource_config->get('disabled')) {
        // Either this item is marked internal by the entity-type OR the default
        // disabled setting is active.
        if (!$default_disabled) {
          // If default disabled is inactive, this entity-type is marked as
          // internal.
          continue;
        }

        // If default disabled is active, we need to make sure that the entity
        // type isn't marked internal before we present the option to edit and
        // therefore enable the resource type.
        $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);
        if ($entity_type_definition->isInternal()) {
          continue;
        }
        $default_group = 'disabled';
      }
      elseif (!$resource_config && $resource_type->isInternal()) {
        continue;
      }

      $group = ($resource_config && $resource_config->get('disabled')) || (!$resource_config && !$resource_type->isLocatable())
        ? 'disabled'
        : $default_group;
      $row = [
        'name' => ['#plain_text' => $resource_type->getTypeName()],
        'path' => [
          '#type' => 'html_tag',
          '#tag' => 'code',
          '#value' => sprintf('/%s/%s', $prefix, ltrim($resource_type->getPath(), '/')),
        ],
        'state' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('Default'),
          '#attributes' => [
            'class' => [
              'label',
            ],
          ],
        ],
        'operations' => $resource_config ? [
          '#type' => 'operations',
          '#links' => [
            'overwrite' => [
              'title' => $group === 'disabled' ? $this->t('Enable') : $this->t('Overwrite'),
              'weight' => -10,
              'url' => Url::fromRoute('entity.jsonapi_resource_config.add_form', [
                'entity_type_id' => $entity_type_id,
                'bundle' => $bundle,
              ]),
            ],
          ],
        ] : [],
      ];

      if ($resource_config && !($resource_config instanceof NullJsonapiResourceConfig)) {
        $row['state']['#value'] = $this->t('Overwritten');
        $row['state']['#attributes']['class'][] = 'label--overwritten';
        $row['operations']['#links'] = $this->getDefaultOperations($resource_config);
        $row['operations']['#links']['delete']['title'] = $this->t('Revert');
      }
      $list[$group]['table'][] = $row;
    }
    $list['#cache']['tags'][] = 'jsonapi_resource_types';

    return $list;
  }

}

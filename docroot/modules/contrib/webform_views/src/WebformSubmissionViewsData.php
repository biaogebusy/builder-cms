<?php

namespace Drupal\webform_views;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionViewsData as WebformSubmissionViewsDataBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Views data for 'webform_submission' entity type.
 */
class WebformSubmissionViewsData extends WebformSubmissionViewsDataBase {

  /**
   * @var WebformElementManagerInterface
   */
  protected $webformElementManager;

  /**
   * @var EntityStorageInterface
   */
  protected $webformStorage;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('plugin.manager.webform.element'),
      $container->get('entity_type.manager')->getStorage('webform')
    );
  }

  /**
   * Constructs a new WebformSubmissionViewsData object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\Sql\SqlEntityStorageInterface $storage_controller
   *   The database storage controller.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\webform\WebformElementManagerInterface $webform_element_manager
   *   The webform element manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $webform_storage
   *   The webform entity storage.
   */
  public function __construct(EntityTypeInterface $entity_type, SqlEntityStorageInterface $storage_controller, EntityTypeManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, TranslationInterface $translation_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, WebformElementManagerInterface $webform_element_manager, EntityStorageInterface $webform_storage) {
    parent::__construct($entity_type, $storage_controller, $entity_manager, $module_handler, $translation_manager, $entity_field_manager, $entity_type_bundle_info);

    $this->webformElementManager = $webform_element_manager;
    $this->webformStorage = $webform_storage;
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $base_table = $this->entityType->getBaseTable() ?: $this->entityType->id();

    $data[$base_table]['source_entity_label'] = [
      'title' => $this->t('Submitted to: Entity label'),
      'help' => $this->t('The label of entity to which this submission was submitted.'),
      'field' => [
        'id' => 'webform_submission_submitted_to_label',
      ],
    ];

    $data[$base_table]['source_entity_rendered_entity'] = [
      'title' => $this->t('Submitted to: Rendered entity'),
      'help' => $this->t('Rendered entity to which this submission was submitted.'),
      'field' => [
        'id' => 'webform_submission_submitted_to_rendered_entity',
      ],
    ];

    // Reverse relationship on the "entity_type" and "entity_id" columns, i.e.
    // from an arbitrary entity to webform submissions that have been submitted
    // to it.
    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      if ($definition instanceof ContentEntityTypeInterface) {
        $relationship = [
          'base' => $base_table,
          'field' => $definition->getKey('id'),
          'base field' => 'entity_id',
          'id' => 'standard',
          'extra' => [
            ['field' => 'entity_type', 'value' => $definition->id()],
          ],
        ];

        // Depending on whether the foreign entity has data table we join on its
        // data table or on its base table. Additionally, if we join on the data
        // table, then we also must join on langcode column.
        if ($definition->getDataTable()) {
          $foreign_table = $definition->getDataTable();
          $relationship['extra'][] = ['field' => 'langcode', 'left_field' => 'langcode'];
        }
        else {
          $foreign_table = $definition->getBaseTable();
        }

        $data[$foreign_table]['webform_submission'] = [
          'title' => $this->t('Webform submissions'),
          'help' => $this->t('Webform submissions submitted to an entity.'),
        ];

        $data[$foreign_table]['webform_submission']['relationship'] = $relationship;
      }
    }

    // Add non-admin "view", "duplicate" and "edit" links.
    $data[$base_table]['webform_submission_user_submission_view'] = [
      'title' => $this->t('Non-admin view link'),
      'help' => $this->t('Link to view a webform submission for non-admin users.'),
      'field' => [
        'id' => 'webform_submission_user_submission_view_field',
        'real field' => $this->entityType->getKey('id'),
        'click sortable' => FALSE,
      ],
    ];

    $data[$base_table]['webform_submission_user_submission_duplicate'] = [
      'title' => $this->t('Non-admin duplicate link'),
      'help' => $this->t('Link to duplicate a webform submission for non-admin users.'),
      'field' => [
        'id' => 'webform_submission_user_submission_duplicate_field',
        'real field' => $this->entityType->getKey('id'),
        'click sortable' => FALSE,
      ],
    ];

    $data[$base_table]['webform_submission_user_submission_edit'] = [
      'title' => $this->t('Non-admin edit link'),
      'help' => $this->t('Link to edit a webform submission for non-admin users.'),
      'field' => [
        'id' => 'webform_submission_user_submission_edit_field',
        'real field' => $this->entityType->getKey('id'),
        'click sortable' => FALSE,
      ],
    ];

    $data[$base_table]['webform_submission_notes_edit'] = [
      'title' => $this->t('Edit notes'),
      'help' => $this->t('In-line text area to edit webform submission notes.'),
      'field' => [
        'id' => 'webform_submission_notes_edit',
        'real field' => 'notes',
        'click sortable' => FALSE,
      ],
    ];

    $data[$base_table]['webform_category'] = [
      'title' => $this->t('Webform category'),
      'help' => $this->t('Webform category of webform submission.'),
      'filter' => [
        'id' => 'webform_views_webform_category',
        'real field' => $this->entityType->getKey('bundle'),
      ],
    ];

    $data[$base_table]['webform_status'] = [
      'title' => $this->t('Webform status'),
      'help' => $this->t('Status of a webform to which submission is submitted to.'),
      'filter' => [
        'id' => 'webform_views_webform_status',
        'real field' => $this->entityType->getKey('bundle'),
      ],
    ];

    // There is no general way to add a relationship to an entity where webform
    // submission has been submitted to, so we just cover the most common case
    // here - the case when the source is a node.
    if ($this->entityTypeManager->hasDefinition('node')) {
      $node_definition = $this->entityTypeManager->getDefinition('node');

      $data[$base_table]['entity_id'] += [
        'relationship' => [
          'base' => $node_definition->getDataTable(),
          'base field' => $node_definition->getKey('id'),
          'id' => 'standard',
          'label' => $this->t('Submitted to: Content'),
          'title' => $this->t('Submitted to: Content'),
          'extra' => [
            ['left_field' => 'entity_type', 'value' => 'node'],
          ],
        ],
      ];
    }

    // Add relationship from user to webform submissions he has submitted.
    $user_definition = $this->entityTypeManager->hasDefinition('user') ? $this->entityTypeManager->getDefinition('user') : FALSE;
    if ($user_definition && $user_definition->getDataTable()) {
      $data[$user_definition->getDataTable()]['webform_submission'] = [
        'title' => $this->t('Webform submission'),
        'help' => $this->t('Webform submission(-s) the user has submitted.'),
        'relationship' => [
          'relationship field' => $user_definition->getKey('id'),
          'base' => $base_table,
          'base field' => 'uid',
          'id' => 'standard',
          'label' => $this->t('Webform submission'),
        ],
      ];
    }

    foreach ($this->webformStorage->loadMultiple() as $webform) {
      foreach ($webform->getElementsInitializedAndFlattened() as $element) {
        $data = array_replace_recursive($data, $this->getWebformElementViewsData($element, $webform));
      }
    }

    return $data;
  }

  /**
   * Collect webform element views data.
   *
   * @param array $element
   *   Element whose views data is to be collected
   * @param \Drupal\webform\WebformInterface $webform
   *   Webform where $element belongs to
   *
   * @return array
   *   Views data that corresponds to the provided $element
   */
  protected function getWebformElementViewsData($element, WebformInterface $webform) {
    $data = [];

    $element_plugin = $this->webformElementManager->getElementInstance($element);
    if (isset($element_plugin->getPluginDefinition()['webform_views_handler'])) {
      $views_handler_class = $element_plugin->getPluginDefinition()['webform_views_handler'];
      $this->moduleHandler->alter('webform_views_element_views_handler', $views_handler_class, $element, $webform);
      if (is_subclass_of($views_handler_class, ContainerInjectionInterface::class)) {
        $views_handler = $views_handler_class::create(\Drupal::getContainer());
      }
      else {
        $views_handler = new $views_handler_class();
      }
      $data = $views_handler->getViewsData($element, $webform);
    }

    return $data;
  }

}

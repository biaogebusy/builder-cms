<?php

namespace Drupal\conflict\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\conflict\Entity\EntityConflictHandlerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

class ConflictResolutionInlineFormBuilder {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ConflictResolutionFormBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, TranslationInterface $string_translation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $string_translation;
  }

  /**
   * Adds the conflict resolution overview to the form.
   *
   * @param $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity of the form.
   */
  public function processForm(&$form, FormStateInterface $form_state, EntityInterface $entity) {
    if (!$entity instanceof ContentEntityInterface) {
      return;
    }
    // If the entity has not been flagged for manual merge then no need to
    // proceed here.
    // @see \Drupal\conflict\Entity\ContentEntityConflictHandler::prepareConflictResolution()
    if (!$entity->{EntityConflictHandlerInterface::CONFLICT_ENTITY_NEEDS_MANUAL_MERGE}) {
      return;
    }

    /** @var \Drupal\conflict\Entity\EntityConflictHandlerInterface $entity_conflict_resolution_handler */
    $entity_conflict_resolution_handler = $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'conflict.resolution_handler');

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_local_original */
    $entity_local_original = $entity->{EntityConflictHandlerInterface::CONFLICT_ENTITY_ORIGINAL};
    $entity_server = $entity->{EntityConflictHandlerInterface::CONFLICT_ENTITY_SERVER};

    $conflicts = [];
    if ($entity_server === 'removed') {
      $form['conflict_resolution_confirm_removed'] = [
        '#type' => 'checkbox',
        '#required' => TRUE,
        '#title' => $this->t('This %entity_type has been removed. Confirm if you want to keep it or remove it.',
          [
            '%entity_type' => $entity_local_original->getEntityType()
              ->getSingularLabel(),
          ]),
      ];
    }
    else {
      $conflicts = $entity_conflict_resolution_handler->getConflicts($entity_local_original, $entity, $entity_server);
    }

    foreach ($conflicts as $field_name => $conflict_type) {
      $form[$field_name]['conflict_resolution'] = [
        '#type' => 'details',
        '#title' => $entity->get($field_name)->getFieldDefinition()->getLabel() . ' - ' . $this->t('Conflict resolution'),
        '#open' => TRUE,
      ];
      $form[$field_name]['conflict_resolution']['overview'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Local version'),
          $this->t('Initial version'),
          $this->t('Server version'),
        ],
        '#rows' => [[
            ['data' => $entity->get($field_name)->view()],
            ['data' => $entity_local_original->get($field_name)->view()],
            ['data' => $entity_server->get($field_name)->view()],
        ]],
      ];
      $form[$field_name]['conflict_resolution']['confirm'] = [
        '#type' => 'checkbox',
        '#required' => TRUE,
        '#title' => $this->t('Manual merge completed'),
      ];
    }

    foreach ($conflicts as $field_name => &$conflict_type) {
      $conflict_type = ['conflict-type' => $conflict_type];
    }
    $manual_merge_conflicts = $form_state->get('manual-merge-conflicts');
    if ($manual_merge_conflicts === NULL) {
      $form_state->set('manual-merge-conflicts', []);
      $manual_merge_conflicts = $form_state->get('manual-merge-conflicts');
    }
    $path_to_entity = $form['#parents'];
    array_pop($path_to_entity);
    $conflicts_with_path = [];
    NestedArray::setValue($conflicts_with_path, $path_to_entity, $conflicts);
    $manual_merge_conflicts = array_merge_recursive($manual_merge_conflicts, $conflicts_with_path);
    $form_state->set('manual-merge-conflicts', $manual_merge_conflicts);

    $this->entityTypeManager->getHandler($entity->getEntityTypeId(), 'conflict.resolution_handler')
      ->finishConflictResolution($entity, [], $form_state);

    // Ensure the form will not be flagged for rebuild.
    // @see \Drupal\conflict\Entity\ContentEntityConflictHandler::entityMainFormValidateLast().
    $form_state->set('conflict.paths', []);

    $message = $this->t('The content has either been modified by another user, or you have already submitted modifications. Manual merge of the conflicts is required.');
    $form['#attached']['drupalSettings']['conflict']['inlineResolutionMessage'] = (string) $message;

    $form['#attached']['library'][] = 'conflict/drupal.conflict_resolution';
  }

}

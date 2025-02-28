<?php

namespace Drupal\conflict\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

interface EntityConflictHandlerInterface {

  /**
   * The entity object property name to use to append the original entity.
   */
  const CONFLICT_ENTITY_ORIGINAL = 'conflictEntityOriginal';

  /**
   * The entity object property name to use to set the original entity's hash.
   */
  const CONFLICT_ENTITY_ORIGINAL_HASH = 'conflictEntityOriginalHash';

  /**
   * The entity object property name to use to append the merged server entity.
   */
  const CONFLICT_ENTITY_SERVER = 'conflictEntityServer';

  /**
   * The entity object property name to use to append the form display.
   */
  const CONFLICT_FORM_DISPLAY = 'conflictFormDisplay';

  /**
   * The entity object property name to use as a needs merge flag.
   */
  const CONFLICT_ENTITY_NEEDS_MANUAL_MERGE = 'conflictEntityNeedsManualMerge';

  /**
   * A constant defining the property name on fields for the conflict type.
   */
  const CONFLICT_TYPE_FIELD_PROPERTY = 'conflictType';

  /**
   * Performs the needed alterations to the entity form.
   *
   * @param array $form
   *   The entity form to be altered to provide the translation workflow.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being created or edited.
   * @param bool $inline_entity_form
   *   (optional) TRUE if an inline entity form is given, FALSE if a regular
   *   entity form is given. Defaults to FALSE.
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity, $inline_entity_form = FALSE);

  /**
   * Returns the entity conflicts.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity_local_original
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity_local_edited
   *   The original not edited entity used to build the form.
   * @param \Drupal\Core\Entity\EntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   *
   * @return array
   *   An array of conflicts, keyed by the field name having as value the
   *   conflict type.
   */
  public function getConflicts(EntityInterface $entity_local_original, EntityInterface $entity_local_edited, EntityInterface $entity_server);

  /**
   * Finishes the conflict resolution after the user interaction (manual merge).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param array $path_parents
   *   The parents to the entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *  The current state of the form.
   */
  public function finishConflictResolution(EntityInterface $entity, $path_parents, FormStateInterface $form_state);

  /**
   * Prepares the entity for the manual conflict resolution.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The current entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity_server
   *   (optional) The server entity i.e. the current entity from the storage or
   *   NULL if the entity has been deleted or in case of inline reference the
   *   relationship was removed.
   */
  public function prepareConflictResolution(EntityInterface $entity, EntityInterface $entity_server = NULL);

}

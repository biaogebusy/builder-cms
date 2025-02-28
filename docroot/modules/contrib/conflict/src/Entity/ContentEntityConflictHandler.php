<?php

namespace Drupal\conflict\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\conflict\ConflictTypes;
use Drupal\conflict\FieldComparatorManagerInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

class ContentEntityConflictHandler implements EntityConflictHandlerInterface, EntityHandlerInterface {

  use StringTranslationTrait;
  use MessengerTrait;
  use DependencySerializationTrait {
    __wakeup as traitWakeup;
    __sleep as traitSleep;
  }

  /**
   * The entity type.
   *
   * @var \Drupal\Core\Entity\ContentEntityTypeInterface
   */
  protected $entityType;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The key value store storing the original entity.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValueOriginalEntity;

  /**
   * The field comparator manager.
   *
   * @var \Drupal\conflict\FieldComparatorManagerInterface
   */
  protected $fieldComparatorManager;

  /**
   * The conflict manager.
   *
   * @var \Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface
   */
  protected $conflictManager;

  /**
   * EntityConflictResolutionHandlerDefault constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value_original_entity
   *   The key value factory for storing the conflict original entity.
   * @param \Drupal\conflict\FieldComparatorManagerInterface $field_comparator_manager
   *   The field comparator manager.
   * @param \Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_manager
   *   The conflict manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, KeyValueStoreInterface $key_value_original_entity, FieldComparatorManagerInterface $field_comparator_manager, ConflictResolverManagerInterface $conflict_manager) {
    $this->entityType = $entity_type;
    $this->entityTypeManager = $entity_type_manager;
    $this->storage = $entity_type_manager->getStorage($entity_type->id());
    $this->moduleHandler = $module_handler;
    $this->keyValueOriginalEntity = $key_value_original_entity;
    $this->fieldComparatorManager = $field_comparator_manager;
    $this->conflictManager = $conflict_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('keyvalue.expirable')->get('conflict_original_entity'),
      $container->get('conflict.field_comparator.manager'),
      $container->get('conflict_resolver.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function entityFormAlter(array &$form, FormStateInterface $form_state, EntityInterface $entity, $inline_entity_form = FALSE) {
    // Let the conflict module updates the other translations before any other
    // entity builder has run, otherwise we might overwrite changes that will be
    // made by the entity builders on other translations. An example for this is
    // \Drupal\content_translation\ContentTranslationHandler::entityFormEntityBuild().
    $form['#entity_builders'] = isset($form['#entity_builders']) ? $form['#entity_builders'] : [];
    array_unshift($form['#entity_builders'], [$this, 'entityFormEntityBuilder']);

    if (!isset($form['conflict_entity_original_hash'])) {
      $input = $form_state->getUserInput();
      $hash_path = $form['#parents'];
      $hash_path[] = 'conflict_entity_original_hash';
      $hash = NestedArray::getValue($input, $hash_path) ?: $entity->{EntityConflictHandlerInterface::CONFLICT_ENTITY_ORIGINAL_HASH};

      $form['conflict_entity_original_hash'] = [
        '#type' => 'hidden',
        '#default_value' => $hash,
      ];
    }
    // @todo This check is actually not really needed, as #validate is only
    // executed at form level, not at inline form level, where only
    // #element_validate should be executed.
    if (!$inline_entity_form) {
      // @todo we have to ensure that our validate method is running at the end
      // but if there is another module which is moving its form_alter hook to
      // the end then there might be some collision. Should we decorate the
      // form builder instead and add our validate method after all the hooks
      // have run?
      $form['#validate'][] = [$this, 'entityMainFormValidateLast'];
    }
  }

  /**
   * Entity builder method.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param $form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\conflict\Entity\ContentEntityConflictHandler::entityFormAlter()
   */
  public function entityFormEntityBuilder($entity_type_id, EntityInterface $entity, &$form, FormStateInterface $form_state) {
    // Run only as part of the final form level submission.
    if (!$this->isFormLevelSubmission($form_state)) {
      return;
    }

    if ($entity instanceof ContentEntityInterface && !$entity->isNew()) {
      if ($entity->isDefaultRevision()) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_server */
        $id = $entity->id();
        $entity_server = $this->storage->loadUnchanged($id);
      }
      else {
        // TODO - how to deal with forward revisions?
        return;
      }

      $input = $form_state->getUserInput();
      $hash_path = $form['#parents'];
      $hash_path[] = 'conflict_entity_original_hash';
      $hash = NestedArray::getValue($input, $hash_path);
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity_original */
      $entity_original = unserialize($this->keyValueOriginalEntity->get($hash));

      // Set the original entity as it might have changed. The original entity
      // in our context is the one that was used to build the form initially
      // and not the unchanged entity.
      $entity->{static::CONFLICT_ENTITY_ORIGINAL} = $entity_original;

      // Currently we do not support concurrent editing in the following cases:
      //  - editing a translation that is removed on the newest version.
      //  - while creating a new translation.
      $edited_langcode = $entity->language()->getId();
      if (!$entity_server->hasTranslation($edited_langcode)) {
        if ($entity_original->hasTranslation($edited_langcode)) {
          // Currently being on a translation that has been removed in the
          // newest version.
          $form_state->setError($form, t('You are editing a translation, that has been removed meanwhile. As a result, your changes cannot be saved.'));
          return;
        }
        else {
          // @todo A new translation is being added. Currently we do not have
          // any support for concurrent editing during translating content. If
          // the entity has been modified meanwhile then the
          // EntityChangedConstraintValidator will fail.
          return;
        }
      }
      else {
        if (!$entity_original->hasTranslation($edited_langcode)) {
          $form_state->setError($form, t('You are creating a translation, that has been created meanwhile. As a result, your changes cannot be saved.'));
          return;
        }
      }

      // Work directly with the entity translations.
      $entity_server = $entity_server->getTranslation($edited_langcode);
      $entity_original = $entity_original->getTranslation($edited_langcode);

      // Check if the entity requires a merge.
      $needs_merge = $this->needsMerge($entity, $entity_original, $entity_server, FALSE);
      if ($needs_merge) {
        /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
        $form_display = $form_state->getFormObject()
          ->getFormDisplay($form_state);

        // Append each field's widget to the field so that further processing
        // can access it.
        foreach (array_keys($form_display->getComponents()) as $field_name) {
          if ($entity->getFieldDefinition($field_name)) {
            $entity->get($field_name)->conflictWidget = $form_display->getRenderer($field_name);
          }
        }

        // Auto merge changes in other translations.
        $this->autoMergeNonEditedTranslations($entity, $entity_server);
        // Auto merge changes in non-editable fields.
        $this->autoMergeNonEditableFields($entity, $entity_server, $form_display);
        // Auto merge non-changed fields.
        $this->autoMergeNotTouchedFields($entity, $entity_original, $entity_server, $form, $form_state);
        // Auto merge entity metadata.
        $this->autoMergeEntityMetadata($entity, $entity_server, $form, $form_state);

        // Run the entities through the event system for conflict discovery
        // and resolution.
        $context = new ParameterBag(['form' => $form, 'form_state' => $form_state, 'form_display' => $form_display]);
        // Disable the merge remote only changes strategy, which is enabled by
        // default.
        // TODO reconsider the decision for having all strategies enabled by
        // default! They should be instead enabled through configuration and/or
        // hooks/events.
        $context->set('merge_strategy.disabled', ['conflict.merge_remote_only_changes']);
        $conflicts = $this->conflictManager->resolveConflicts($entity, $entity_server, $entity_original, $entity, $context);

        // In case the entity still has conflicts then a user interaction is
        // needed.
        $needs_merge = !empty($conflicts) || $this->needsMerge($entity, $entity_original, $entity_server, TRUE);
        if ($needs_merge) {
          // Prepare the entity for conflict resolution.
          $this->prepareConflictResolution($entity, $entity_server);

          // If the entity supports conflict UI merge then add the path to it to
          // the form state storage, otherwise flag the form with an error as it
          // would've been flagged by the entity constraint "EntityChanged".
          // @see \Drupal\Core\Entity\Plugin\Validation\Constraint\EntityChangedConstraint::$message
          // @see \Drupal\Core\Entity\Plugin\Validation\Constraint\EntityChangedConstraintValidator::validate()
          if ($this->entityType->get('conflict_ui_merge_supported') && !(bool) $form_state->get('manual-merge-not-possible')) {
            $path = implode('.', $form['#parents']);
            $conflict_paths = $form_state->get('conflict.paths') ?: [];
            $conflict_paths[$path] = ['entity_type' => $entity_type_id, 'entity_id' => $entity->id()];
            $form_state->set('conflict.paths', $conflict_paths);
          }
          else {
            $message = t('The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.');
            $message .= ' ' . t('Unfortunately no conflict resolution could be provided for the set of changes.');
            $form_state->setError($form, $message);
          }
        }
      }
    }
  }

  /**
   * Form level validation handler running after all others.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\conflict\Entity\ContentEntityConflictHandler::entityFormAlter()
   */
  public function entityMainFormValidateLast(&$form, FormStateInterface $form_state) {
    // Run only as part of the final form level submission or if the form has
    // been completely validated and has no errors.
    if (!$this->isFormLevelSubmission($form_state) || $form_state::hasAnyErrors() || empty($form_state->get('conflict.paths'))) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    // Support https://www.drupal.org/node/2833682.
    if (method_exists($form_object, 'getIntermediateEntity')) {
      $entity = $form_object->getIntermediateEntity();
    }
    else {
      $entity = $form_object->buildEntity($form, $form_state);
    }
    // Exchange the entity with the intermediate one and flag the form so that
    // the conflict resolution form gets appended on form rebuild.
    $form_object->setEntity($entity);
    $form_state->set('conflict.build_conflict_resolution_form', TRUE);
    $form_state->setCached(TRUE);
    $form_state->setRebuild(TRUE);

    $conflict_paths = &$form_state->get('conflict.paths');
    $this->moduleHandler->alter('conflict_paths', $conflict_paths, $form_state);
  }

  /**
   * Performs a check if a merge is required.
   *
   * Note that if the entity doesn't implement the EntityChangedInterface and no
   * extended check ($extended_check = FALSE) is performed, then the method will
   * return TRUE as there is no short way of checking for changes, in which case
   * the extended check should be performed afterwards.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_edited
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_original
   *   The original not edited entity used to build the form.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   * @param bool $extended_check
   *   Specifies whether an extended check should be performed.
   *
   * @return bool
   *   TRUE if a merge is needed, FALSE otherwise.
   */
  protected function needsMerge(ContentEntityInterface $entity_local_edited, ContentEntityInterface $entity_local_original, ContentEntityInterface $entity_server, $extended_check) {
    if ($this->entityType->isRevisionable()) {
      if (($entity_local_edited->getRevisionId() != $entity_server->getRevisionId()) || ($entity_local_edited->getLoadedRevisionId() != $entity_server->getLoadedRevisionId())) {
        return TRUE;
      }
    }
    if ($extended_check) {
      return $this->hasConflicts($entity_local_edited, $entity_local_original, $entity_server);
    }
    else {
      $entity_server_langcodes = array_keys($entity_server->getTranslationLanguages());
      $entity_local_edited_langcodes = array_keys($entity_local_edited->getTranslationLanguages());
      if ($entity_server_langcodes != $entity_local_edited_langcodes) {
        return TRUE;
      }
      if ($entity_local_edited instanceof EntityChangedInterface) {
        foreach ($entity_server_langcodes as $langcode) {
          /** @var \Drupal\Core\Entity\EntityChangedInterface $entity_server_translation */
          $entity_server_translation = $entity_server->getTranslation($langcode);
          /** @var \Drupal\Core\Entity\EntityChangedInterface $entity_local_edited_translation */
          $entity_local_edited_translation = $entity_local_edited->getTranslation($langcode);
          if ($entity_server_translation->getChangedTime() != $entity_local_edited_translation->getChangedTime()) {
            return TRUE;
          }
        }
      }
      else {
        // If the entity doesn't implement the  EntityChangedInterface and a
        // non-extended check is performed then we return TRUE, so that
        // auto-merge is executed and an extended check is made afterwards.
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Merges translatable fields of non-edited translations.
   *
   * Additionally deleted translations will be removed and new translations will
   * be added.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_edited
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   */
  protected function autoMergeNonEditedTranslations(ContentEntityInterface $entity_local_edited, ContentEntityInterface $entity_server) {
    $edited_langcode = $entity_local_edited->language()->getId();
    $entity_server_langcodes = array_keys($entity_server->getTranslationLanguages());
    $entity_local_edited_langcodes = array_keys($entity_local_edited->getTranslationLanguages());

    foreach ($entity_server_langcodes as $langcode) {
      if ($langcode == $edited_langcode) {
        continue;
      }
      // @todo we should set that the translation is not new.
      $entity_server_translation = $entity_server->getTranslation($langcode);
      $entity_local_edited_translation = $entity_local_edited->hasTranslation($langcode) ? $entity_local_edited->getTranslation($langcode) : $entity_local_edited->addTranslation($langcode);

      // @todo If the entity implements the EntityChangedInterface then first
      // check the changed timestamps as a shortcut to skip updating fields of
      // translations that haven't changed, but for that we have to ensure
      if ($entity_server_translation->getChangedTime() != $entity_local_edited_translation->getChangedTime()) {
        foreach ($entity_server_translation->getTranslatableFields() as $field_name => $field_item_list) {
          $entity_local_edited_translation->set($field_name, $field_item_list->getValue());
        }
      }
    }

    foreach (array_diff($entity_local_edited_langcodes, $entity_server_langcodes) as $langcode) {
      $entity_local_edited->removeTranslation($langcode);
    }
  }

  /**
   * Merges non-editable fields.
   *
   * As non-editable fields are considered fields that are not contained in the
   * form display or the current user does not have edit access for them.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_edited
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display
   *   The form display that the entity form operates with.
   */
  protected function autoMergeNonEditableFields(ContentEntityInterface $entity_local_edited, ContentEntityInterface $entity_server, EntityFormDisplayInterface $form_display) {
    $components = $form_display->getComponents();
    foreach ($entity_local_edited->getFields() as $field_name => $items) {
      if (!isset($components[$field_name]) || !$items->access('edit')) {
        $items->setValue($entity_server->get($field_name)->getValue(TRUE));
      }
    }
  }

  /**
   * Merges non-touched fields i.e. prevents reverts.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_edited
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_original
   *   The original not edited entity used to build the form.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   * @param array $form
   *   The form array of the entity form. Might be used to retrieve the path to
   *   the entity in the form state values or user input.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Might be used to alter the user input to
   *   reflect new metadata from the server entity.
   */
  protected function autoMergeNotTouchedFields(ContentEntityInterface $entity_local_edited, ContentEntityInterface $entity_local_original, ContentEntityInterface $entity_server, $form, FormStateInterface $form_state) {
    $conflicts = $this->getConflicts($entity_local_original, $entity_local_edited, $entity_server);
    if ($conflicts) {
      $input = &$form_state->getUserInput();
      $auto_merged_untouched_fields = [];
      foreach ($conflicts as $field_name => $conflict_type) {
        if ($conflict_type === ConflictTypes::CONFLICT_TYPE_REMOTE) {
          $entity_local_edited->set($field_name, $entity_server->get($field_name)->getValue());
          $auto_merged_untouched_fields[] = $field_name;

          // Remove the value from the user input as there might be conflicts and
          // the form will be returned back to the user for manually resolving
          // them. In this case we want to show the auto merged values and notify
          // the user about this action.
          $path = $form['#parents'];
          $path[] = $field_name;
          NestedArray::unsetValue($input, $path);
        }
      }
      if ($auto_merged_untouched_fields) {
        // Conflicts can be present only in fields, which are presented in the
        // form, but it might happen that there is custom code updating a field
        // not part of the form, in which case it will be confusing for the user
        // if we notify about an auto merge on that field. Therefore we ensure
        // that we notify the user only about auto merges on fields in the
        // current form.

        /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
        $form_display = $form_state->getFormObject()
          ->getFormDisplay($form_state);
        $auto_merged_untouched_fields_form = array_intersect($auto_merged_untouched_fields, array_keys($form_display->getComponents()));
        if ($auto_merged_untouched_fields_form) {
          $field_labels = array_map(function ($name) use ($entity_local_edited) {
            return $entity_local_edited->get($name)->getFieldDefinition()->getLabel();
          }, $auto_merged_untouched_fields);

          // Notify the user about the auto merged fields.
          $this->messenger()->addMessage($this->t('The content has been modified meanwhile. Changes for the following fields have been successfully applied: %fields.', ['%fields' => \implode(', ', $field_labels)]));
        }
      }
    }
  }

  /**
   * Merges entity metadata.
   *
   * For entities implementing the EntityChangedInterface, the changed time will
   * be merged.
   * For revisionable entities the revision ID will be merged.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_edited
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   * @param array $form
   *   The form array of the entity form. Might be used to retrieve the path to
   *   the entity in the form state values or user input.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Might be used to alter the user input to
   *   reflect new metadata from the server entity.
   */
  protected function autoMergeEntityMetadata(ContentEntityInterface $entity_local_edited, ContentEntityInterface $entity_server, $form, FormStateInterface $form_state) {
    if ($entity_local_edited instanceof EntityChangedInterface && ($entity_local_edited->getChangedTime() != $entity_server->getChangedTime())) {
      $entity_local_edited->setChangedTime($entity_server->getChangedTime());

      // We have to update the changed timestamp stored as hidden value in the
      // form to the new value resulted from the merge, otherwise the old one
      // will be mapped on submit.
      $changed_path = $form['#parents'];
      $changed_path[] = 'changed';
      $input = &$form_state->getUserInput();
      NestedArray::setValue($input, $changed_path, $entity_local_edited->getChangedTime());
    }
    if ($this->entityType->isRevisionable()) {
      $entity_local_edited->set($this->entityType->getKey('revision'), $entity_server->getRevisionId());
      $entity_local_edited->updateLoadedRevisionId();
    }
  }

  /**
   * Checks whether there are conflicts.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_edited
   *   The locally edited entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_local_original
   *   The original not edited entity used to build the form.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity_server
   *   The unchanged entity loaded from the storage.
   *
   * @return bool
   *
   */
  protected function hasConflicts(ContentEntityInterface $entity_local_edited, ContentEntityInterface $entity_local_original, ContentEntityInterface $entity_server) {
    $entity_type_id = $this->entityType->id();
    $entity_bundle = $entity_local_edited->bundle();
    $langcode = $entity_local_edited->language()->getId();
    $entity_server = $entity_server->getTranslation($langcode);
    $entity_local_original = $entity_local_original->getTranslation($langcode);

    $skip_fields = [];
    // The revision created field is updated constantly and it will always cause
    // conflicts, therefore we skip it here, as it gets updated correctly on
    // submit during entity building from user input.
    // @see \Drupal\Core\Entity\ContentEntityForm::buildEntity().
    $skip_fields = array_flip($this->entityType->getRevisionMetadataKeys());

    foreach ($entity_local_edited->getFields() as $field_name => $field_items_list_local_edited) {
      if (isset($skip_fields[$field_name])) {
        continue;
      }
      $field_definition = $field_items_list_local_edited->getFieldDefinition();
      // There could be no conflicts in read only fields.
      if ($field_definition->isReadOnly()) {
        continue;
      }
      $field_type = $field_definition->getType();
      $field_items_list_server = $entity_server->get($field_name);
      $field_items_list_local_original = $entity_local_original->get($field_name);

      // Check for changes between the server and the locally edited version. If
      // there are no changes between them then it might happen that a field
      // is changed in both versions to the same value, which we do not
      // consider as any conflict.
      if ($this->fieldComparatorManager->hasChanged($field_items_list_server, $field_items_list_local_edited, $langcode, $entity_type_id, $entity_bundle, $field_type, $field_name)) {
        // Check for changes between the server the locally used original
        // version. If the server version has changed compared to the locally
        // used original version then there is a conflict either
        //   - value changed only on the server
        //   - value changed both on the server and locally
        if ($this->fieldComparatorManager->hasChanged($field_items_list_server, $field_items_list_local_original, $langcode, $entity_type_id, $entity_bundle, $field_type, $field_name)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConflicts(EntityInterface $entity_local_original, EntityInterface $entity_local_edited, EntityInterface $entity_server) {
    $conflicts = [];

    $entity_type_id = $this->entityType->id();
    $entity_bundle = $entity_local_edited->bundle();

    $langcode = $entity_local_edited->language()->getId();
    $entity_server = $entity_server->getTranslation($langcode);
    $entity_local_original = $entity_local_original->getTranslation($langcode);

    // The revision created field is updated constantly and it will always cause
    // conflicts, therefore we skip it here, as it gets updated correctly on
    // submit during entity building from user input.
    // @see \Drupal\Core\Entity\ContentEntityForm::buildEntity().
    $skip_fields = array_flip($this->entityType->getRevisionMetadataKeys());

    foreach ($entity_local_edited->getFields() as $field_name => $field_items_list_local_edited) {
      if (isset($skip_fields[$field_name])) {
        continue;
      }

      $field_definition = $field_items_list_local_edited->getFieldDefinition();
      // There could be no conflicts in read only fields.
      if ($field_definition->isReadOnly()) {
        continue;
      }
      $field_type = $field_definition->getType();
      $field_items_list_server = $entity_server->get($field_name);
      $field_items_list_local_original = $entity_local_original->get($field_name);

      $conflict_type = $this->fieldComparatorManager->getConflictType($field_items_list_local_edited, $field_items_list_server, $field_items_list_local_original, $langcode, $entity_type_id, $entity_bundle, $field_type, $field_name);
      if ($conflict_type) {
        $conflicts[$field_name] = $conflict_type;
      }
    }
    return $conflicts;
  }

  /**
   * Determines if the form level submission has been triggered.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   TRUE if the form has been submitted for final submission, FALSE otherwise.
   */
  protected function isFormLevelSubmission(FormStateInterface $form_state) {
    // @todo find a safer way of determining if this is a form level submission.
    return in_array('::submitForm', $form_state->getSubmitHandlers());
  }

  /**
   * {@inheritdoc}
   */
  public function prepareConflictResolution(EntityInterface $entity, EntityInterface $entity_server = NULL) {
    // Manual merge is needed if even after the auto-merge of non-edited
    // translations, fields with no edit access and entity metadata there
    // are still conflicts in the current translation and/or
    // non-translatable fields.
    $entity->{static::CONFLICT_ENTITY_NEEDS_MANUAL_MERGE} = TRUE;

    // Append the server entity that will be used for building the manual
    // conflict resolution.
    $entity->{static::CONFLICT_ENTITY_SERVER} = $entity_server ?? 'removed';
  }

  /**
   * {@inheritdoc}
   */
  public function finishConflictResolution(EntityInterface $entity, $path_parents, FormStateInterface $form_state) {
    // Exchange the original entity with the server one as after the user
    // interaction (manual merge) the current entity has as origin the server
    // entity.
    $entity_server = $entity->{static::CONFLICT_ENTITY_SERVER};
    $entity->{static::CONFLICT_ENTITY_ORIGINAL} = $entity_server;
    $entity_server_hash = $entity_server->{static::CONFLICT_ENTITY_ORIGINAL_HASH};
    $entity->{static::CONFLICT_ENTITY_ORIGINAL_HASH} = $entity_server_hash;

    // Flag the entity that it doesn't need a manual merge anymore.
    $entity->{static::CONFLICT_ENTITY_NEEDS_MANUAL_MERGE} = FALSE;

    // Exchange the original entity's hash value in the user input as well to be
    // prepared for next submits in case the form isn't submitted immediately,
    // but the work is continued.
    $input = &$form_state->getUserInput();
    $hash_path_parents = $path_parents;
    $hash_path_parents[] = 'conflict_entity_original_hash';
    NestedArray::setValue($input, $hash_path_parents, $entity_server_hash);
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    $this->traitWakeup();
    $this->storage = $this->entityTypeManager->getStorage($this->entityType->id());
  }

  /**
   *{ @inheritdoc}
   */
  public function __sleep() {
    $vars = $this->traitSleep();
    unset($vars['storage']);
    return $vars;
  }

}

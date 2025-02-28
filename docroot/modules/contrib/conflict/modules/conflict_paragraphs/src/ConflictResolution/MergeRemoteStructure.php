<?php

namespace Drupal\conflict_paragraphs\ConflictResolution;

use Drupal\Component\Utility\NestedArray;
use Drupal\conflict\ConflictResolution\MergeStrategyBase;
use Drupal\conflict\Entity\EntityConflictHandlerInterface;
use Drupal\conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

class MergeRemoteStructure extends MergeStrategyBase {

  /**
   * {@inheritdoc}
   */
  public function getMergeStrategyId() : string {
    return 'conflict.merge_remote_paragraph_structure';
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflictsContentEntity(EntityConflictResolutionEvent $event) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $local_entity */
    $local_entity = $event->getLocalEntity();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $remote_entity */
    $remote_entity = $event->getRemoteEntity();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $original_entity */
    $original_entity = $event->getBaseEntity();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $result_entity */
    $result_entity = $event->getResultEntity();

    /** @var \Drupal\Core\Form\FormStateInterface $form_state */
    $form_state = $event->getContextParameter('form_state');

    // TODO this supports only paragraphs at first level.
    foreach ($event->getConflicts() as $property => $conflict_type) {
      $field_definition = $remote_entity->getFieldDefinition($property);
      if (ParagraphsWidget::isApplicable($field_definition)) {
        $local_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $local_entity->get($property)->getValue());
        $server_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $remote_entity->get($property)->getValue());
        $original_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $original_entity->get($property)->getValue());

        $local_paragraph_ids_sorted = $local_paragraph_ids_unsorted;
        $server_paragraph_ids_sorted = $server_paragraph_ids_unsorted;
        $original_paragraph_ids_sorted = $original_paragraph_ids_unsorted;
        sort($local_paragraph_ids_sorted);
        sort($server_paragraph_ids_sorted);
        sort($original_paragraph_ids_sorted);

        // No entities have been added or deleted in the server version.
        if ($server_paragraph_ids_sorted == $original_paragraph_ids_sorted) {
          // If the server version has been saved with different sorting, then
          // apply this sorting to the current result entity and widget state.
          /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $field_item_list */
          $field_item_list = $result_entity->get($property);
          $server_field_item_list = $remote_entity->get($property);

          $field_item_list_unsorted = clone $field_item_list;
          $field_item_list->setValue(NULL);

          foreach ($server_field_item_list as $index => $server_field_item) {
            $entity_id = $server_field_item->target_id;
            foreach ($field_item_list_unsorted as $local_item) {
              if ($entity_id == $local_item->target_id) {
                // If an entity has been flagged as needing a manual merge, then
                // we have to keep the local version for the merge UI, otherwise
                // we can inherit the remote version.
                if ($local_item->entity->{EntityConflictHandlerInterface::CONFLICT_ENTITY_NEEDS_MANUAL_MERGE}) {
                  $item_value = $local_item->getValue();
                  $item_value['entity'] = $local_item->entity;
                }
                else {
                  $item_value = $server_field_item->getValue();
                  $item_value['entity'] = $server_field_item->entity;

                  if ($form_state) {
                    // During a form submission this code will be called twice
                    // - first during the validation to build the entity and
                    // then during the submission. However if we exchange the
                    // server entity with the static reference too early, then
                    // the old user input will be mapped on it. Therefore we
                    // need to exchange it with the proper reference first after
                    // the validation is complete. Until then break the global
                    // reference by using a clone.
                    if (!$form_state->isValidationComplete()) {
                      $item_value['entity'] = clone $item_value['entity'];
                    }

                    // Prevent mapping the user input. On form rebuild the
                    // values from the remote entity should be used instead of
                    // the ones submitted in the current session.
                    $path = [$property, $index];
                    $input =& $form_state->getUserInput();
                    NestedArray::unsetValue($input, $path);
                  }
                }
                $field_item_list->appendItem($item_value);
                break;
              }
            }
          }

          // TODO this supports only paragraphs at first level.
          if ($form_state) {
            $this->reorderWidgetState($field_item_list, $form_state);
          }
        }
        else {
          $new_paragraph_ids = array_diff($server_paragraph_ids_sorted, $original_paragraph_ids_sorted);
          $removed_paragraph_ids = array_diff($original_paragraph_ids_sorted, $server_paragraph_ids_sorted);

          // Only new paragraphs added.
          if ($new_paragraph_ids && empty($removed_paragraph_ids)) {
            /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $field_item_list */
            $field_item_list = $result_entity->get($property);
            $server_field_item_list = $remote_entity->get($property);

            $field_item_list_unsorted = clone $field_item_list;
            $field_item_list->setValue(NULL);

            foreach ($server_paragraph_ids_unsorted as $index => $entity_id) {
              foreach ($field_item_list_unsorted as $item) {
                if ($entity_id == $item->target_id) {
                  $item_value = $item->getValue();
                  $item_value['entity'] = $item->entity;
                  $field_item_list->appendItem($item_value);
                  // Found, do not search in the server list to append.
                  continue 2;
                }
              }
              foreach ($server_field_item_list as $item) {
                if ($entity_id == $item->target_id) {
                  $item_value = $item->getValue();
                  $item_value['entity'] = $item->entity;
                  $field_item_list->appendItem($item_value);

                  if ($form_state && !$form_state->isValidationComplete()) {
                    $item_value['entity'] = clone $item_value['entity'];
                  }
                  break;
                }
              }
            }

            // TODO this supports only paragraphs at first level.
            if ($form_state) {
              $this->reorderWidgetState($field_item_list, $form_state);
            }
          }
          // Only existing paragraphs removed.
          elseif ($removed_paragraph_ids && empty($new_paragraph_ids)) {
            /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $field_item_list */
            $field_item_list = $result_entity->get($property);

            $field_item_list_unsorted = clone $field_item_list;
            $field_item_list->setValue(NULL);

            foreach ($field_item_list_unsorted as $item) {
              if (!in_array($item->target_id, $removed_paragraph_ids)) {
                $item_value = $item->getValue();
                $item_value['entity'] = $item->entity;
                $field_item_list->appendItem($item_value);
              }
              else {
                // Keep the entity only if it is  flagged as conflicting,
                // otherwise it was not changed by the current session and can
                // be removed.
                $entity = $item->entity;
                if ($entity->{EntityConflictHandlerInterface::CONFLICT_ENTITY_NEEDS_MANUAL_MERGE} && $entity->{EntityConflictHandlerInterface::CONFLICT_ENTITY_SERVER} === 'removed') {
                  $item_value = $item->getValue();
                  $item_value['entity'] = $item->entity;
                  $field_item_list->appendItem($item_value);
                }
              }
            }

            // TODO this supports only paragraphs at first level.
            if ($form_state) {
              $this->reorderWidgetState($field_item_list, $form_state);
            }
          }
          // Both new paragraphs added and existing paragraphs deleted.
          elseif (!empty($removed_paragraph_ids) && !empty($new_paragraph_ids)) {
            // TODO not yet supported.
            if ($form_state) {
              $form_state->set('manual-merge-not-possible', TRUE);
            }
          }
        }
      }
    }
  }

  /**
   * Reorders the widget state for the reordered item list.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field_item_list
   *   The reordered field item list.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function reorderWidgetState(FieldItemListInterface $field_item_list, FormStateInterface $form_state) {
    $field_name = $field_item_list->getName();
    $widget_state = ParagraphsWidget::getWidgetState([], $field_name, $form_state);
    $old_widget_state_paragraphs = $widget_state['paragraphs'];
    $widget_state['paragraphs'] = [];

    foreach ($field_item_list as $new_delta => $item) {
      $found = FALSE;
      foreach ($old_widget_state_paragraphs as $old_delta => $old_delta_state) {
        if ($item->target_id == $old_delta_state['entity']->id()) {
          $widget_state['paragraphs'][$new_delta] = $old_delta_state;
          $widget_state['paragraphs'][$new_delta]['entity'] = $item->entity;
          $found = TRUE;
        }
      }
      // New entity.
      if (!$found) {
        $widget_state['paragraphs'][$new_delta]['entity'] = $item->entity;
      }
    }
    $widget_state['items_count'] = count($widget_state['paragraphs']);
    ParagraphsWidget::setWidgetState([], $field_name, $form_state, $widget_state);
  }

}

<?php

namespace Drupal\conflict_paragraphs\Plugin\Conflict\FieldComparator;

use Drupal\conflict\ConflictTypes;
use Drupal\conflict\Entity\EntityConflictHandlerInterface;
use Drupal\conflict\Plugin\Conflict\FieldComparator\FieldComparatorDefault;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field comparator plugin implementation for paragraph reference fields.
 *
 * @FieldComparator(
 *   id = "conflict_field_comparator_paragraph_ref",
 *   entity_type_id = "*",
 *   bundle = "*",
 *   field_type = "entity_reference_revisions",
 *   field_name = "*",
 * )
 */
class FieldComparatorParagraphReference extends FieldComparatorDefault implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a FieldComparatorParagraphReference object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct([], $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConflictType(FieldItemListInterface $local, FieldItemListInterface $server, FieldItemListInterface $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name) {
    /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $local */
    /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $server */
    /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $original */

    // If the default conflict detection is detecting only a remote change then
    // we need to compare exactly the referenced entities in order to detect
    // whether there has been only remote change or also a local conflicting
    // change.
    $conflict_type = parent::getConflictType($local, $server, $original, $langcode, $entity_type_id, $bundle, $field_type, $field_name);
    if (ConflictTypes::CONFLICT_TYPE_REMOTE === $conflict_type) {
      $local_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $local->getValue());
      $server_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $server->getValue());
      $original_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $original->getValue());

      $local_paragraph_ids_sorted = $local_paragraph_ids_unsorted;
      $server_paragraph_ids_sorted = $server_paragraph_ids_unsorted;
      $original_paragraph_ids_sorted = $original_paragraph_ids_unsorted;
      sort($local_paragraph_ids_sorted);
      sort($server_paragraph_ids_sorted);
      sort($original_paragraph_ids_sorted);

      // No entities have been added or deleted in the server version.
      if ($server_paragraph_ids_sorted == $original_paragraph_ids_sorted) {
        $conflict_type = $this->getConflictTypeForCommonParagraphs($local, $server, $original) ?: $conflict_type;
      }
      // Entities have been added or deleted in the server version.
      else {
        $new_paragraph_ids = array_diff($server_paragraph_ids_sorted, $original_paragraph_ids_sorted);
        $removed_paragraph_ids = array_diff($original_paragraph_ids_sorted, $server_paragraph_ids_sorted);

        // Only new paragraphs added.
        if ($new_paragraph_ids && empty($removed_paragraph_ids)) {
          // TODO check whether common paragraphs have been changed in remote
          // and local. If not classify as REMOTE_ONLY changes - because of the
          // added paragraphs.
          $conflict_type = $this->getConflictTypeForCommonParagraphs($local, $server, $original) ?: $conflict_type;
        }
        // Only existing paragraphs removed.
        elseif ($removed_paragraph_ids && empty($new_paragraph_ids)) {
          // TODO not yet supported.
          // Check that paragraphs removed from server haven't changed in local
          // version compared to original and that common paragraphs haven't
          // changed as well. If both conditions are fulfilled classify as
          // REMOTE only change. If however one of both conditions is not
          // fulfilled classify as Remote-Local conflict.
          $conflict_type = $this->getConflictTypeForCommonParagraphs($local, $server, $original) ?: $conflict_type;
        }
        // Both new paragraphs added and existing paragraphs deleted.
        else {
          // TODO not yet supported.
          // Remote only change if:
          //   1. Check that paragraphs removed from server haven't changed in
          //      local version compared to original.
          //   2. Common paragraphs haven't changed as well.
          // Remote and Local conlict: if one of both conditions is not
          // fulfilled.
          $conflict_type = $this->getConflictTypeForCommonParagraphs($local, $server, $original) ?: $conflict_type;
        }
      }
    }

    return $conflict_type;
  }

  /**
   * Returns the conflict type for common paragraphs.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $local
   *   The local field item list to compare.
   * @param \Drupal\Core\Field\FieldItemListInterface $server
   *   The server field item list to compare.
   * @param \Drupal\Core\Field\FieldItemListInterface $original
   *   The original field item list, from which local and the server emerged.
   *
   * @return string|null
   *   The conflict type or NULL if none.
   */
  protected function getConflictTypeForCommonParagraphs(FieldItemListInterface $local, FieldItemListInterface $server, FieldItemListInterface $original) {
    $conflict_type = NULL;

    /** @var \Drupal\conflict\Entity\EntityConflictHandlerInterface $entity_conflict_resolution_handler */
    $entity_conflict_resolution_handler = $this->entityTypeManager->getHandler('paragraph', 'conflict.resolution_handler');

    foreach ($local as $local_item) {
      $local_item_target_id = $local_item->target_id;
      /** @var \Drupal\paragraphs\ParagraphInterface $local_item_entity */
      $local_item_entity = $local_item->entity;
      /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $local_item_entity_form_display */
      $local_item_entity_form_display = $local_item_entity->{EntityConflictHandlerInterface::CONFLICT_FORM_DISPLAY};

      $server_item_entity = NULL;
      foreach ($server as $server_item) {
        if ($local_item_target_id == $server_item->target_id) {
          /** @var \Drupal\paragraphs\ParagraphInterface $server_item_entity */
          $server_item_entity = $server_item->entity;
          break;
        }
      }

      foreach ($original as $original_item) {
        if ($local_item_target_id == $original_item->target_id) {
          /** @var \Drupal\paragraphs\ParagraphInterface $original_item_entity */
          $original_item_entity = $original_item->entity;
          break;
        }
      }

      if ($server_item_entity) {
        foreach (array_keys($local_item_entity_form_display->getComponents()) as $paragraph_field_name) {
          $local_paragraph_field = $local_item_entity->get($paragraph_field_name);
          $server_paragraph_field = $server_item_entity->get($paragraph_field_name);

          if (!$server_paragraph_field->equals($local_paragraph_field)) {
            $original_paragraph_field = $original_item_entity->get($paragraph_field_name);
            if (!$server_paragraph_field->equals($original_paragraph_field)) {
              if (!$local_paragraph_field->equals($original_paragraph_field)) {
                $conflict_type = ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE;

                // TODO find a better place to append the server entity. This
                // is needed for preparation of the conflict resolution.
                // @see \Drupal\conflict\Entity\ContentEntityConflictHandler::prepareConflictResolution()
                // Maybe the Merge Strategy is a better suited place?
                $entity_conflict_resolution_handler->prepareConflictResolution($local_item_entity, $server_item_entity);
                break;
              }
            }
          }
        }
      }
      // The entity has been removed in the remote version.
      else {
        foreach (array_keys($local_item_entity_form_display->getComponents()) as $paragraph_field_name) {
          $local_paragraph_field = $local_item_entity->get($paragraph_field_name);
          $original_paragraph_field = $original_item_entity->get($paragraph_field_name);

          if (!$local_paragraph_field->equals($original_paragraph_field)) {
            $conflict_type = ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE;

            // TODO find a better place to append the server entity. This
            // is needed for preparation of the conflict resolution.
            // @see \Drupal\conflict\Entity\ContentEntityConflictHandler::prepareConflictResolution()
            // Maybe the Merge Strategy is a better suited place?
            $entity_conflict_resolution_handler->prepareConflictResolution($local_item_entity, $server_item_entity);
            break;
          }
        }
      }
    }


    $local_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $local->getValue());
    $original_paragraph_ids_unsorted = array_map(function ($value) {return $value['target_id'];}, $original->getValue());
    $removed_locally_paragraph_ids = array_diff($original_paragraph_ids_unsorted, $local_paragraph_ids_unsorted);

    foreach($removed_locally_paragraph_ids as $paragraph_id) {
      $original_item_entity = NULL;
      foreach ($original as $original_item) {
        if ($paragraph_id == $original_item->target_id) {
          /** @var \Drupal\paragraphs\ParagraphInterface $original_item_entity */
          $original_item_entity = $original_item->entity;
          break;
        }
      }

      $server_item_entity = NULL;
      foreach ($server as $server_item) {
        if ($paragraph_id == $server_item->target_id) {
          /** @var \Drupal\paragraphs\ParagraphInterface $original_item_entity */
          $server_item_entity = $server_item->entity;
          break;
        }
      }

      /** @var \Drupal\Core\Field\WidgetInterface $widget */
      $widget = $local->conflictWidget;
      if ($widget) {
        $removed_entity_form_display = EntityFormDisplay::collectRenderDisplay($original_item_entity, $widget->getSetting('form_display_mode'));
      }
      else {
        // TODO
      }

      foreach (array_keys($removed_entity_form_display->getComponents()) as $paragraph_field_name) {
        $server_paragraph_field = $server_item_entity->get($paragraph_field_name);
        $original_paragraph_field = $original_item_entity->get($paragraph_field_name);

        if (!$server_paragraph_field->equals($original_paragraph_field)) {
          $conflicting_removed_paragraph_ids = $local->conflictingRemovedParagraphIds ?? [];
          $conflicting_removed_paragraph_ids[] = $paragraph_id;
          $local->conflictingRemovedParagraphIds = $conflicting_removed_paragraph_ids;
          $conflict_type = ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE;
          break;
        }
      }
    }

    return $conflict_type;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return ParagraphsWidget::isApplicable($field_definition);
  }

}

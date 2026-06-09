<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_automators\AutomatorsToolInterface;

/**
 * Defines the automators tool entity type.
 *
 * @ConfigEntityType(
 *   id = "automators_tool",
 *   label = @Translation("Automators Tool"),
 *   label_collection = @Translation("Automators Tools"),
 *   label_singular = @Translation("automators tool"),
 *   label_plural = @Translation("automators tools"),
 *   label_count = @PluralTranslation(
 *     singular = "@count automators tool",
 *     plural = "@count automators tools",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_automators\AutomatorsToolListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_automators\Form\AutomatorsToolForm",
 *       "edit" = "Drupal\ai_automators\Form\AutomatorsToolForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "automators_tool",
 *   admin_permission = "administer automators_tool",
 *   links = {
 *     "collection" = "/admin/structure/automators-tool",
 *     "add-form" = "/admin/structure/automators-tool/add",
 *     "edit-form" = "/admin/structure/automators-tool/{automators_tool}",
 *     "delete-form" = "/admin/structure/automators-tool/{automators_tool}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "workflow",
 *     "field_connections",
 *   },
 * )
 */
final class AutomatorsTool extends ConfigEntityBase implements AutomatorsToolInterface {

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * The workflow.
   */
  protected string $workflow;

  /**
   * The field connections.
   */
  protected array $field_connections;

}

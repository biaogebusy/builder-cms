<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Automator Chain type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "automator_chain_type",
 *   label = @Translation("Automator Chain type"),
 *   label_collection = @Translation("Automator Chain types"),
 *   label_singular = @Translation("automator chain type"),
 *   label_plural = @Translation("automator chains types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count automator chains type",
 *     plural = "@count automator chains types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\ai_automators\Form\AutomatorChainTypeForm",
 *       "edit" = "Drupal\ai_automators\Form\AutomatorChainTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\ai_automators\AutomatorChainTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\ai_automators\Routing\AutomatorChainTypeHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer automator_chain types",
 *   bundle_of = "automator_chain",
 *   config_prefix = "automator_chain_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/ai/automator_chain_types/add",
 *     "edit-form" = "/admin/config/ai/automator_chain_types/manage/{automator_chain_type}",
 *     "delete-form" = "/admin/config/ai/automator_chain_types/manage/{automator_chain_type}/delete",
 *     "collection" = "/admin/config/ai/automator_chain_types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   },
 * )
 */
final class AutomatorChainType extends ConfigEntityBundleBase {

  /**
   * The machine name of this automator chain type.
   */
  protected string $id;

  /**
   * The human-readable name of the automator chain type.
   */
  protected string $label;

}

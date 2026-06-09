<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\ai_automators\AutomatorChainInterface;

/**
 * Defines the automator chain entity class.
 *
 * @ContentEntityType(
 *   id = "automator_chain",
 *   label = @Translation("Automator Chain"),
 *   label_collection = @Translation("Automator Chains"),
 *   label_singular = @Translation("Automator Chain"),
 *   label_plural = @Translation("Automator Chains"),
 *   label_count = @PluralTranslation(
 *     singular = "@count automator chains",
 *     plural = "@count automator chains",
 *   ),
 *   bundle_label = @Translation("Automator Chain type"),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_automators\AutomatorChainListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\ai_automators\Form\AutomatorChainForm",
 *       "edit" = "Drupal\ai_automators\Form\AutomatorChainForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ai_automators\Routing\AutomatorChainHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "automator_chain",
 *   admin_permission = "administer automator_chain types",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "bundle",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/automator-chain",
 *     "add-form" = "/admin/content/automator-chain/add/{automator_chain_type}",
 *     "add-page" = "/admin/content/automator-chain/add",
 *     "canonical" = "/admin/content/automator-chain/{automator_chain}",
 *     "edit-form" = "/admin/content/automator-chain/{automator_chain}",
 *     "delete-form" = "/admin/content/automator-chain/{automator_chain}/delete",
 *     "delete-multiple-form" = "/admin/content/automator-chain/delete-multiple",
 *   },
 *   bundle_entity_type = "automator_chain_type",
 *   field_ui_base_route = "entity.automator_chain_type.edit_form",
 * )
 */
final class AutomatorChain extends ContentEntityBase implements AutomatorChainInterface {

}

<?php

namespace Drupal\ai_logging\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the AI Log Type entity.
 *
 * @ConfigEntityType(
 *   id = "ai_log_type",
 *   label = @Translation("AI Log Type"),
 *   bundle_of = "ai_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_prefix = "ai_log_type",
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\ai_logging\Form\AiLogTypeForm",
 *       "add" = "Drupal\ai_logging\Form\AiLogTypeForm",
 *       "edit" = "Drupal\ai_logging\Form\AiLogTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\ai_logging\AiLogTypeListBuilder",
 *     "view_builder" = "Drupal\ai_logging\ViewBuilder\LogTypeViewBuilder",
 *     "field_ui" = "Drupal\field_ui\Entity\EntityFormDisplay",
 *   },
 *   admin_permission = "administer ai log",
 *   links = {
 *     "canonical" = "/admin/config/ai/logging/types/{ai_log_type}",
 *     "add-form" = "/admin/config/ai/logging/types/add",
 *     "edit-form" = "/admin/config/ai/logging/types/{ai_log_type}/edit",
 *     "delete-form" = "/admin/config/ai/logging/types/{ai_log_type}/delete",
 *     "collection" = "/admin/config/ai/logging/types",
 *   }
 * )
 */
class AiLogType extends ConfigEntityBundleBase {
}

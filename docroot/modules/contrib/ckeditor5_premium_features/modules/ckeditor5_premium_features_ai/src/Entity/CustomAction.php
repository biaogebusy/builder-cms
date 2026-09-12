<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the AI Custom Action config entity.
 *
 * @ConfigEntityType(
 *   id = "ckeditor5_ai_custom_action",
 *   label = @Translation("Custom Actions"),
 *   label_collection = @Translation("Custom Actions"),
 *   label_singular = @Translation("Custom Action"),
 *   label_plural = @Translation("Custom Actions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Custom Action",
 *     plural = "@count Custom Actions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor5_premium_features_ai\CustomActionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ckeditor5_premium_features_ai\Form\CustomActionForm",
 *       "edit" = "Drupal\ckeditor5_premium_features_ai\Form\CustomActionForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "ckeditor5_ai_custom_action",
 *   admin_permission = "administer ckeditor ai",
 *   links = {
 *     "collection" = "/admin/structure/ckeditor-ai-custom-actions",
 *     "add-form" = "/admin/structure/ckeditor-ai-custom-actions/add",
 *     "edit-form" = "/admin/structure/ckeditor-ai-custom-actions/{ckeditor5_ai_custom_action}",
 *     "delete-form" = "/admin/structure/ckeditor-ai-custom-actions/{ckeditor5_ai_custom_action}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "prompt",
 *     "type",
 *     "model",
 *     "textFormats",
 *   }
 * )
 */
final class CustomAction extends ConfigEntityBase {

  /**
   * The machine name ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The human readable label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The prompt text.
   *
   * @var string|null
   */
  protected ?string $prompt = NULL;

  /**
   * The type of command: ACTION | CHAT.
   *
   * @var string|null
   */
  protected ?string $type = NULL;

  /**
   * The model name.
   *
   * @var string|null
   */
  protected ?string $model = NULL;

  /**
   * Allowed text formats (limited to CKEditor 5-enabled formats).
   *
   * @var array|null
   */
  protected ?array $textFormats = NULL;
}

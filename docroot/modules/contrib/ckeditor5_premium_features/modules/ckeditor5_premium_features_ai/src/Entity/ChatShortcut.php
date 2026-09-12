<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the AI Chat Shortcut config entity.
 *
 * @ConfigEntityType(
 *   id = "ckeditor5_ai_chat_shortcut",
 *   label = @Translation("Chat Shortcuts"),
 *   label_collection = @Translation("Chat Shortcuts"),
 *   label_singular = @Translation("Chat Shortcut"),
 *   label_plural = @Translation("Chat Shortcuts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Chat Shortcut",
 *     plural = "@count Chat Shortcuts",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor5_premium_features_ai\ChatShortcutListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ckeditor5_premium_features_ai\Form\ChatShortcutForm",
 *       "edit" = "Drupal\ckeditor5_premium_features_ai\Form\ChatShortcutForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "ckeditor5_ai_chat_shortcut",
 *   admin_permission = "administer ckeditor ai",
 *   links = {
 *     "collection" = "/admin/structure/ckeditor-ai-chat-shortcuts",
 *     "add-form" = "/admin/structure/ckeditor-ai-chat-shortcuts/add",
 *     "edit-form" = "/admin/structure/ckeditor-ai-chat-shortcuts/{ckeditor5_ai_chat_shortcut}",
 *     "delete-form" = "/admin/structure/ckeditor-ai-chat-shortcuts/{ckeditor5_ai_chat_shortcut}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "type",
 *     "prompt",
 *     "useReasoning",
 *     "useWebSearch",
 *     "commandId",
 *     "textFormats",
 *   }
 * )
 */
final class ChatShortcut extends ConfigEntityBase {

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
   * The type of shortcut: chat | review | translate.
   *
   * @var string|null
   */
  protected ?string $type = NULL;

  /**
   * The prompt text.
   *
   * @var string|null
   */
  protected ?string $prompt = NULL;

  /**
   * Use reasoning.
   *
   * @var bool|null
   */
  protected ?bool $useReasoning = NULL;

  /**
   * Use web search.
   *
   * @var bool|null
   */
  protected ?bool $useWebSearch = NULL;

  /**
   * Command ID for review type.
   *
   * @var string|null
   */
  protected ?string $commandId = NULL;

  /**
   * Allowed text formats (limited to CKEditor 5-enabled formats).
   *
   * @var array|null
   */
  protected ?array $textFormats = NULL;

}

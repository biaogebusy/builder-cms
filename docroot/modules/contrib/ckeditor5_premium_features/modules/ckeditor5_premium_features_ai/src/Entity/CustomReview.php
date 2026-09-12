<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the AI Custom Review config entity.
 *
 * @ConfigEntityType(
 *   id = "ckeditor5_ai_custom_review",
 *   label = @Translation("Custom Reviews"),
 *   label_collection = @Translation("Custom Reviews"),
 *   label_singular = @Translation("Custom Review"),
 *   label_plural = @Translation("Custom Reviews"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Custom Review",
 *     plural = "@count Custom Reviews",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor5_premium_features_ai\CustomReviewListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ckeditor5_premium_features_ai\Form\CustomReviewForm",
 *       "edit" = "Drupal\ckeditor5_premium_features_ai\Form\CustomReviewForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "ckeditor5_ai_custom_review",
 *   admin_permission = "administer ckeditor ai",
 *   links = {
 *     "collection" = "/admin/structure/ckeditor-ai-custom-reviews",
 *     "add-form" = "/admin/structure/ckeditor-ai-custom-reviews/add",
 *     "edit-form" = "/admin/structure/ckeditor-ai-custom-reviews/{ckeditor5_ai_custom_review}",
 *     "delete-form" = "/admin/structure/ckeditor-ai-custom-reviews/{ckeditor5_ai_custom_review}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "prompt",
 *     "model",
 *     "textFormats",
 *   }
 * )
 */
final class CustomReview extends ConfigEntityBase {

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
   * The description.
   *
   * @var string|null
   */
  protected ?string $description = NULL;

  /**
   * The prompt text.
   *
   * @var string|null
   */
  protected ?string $prompt = NULL;

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

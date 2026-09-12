<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Merge Fields Group entity.
 *
 * @ConfigEntityType(
 *   id = "merge_fields_group",
 *   label = @Translation("Merge Fields Group"),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor5_premium_features_merge_fields\MergeFieldsGroupListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ckeditor5_premium_features_merge_fields\Form\MergeFieldsGroupForm",
 *       "edit" = "Drupal\ckeditor5_premium_features_merge_fields\Form\MergeFieldsGroupForm",
 *       "delete" = "Drupal\ckeditor5_premium_features_merge_fields\Form\MergeFieldsGroupDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "merge_fields_group",
 *   admin_permission = "administer merge fields",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/ckeditor5-premium-features/merge-fields/group/add",
 *     "edit-form" = "/admin/config/ckeditor5-premium-features/merge-fields/group/{merge_fields_group}/edit",
 *     "delete-form" = "/admin/config/ckeditor5-premium-features/merge-fields/group/{merge_fields_group}/delete",
 *     "collection" = "/admin/config/ckeditor5-premium-features/merge-fields"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "weight"
 *   }
 * )
 */
class MergeFieldsGroup extends ConfigEntityBase implements MergeFieldsGroupInterface {

  /**
   * The Merge Fields Group ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Merge Fields Group label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Merge Fields Group weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight): MergeFieldsGroupInterface {
    $this->weight = $weight;
    return $this;
  }

}

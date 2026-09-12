<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Merge Fields Item entity.
 *
 * @ConfigEntityType(
 *   id = "merge_fields_item",
 *   label = @Translation("Merge Fields Item"),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor5_premium_features_merge_fields\MergeFieldsItemListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ckeditor5_premium_features_merge_fields\Form\MergeFieldsItemForm",
 *       "edit" = "Drupal\ckeditor5_premium_features_merge_fields\Form\MergeFieldsItemForm",
 *       "delete" = "Drupal\ckeditor5_premium_features_merge_fields\Form\MergeFieldsItemDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "merge_fields_item",
 *   admin_permission = "administer merge fields",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/ckeditor5-premium-features/merge-fields/item/add",
 *     "edit-form" = "/admin/config/ckeditor5-premium-features/merge-fields/item/{merge_fields_item}/edit",
 *     "delete-form" = "/admin/config/ckeditor5-premium-features/merge-fields/item/{merge_fields_item}/delete",
 *     "collection" = "/admin/config/ckeditor5-premium-features/merge-fields"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "token",
 *     "group",
 *     "weight",
 *     "type",
 *     "default_value",
 *     "height",
 *     "width"
 *   }
 * )
 */
class MergeFieldsItem extends ConfigEntityBase implements MergeFieldsItemInterface {

  /**
   * The Merge Fields Item ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Merge Fields Item label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Merge Fields Item token.
   *
   * @var string
   */
  protected $token;

  /**
   * The Merge Fields Group ID.
   *
   * @var string
   */
  protected $group;

  /**
   * The Merge Fields Item weight.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * The Merge Fields Item type.
   *
   * @var string
   */
  protected $type = 'text';

  /**
   * The Merge Fields Item default value.
   *
   * @var string
   */
  protected $default_value = '';

  /**
   * The Merge Fields Item height.
   *
   * @var int|null
   */
  protected $height = NULL;

  /**
   * The Merge Fields Item width.
   *
   * @var int|null
   */
  protected $width = NULL;

  /**
   * {@inheritdoc}
   */
  public function getToken(): string {
    return $this->token ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setToken(string $token): MergeFieldsItemInterface {
    $this->token = $token;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): string {
    return $this->group ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setGroup(string $group): MergeFieldsItemInterface {
    $this->group = $group;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(int $weight): MergeFieldsItemInterface {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->type ?? 'text';
  }

  /**
   * {@inheritdoc}
   */
  public function setType(string $type): MergeFieldsItemInterface {
    $this->type = $type;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValue(): string {
    return $this->default_value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultValue(string $default_value): MergeFieldsItemInterface {
    $this->default_value = $default_value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight(): ?int {
    return $this->height;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeight(?int $height): MergeFieldsItemInterface {
    $this->height = $height;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth(): ?int {
    return $this->width;
  }

  /**
   * {@inheritdoc}
   */
  public function setWidth(?int $width): MergeFieldsItemInterface {
    $this->width = $width;
    return $this;
  }
}

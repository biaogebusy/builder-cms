<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_plugin_pack_templates\Entity;

use Drupal\ckeditor5_plugin_pack_templates\CKEditor5TemplateInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the ckeditor template entity type.
 *
 * @ConfigEntityType(
 *   id = "ckeditor5_template",
 *   label = @Translation("CKEditor5 Template"),
 *   label_collection = @Translation("CKEditor5 Templates"),
 *   label_singular = @Translation("CKEditor5 Template"),
 *   label_plural = @Translation("CKEditor5 Templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count ckeditor5 template",
 *     plural = "@count ckeditor5 templates",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor5_plugin_pack_templates\CKEditor5TemplateListBuilder",
 *     "form" = {
 *       "default" = "Drupal\ckeditor5_plugin_pack_templates\Form\ContentTemplates\CKEditor5TemplateEntityForm",
 *       "add" = "Drupal\ckeditor5_plugin_pack_templates\Form\ContentTemplates\CKEditor5TemplateEntityForm",
 *       "edit" = "Drupal\ckeditor5_plugin_pack_templates\Form\ContentTemplates\CKEditor5TemplateEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "ckeditor5_template",
 *   admin_permission = "administer ckeditor5 templates",
 *   links = {
 *     "collection" = "/admin/config/ckeditor5-templates/content-templates",
 *     "add-form" = "/admin/config/ckeditor5-templates/content-templates/add",
 *     "edit-form" = "/admin/config/ckeditor5-templates/content-templates/{ckeditor5_template}",
 *     "delete-form" = "/admin/config/ckeditor5-templates/{ckeditor5_template}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "weight" = "weight"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "status",
 *     "data",
 *     "icon",
 *     "textFormats",
 *     "weight"
 *   }
 * )
 */
class CKEditor5Template extends ConfigEntityBase implements CKEditor5TemplateInterface {

  /**
   * The template ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The template label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The template status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The template description.
   *
   * @var string
   */
  protected ?string $description;

  /**
   * The template icon.
   *
   * @var string
   */
  protected ?string $icon;

  /**
   * The template HTML code.
   *
   * @var string
   */
  protected ?string $data;

  /**
   * The template allowed text formats.
   *
   * @var array
   */
  protected ?array $textFormats;

  /**
   * The template weight.
   *
   * @var int
   */
  protected ?int $weight;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if (!isset($this->weight)) {
      $templates = $storage->loadMultiple();
      if (empty($templates)) {
        $this->weight = 0;
      }
      else {
        $weights = array_column($templates, 'weight');
        $this->weight = min($weights) - 1;
      }
    }
  }

  /**
   * Returns an array of definitions.
   */
  public function getDefinition(): array {
    return [
      'title' => $this->label(),
      'description' => $this->description,
      'data' => $this->data,
      'icon' => $this->icon,
    ];
  }

}

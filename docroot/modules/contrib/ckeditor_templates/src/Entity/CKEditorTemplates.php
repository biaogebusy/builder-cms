<?php

namespace Drupal\ckeditor_templates\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ckeditor_templates\CKEditorTemplatesInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the ckeditor template entity type.
 *
 * @ConfigEntityType(
 *   id = "ckeditor_templates",
 *   label = @Translation("CKEditor Template"),
 *   label_collection = @Translation("CKEditor Templates"),
 *   label_singular = @Translation("CKEditor Template"),
 *   label_plural = @Translation("CKEditor Templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count template",
 *     plural = "@count templates",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor_templates\CKEditorTemplatesListBuilder",
 *     "form" = {
 *       "default" = "Drupal\ckeditor_templates\Form\CKEditorTemplatesEntityForm",
 *       "add" = "Drupal\ckeditor_templates\Form\CKEditorTemplatesEntityForm",
 *       "edit" = "Drupal\ckeditor_templates\Form\CKEditorTemplatesEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "ckeditor_templates",
 *   admin_permission = "administer ckeditor templates",
 *   links = {
 *     "collection" = "/admin/config/content/ckeditor-templates",
 *     "add-form" = "/admin/config/content/ckeditor-templates/add",
 *     "edit-form" = "/admin/config/content/ckeditor-templates/{ckeditor_templates}",
 *     "delete-form" = "/admin/config/content/ckeditor-templates/{ckeditor_templates}/delete"
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
 *     "status",
 *     "description",
 *     "thumb",
 *     "thumb_alternative",
 *     "code",
 *     "formats",
 *     "weight"
 *   }
 * )
 */
class CKEditorTemplates extends ConfigEntityBase implements CKEditorTemplatesInterface {

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
  protected string $description;

  /**
   * The template thumb.
   *
   * @var string
   */
  protected array $thumb;

  /**
   * The template thumb_alternative.
   *
   * @var string
   */
  protected string $thumb_alternative;

  /**
   * The template HTML code.
   *
   * @var array
   */
  protected array $code;

  /**
   * The template allowed formats.
   *
   * @var array
   */
  protected array $formats;

  /**
   * The template weight.
   *
   * @var int
   */
  protected int $weight;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if (!isset($this->weight)) {
      $templates = $storage->loadMultiple();
      if (empty($templates)) {
        $this->weight = 0;
        return;
      }
      $weights = array_column($templates, 'weight');
      $this->weight = max($weights) + 1;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $return = parent::save();
    \Drupal::service('plugin.manager.ckeditor_template')->clearCachedDefinitions();
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    \Drupal::service('plugin.manager.ckeditor_template')->clearCachedDefinitions();
  }

}

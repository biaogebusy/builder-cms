<?php

namespace Drupal\layout_library\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\field_ui\FieldUI;
use Drupal\layout_builder\SectionListInterface;
use Drupal\layout_builder\SectionListTrait;
use Drupal\Core\Url;

/**
 * Defines a layout entity.
 *
 * @ConfigEntityType(
 *   id = "layout",
 *   label = @Translation("Layout"),
 *   label_collection = @Translation("Layout library"),
 *   handlers = {
 *     "storage" = "Drupal\layout_library\LayoutStorageHandler",
 *     "form" = {
 *       "default" = "Drupal\layout_library\Form\LayoutForm",
 *       "delete" = "Drupal\layout_library\Form\LayoutDeleteForm",
 *       "add" = "Drupal\layout_library\Form\LayoutAddForm",
 *       "layout_builder" = "Drupal\layout_library\Form\LayoutBuilderForm",
 *     },
 *     "list_builder" = "Drupal\layout_library\Entity\LayoutListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *   },
 *   admin_permission = "configure any layout",
 *   config_prefix = "layout",
 *   config_export = {
 *     "id",
 *     "label",
 *     "targetEntityType",
 *     "targetBundle",
 *     "layout",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "delete-form" = "/admin/structure/layouts/manage/{layout}/delete",
 *     "add-form" = "/admin/structure/layouts/add",
 *     "collection" = "/admin/structure/layouts",
 *   }
 * )
 */
class Layout extends ConfigEntityBase implements SectionListInterface {

  use SectionListTrait;
  /**
   * Unique ID for the config entity.
   *
   * @var string
   */
  protected $id;

  /**
   * Entity type of this layout.
   *
   * @var string
   */
  protected $targetEntityType;

  /**
   * Valid bundles for this layout.
   *
   * @var string
   */
  protected $targetBundle;

  /**
   * Layout label.
   *
   * @var string
   */
  protected $label;

  /**
   * Layout.
   *
   * @var array
   */
  protected $layout = [];

  /**
   * Gets value of targetEntityType.
   *
   * @return string
   *   Value of targetEntityType
   */
  public function getTargetEntityType() {
    return $this->targetEntityType;
  }

  /**
   * Gets value of targetBundle.
   *
   * @return string
   *   Value of targetBundle
   */
  public function getTargetBundle() {
    return $this->targetBundle;
  }

  /**
   * Gets value of layout.
   *
   * @return array
   *   Value of layout
   */
  public function getLayout() {
    return $this->layout;
  }

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    return $this->layout;
  }

  /**
   * Stores the information for all sections.
   *
   * Implementations of this method are expected to call array_values() to rekey
   * the list of sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of section objects.
   *
   * @return $this
   */
  protected function setSections(array $sections) {
    $this->layout = array_values($sections);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function toUrl($rel = 'layout-builder', array $options = []) {
    if ($rel === 'layout-builder') {
      $options += [
        'language' => NULL,
        'entity_type' => 'layout',
        'entity' => $this,
      ];
      $parameters = FieldUI::getRouteBundleParameter(\Drupal::entityTypeManager()->getDefinition($this->getTargetEntityType()), $this->getTargetBundle());
      $parameters['layout'] = $this->id();
      $uri = new Url("layout_builder.layout_library.{$this->getTargetEntityType()}.view", $parameters);
      $uri->setOptions($options);
      return $uri;
    }
    return parent::toUrl($rel, $options);
  }

}

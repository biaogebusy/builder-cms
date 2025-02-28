<?php
namespace Drupal\entity_theme_engine\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Entity Widget entity.
 *
 * @ConfigEntityType(
 * id = "entity_widget",
 * label = @Translation("Entity Widget"),
 * handlers = {
 * "list_builder" = "Drupal\entity_theme_engine\EntityWidgetListBuilder",
 * "form" = {
 * "add" = "Drupal\entity_theme_engine\Form\EntityWidgetForm",
 * "edit" = "Drupal\entity_theme_engine\Form\EntityWidgetForm",
 * "delete" = "Drupal\entity_theme_engine\Form\EntityWidgetDeleteForm"
 * },
 * "route_provider" = {
 * "html" = "Drupal\entity_theme_engine\EntityWidgetHtmlRouteProvider",
 * },
 * },
 * config_prefix = "entity_widget",
 * admin_permission = "administer entity widget entities",
 * entity_keys = {
 * "id" = "id",
 * "label" = "label",
 * "uuid" = "uuid"
 * },
 * links = {
 * "canonical" = "/admin/structure/entity-widget/{entity_widget}",
 * "add-form" = "/admin/structure/entity-widget/add",
 * "edit-form" = "/admin/structure/entity-widget/{entity_widget}/edit",
 * "delete-form" = "/admin/structure/entity-widget/{entity_widget}/delete",
 * "collection" = "/admin/structure/entity-widget"
 * },
 * config_export = {
 *    "id",
 *    "label",
 *    "entity_type",
 *    "bundle",
 *    "display",
 *    "theme",
 *    "library",
 *    "category",
 *    "preview",
 *    "attach",
 *    "enabled",
 *    "template"
 * }
 * )
 */
class EntityWidget extends ConfigEntityBase {

  /**
   * The widget ID, used in url.
   *
   * @var string
   */
  protected $id;

  /**
   * The widget label.
   *
   * @var string
   */
  protected $label;

  /**
   * The widget entity type.
   *
   * @var string
   */
  protected $entity_type;

  /**
   * The widget entity bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The twig template.
   *
   * @var string
   */
  protected $template = "";

  /**
   * The display.
   *
   * @var string
   */
  protected $display = "default";

  /**
   * The theme.
   *
   * @var string
   */
  protected $theme = "";

  /**
   * The attached library.
   *
   * @var string
   */
  protected $library;

  /**
   * The widget cagetory.
   *
   * @var string
   */
  protected $category;

  /**
   * The widget preview image path.
   *
   * @var string
   */
  protected $preview;

  /**
   * Is attach context.
   *
   * @var bool
   */
  protected $attach = 0;

  /**
   * Is enabled.
   *
   * @var bool
   */
  protected $enabled = 1;

  /**
   *
   * @return string
   */
  public function getWidgetEntityType() {
    return $this->entity_type;
  }

  /**
   *
   * @return string
   */
  public function getTemplate() {
    return $this->template;
  }

  /**
   *
   * @return string
   */
  public function getLibrary() {
    return $this->library;
  }

  /**
   *
   * @return boolean
   */
  public function isEnabled() {
    return $this->enabled;
  }

  /**
   *
   * @param string $entity_type
   */
  public function setWidgetEntityType($entity_type) {
    $this->entity_type = $entity_type;
  }

  /**
   *
   * @param string $template
   */
  public function setTemplate($template) {
    $this->template = $template;
  }

  /**
   *
   * @param string $library
   */
  public function setLibrary($library) {
    $this->library = $library;
  }

  /**
   *
   * @param boolean $enabled
   */
  public function setEnabled($enabled) {
    $this->enabled = $enabled;
  }

  /**
   *
   * @return string
   */
  public function getDisplay() {
    return $this->display;
  }

  /**
   *
   * @param string $display
   */
  public function setDisplay($display) {
    $this->display = $display;
  }

  /**
   *
   * @return string
   */
  public function getBundle() {
    return $this->bundle;
  }

  /**
   *
   * @return string
   */
  public function getTheme() {
    return $this->theme;
  }

  /**
   *
   * @param string $bundle
   */
  public function setBundle($bundle) {
    $this->bundle = $bundle;
  }

  /**
   *
   * @param string $theme
   */
  public function setTheme($theme) {
    $this->theme = $theme;
  }

  /**
   * @return string
   */
  public function getCategory() {
      return $this->category;
  }

  /**
   * @return string
   */
  public function getPreview() {
      return $this->preview;
  }

  /**
   * @param string $category
   */
  public function setCategory($category) {
      $this->category = $category;
  }

  /**
   * @param string $preview
   */
  public function setPreview($preview) {
      $this->preview = $preview;
  }

  /**
   * @return boolean
   */
  public function isAttach() {
      return $this->attach;
  }

  /**
   * @param boolean $attach
   */
  public function setAttach($attach) {
      $this->attach = $attach;
  }


}

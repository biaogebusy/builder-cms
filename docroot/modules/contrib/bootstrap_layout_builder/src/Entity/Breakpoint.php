<?php

namespace Drupal\bootstrap_layout_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\bootstrap_layout_builder\BreakpointInterface;

/**
 * Defines the Breakpoint config entity.
 *
 * @ConfigEntityType(
 *   id = "blb_breakpoint",
 *   label = @Translation("Bootstrap Layout Builder Breakpoint"),
 *   label_collection = @Translation("Bootstrap Layout Builder Breakpoints"),
 *   label_plural = @Translation("Bootstrap Layout Builder Breakpoint"),
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "list_builder" = "Drupal\bootstrap_layout_builder\BreakpointListBuilder",
 *     "form" = {
 *       "add" = "Drupal\bootstrap_layout_builder\Form\BreakpointForm",
 *       "edit" = "Drupal\bootstrap_layout_builder\Form\BreakpointForm",
 *       "delete" = "Drupal\bootstrap_layout_builder\Form\BreakpointDeleteForm"
 *     }
 *   },
 *   config_prefix = "breakpoint",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label",
 *     "base_class" = "base_class",
 *     "status" = "status",
 *     "weight" = "weight",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/bootstrap-layout-builder/breakpoints/{blb_breakpoint}/edit",
 *     "delete-form" = "/admin/config/bootstrap-layout-builder/breakpoints/{blb_breakpoint}/delete",
 *     "collection" = "/admin/config/bootstrap-layout-builder/breakpoints",
 *     "add-form" = "/admin/config/bootstrap-layout-builder/breakpoints/add"
 *   }
 * )
 */
class Breakpoint extends ConfigEntityBase implements BreakpointInterface {

  /**
   * The Bootstrap layout Builder breakpoint ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Bootstrap layout Builder breakpoint label.
   *
   * @var string
   */
  protected $label;

  /**
   * The breakpoint base class.
   *
   * @var string
   */
  protected $base_class;

  /**
   * The breakpoint status.
   *
   * @var bool
   */
  protected $status;

  /**
   * Order of breakpoints on the config page & Layout Builder add/update forms.
   *
   * @var int
   */
  protected $weight = 0;

  /**
   * {@inheritdoc}
   */
  public function getBaseClass() {
    return $this->base_class;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutOptions($layout_id) {
    $options = [];
    $query = $this->entityTypeManager()->getStorage('blb_layout_option')->getQuery();
    $blb_options = $query->condition('layout_id', $layout_id)->accessCheck(TRUE)->sort('weight', 'ASC')->execute();
    foreach ($blb_options as $option_id) {
      $option = $this->entityTypeManager()->getStorage('blb_layout_option')->load($option_id);
      if (!in_array($this->id(), $option->getBreakpointsIds())) {
        continue;
      }
      $options[$option->getStructureId()] = $option->label();
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getClassByPosition($key, $strucutre_id) {
    if (!$strucutre_id) {
      return;
    }
    $strucutre = substr($strucutre_id, strlen('blb_col_'));
    $strucutre = explode('_', $strucutre);
    // Full width case.
    $sufix = '12';
    if (count($strucutre) > 1) {
      $sufix = (isset($strucutre[$key])) ? $strucutre[$key] : $strucutre[0];
    }
    $class = $this->getBaseClass() . '-' . $sufix;
    return $class;
  }

}

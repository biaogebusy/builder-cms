<?php

namespace Drupal\bootstrap_layout_builder\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\bootstrap_layout_builder\LayoutInterface;

/**
 * Defines the layout configuration entity.
 *
 * @ConfigEntityType(
 *   id = "blb_layout",
 *   label = @Translation("Bootstrap Layout Builder Layout"),
 *   label_collection = @Translation("Bootstrap Layout Builder Layouts"),
 *   label_plural = @Translation("Bootstrap Layout Builder Layout"),
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *     "list_builder" = "Drupal\bootstrap_layout_builder\LayoutListBuilder",
 *     "form" = {
 *       "options" = "Drupal\bootstrap_layout_builder\Form\LayoutOptionsForm",
 *       "edit" = "Drupal\bootstrap_layout_builder\Form\LayoutForm"
 *     }
 *   },
 *   config_prefix = "layout",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "number_of_columns" = "number_of_columns",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id" = "id",
 *     "label" = "label",
 *     "number_of_columns" = "number_of_columns",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "options-form" = "/admin/config/bootstrap-layout-builder/layouts/{blb_layout}/options",
 *     "edit-form" = "/admin/config/bootstrap-layout-builder/layouts/{blb_layout}",
 *     "collection" = "/admin/config/bootstrap-layout-builder/layouts",
 *   }
 * )
 */
class Layout extends ConfigEntityBase implements LayoutInterface {

  /**
   * The machine name for the configuration entity.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the configuration entity.
   *
   * @var string
   */
  protected $label;

  /**
   * The number of layout columns.
   *
   * @var string
   */
  protected $number_of_columns;

  /**
   * {@inheritdoc}
   */
  public function getNumberOfColumns() {
    return $this->number_of_columns;
  }

  /**
   * {@inheritdoc}
   */
  public function setNumberOfColumns($number_of_columns) {
    $this->number_of_columns = $number_of_columns;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLayoutOptions() {
    $options = $this->entityTypeManager()->getStorage('blb_layout_option')->loadByProperties(['layout_id' => $this->id()]);
    uasort($options, function ($a, $b) {
      $a_weight = $a->getWeight();
      $b_weight = $b->getWeight();
      if ($a_weight == $b_weight) {
        return strnatcasecmp($a->label(), $b->label());
      }
      return ($a_weight < $b_weight) ? -1 : 1;
    });
    return $options;
  }

}

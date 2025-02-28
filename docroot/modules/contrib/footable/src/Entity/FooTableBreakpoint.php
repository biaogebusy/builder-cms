<?php

namespace Drupal\footable\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the FooTable Breakpoint Config entity.
 *
 * @ConfigEntityType(
 *   id = "footable_breakpoint",
 *   label = @Translation("FooTable breakpoint"),
 *   label_collection = @Translation("FooTable breakpoints"),
 *   label_singular = @Translation("FooTable breakpoint"),
 *   label_plural = @Translation("FooTable breakpoints"),
 *   label_count = @PluralTranslation(
 *     singular = "@count FooTable breakpoint",
 *     plural = "@count FooTable breakpoints"
 *   ),
 *   admin_permission = "administer footable",
 *   handlers = {
 *     "list_builder" = "Drupal\footable\FooTableBreakpointListBuilder",
 *     "form" = {
 *       "add" = "Drupal\footable\Form\FooTableBreakpointForm",
 *       "edit" = "Drupal\footable\Form\FooTableBreakpointForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "name",
 *     "label",
 *     "breakpoint",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/user-interface/footable/breakpoint/add",
 *     "edit-form" = "/admin/config/user-interface/footable/breakpoint/{footable_breakpoint}/edit",
 *     "delete-form" = "/admin/config/user-interface/footable/breakpoint/{footable_breakpoint}/delete",
 *     "collection" = "/admin/config/user-interface/footable/breakpoint"
 *   }
 * )
 */
class FooTableBreakpoint extends ConfigEntityBase implements FooTableBreakpointInterface {

  /**
   * The name of the FooTable breakpoint.
   *
   * @var string
   */
  protected $name;

  /**
   * The breakpoint of the FooTable breakpoint.
   *
   * @var int
   */
  protected $breakpoint;

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getBreakpoint() {
    return $this->breakpoint;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadAll() {
    $breakpoints = self::loadMultiple();

    // Add 'All' breakpoint.
    $values = [
      'label' => 'All',
      'name' => 'all',
      'breakpoint' => 'all',
    ];
    $breakpoints['all'] = new self($values, 'footable_breakpoint');

    uasort($breakpoints, [__CLASS__, 'sort']);
    return $breakpoints;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    $breakpointA = $a->getBreakpoint();
    $breakpointB = $b->getBreakpoint();

    if ($breakpointA === $breakpointB) {
      return strnatcasecmp($a->label(), $b->label());
    }
    return ($breakpointA < $breakpointB) ? -1 : 1;
  }

}

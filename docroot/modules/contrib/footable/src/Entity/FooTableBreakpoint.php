<?php

namespace Drupal\footable\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\footable\FooTableBreakpointInterface;

/**
 * Defines the FooTable Breakpoint Config entity.
 *
 * @ConfigEntityType(
 *   id = "footable_breakpoint",
 *   label = @Translation("FooTable breakpoint"),
 *   admin_permission = "administer footable",
 *   handlers = {
 *     "list_builder" = "Drupal\footable\FooTableBreakpointListBuilder",
 *     "form" = {
 *       "add" = "Drupal\footable\Form\FooTableBreakpointEditForm",
 *       "edit" = "Drupal\footable\Form\FooTableBreakpointEditForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "name",
 *     "label" = "label"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/user-interface/footable/breakpoint/{footable_breakpoint}/edit",
 *     "delete-form" = "/admin/config/user-interface/footable/breakpoint/{footable_breakpoint}/delete",
 *     "collection" = "/admin/config/user-interface/footable/breakpoint"
 *   },
 *   config_export = {
 *     "name",
 *     "label",
 *     "breakpoint",
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
   * @var string|int
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
  public function setBreakpoint($breakpoint) {
    $this->breakpoint = $breakpoint;
    return $this;
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

    uasort($breakpoints, ['Drupal\footable\Entity\FooTableBreakpoint', 'sort']);
    return $breakpoints;
  }

  /**
   * {@inheritdoc}
   */
  public static function sort(ConfigEntityInterface $a, ConfigEntityInterface $b) {
    $a_breakpoint = $a->getBreakpoint();
    $b_breakpoint = $b->getBreakpoint();

    if ($a_breakpoint == $b_breakpoint) {
      return strnatcasecmp($a->label(), $b->label());
    }
    return ($a_breakpoint < $b_breakpoint) ? -1 : 1;
  }

}

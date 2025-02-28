<?php

namespace Drupal\bootstrap_layout_builder;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a layout option entity.
 */
interface LayoutOptionInterface extends ConfigEntityInterface {

  /**
   * Returns the layout_id.
   *
   * @return int
   *   The layout option layout_id.
   */
  public function getLayoutId();

  /**
   * Sets the layout_id.
   *
   * @param int $layout_id
   *   The layout option layout_id.
   *
   * @return $this
   *   The called layout option entity.
   */
  public function setLayoutId($layout_id);

  /**
   * Returns the structure.
   *
   * @return string
   *   The layout option structure.
   */
  public function getStructure();

  /**
   * Sets the structure.
   *
   * @param string $structure
   *   The layout option structure.
   *
   * @return $this
   *   The called layout option entity.
   */
  public function setStructure($structure);

  /**
   * Returns the structure id for select list options.
   *
   * @return string
   *   The layout option structure.
   */
  public function getStructureId();

  /**
   * Returns the weight among layout options with the same depth.
   *
   * @return int
   *   The layout option weight.
   */
  public function getWeight();

  /**
   * Sets the weight among layout options with the same depth.
   *
   * @param int $weight
   *   The layout option weight.
   *
   * @return $this
   *   The called layout option entity.
   */
  public function setWeight($weight);

  /**
   * Returns array of enabled breakpoints ids.
   *
   * @return array
   *   The breakpoints ids.
   */
  public function getBreakpointsIds();

  /**
   * Returns array of enabled breakpoints lablels.
   *
   * @return array
   *   The breakpoints labels.
   */
  public function getBreakpointsLabels();

  /**
   * Returns parent layout entity.
   *
   * @return object
   *   The parent layout entity.
   */
  public function getLayout();

  /**
   * Get layout entity by Id.
   *
   * @param string $layout_id
   *   The layout id.
   *
   * @return object
   *   The layout entity.
   */
  public function getLayoutById($layout_id);

  /**
   * Returns array of enabled default breakpoints ids.
   *
   * @return array
   *   The default breakpoints ids.
   */
  public function getDefaultBreakpointsIds();

  /**
   * Returns array of enabled default breakpoints labels.
   *
   * @return array
   *   The breakpoints labels.
   */
  public function getDefaultBreakpointsLabels();

}

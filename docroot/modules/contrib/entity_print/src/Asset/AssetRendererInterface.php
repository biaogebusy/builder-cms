<?php

namespace Drupal\entity_print\Asset;

/**
 * Interface for the print asset renderer.
 */
interface AssetRendererInterface {

  /**
   * Renders the CSS assets for the given entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of entities for whose assets we want to render.
   * @param bool $use_default_css
   *   TRUE to add in the global CSS otherwise FALSE.
   * @param bool $optimize_css
   *   TRUE to optimise the CSS otherwise FALSE.
   *
   * @return array
   *   The renderable array for the assets.
   */
  public function render(array $entities, $use_default_css = TRUE, $optimize_css = TRUE);

}

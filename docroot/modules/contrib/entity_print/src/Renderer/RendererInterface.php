<?php

namespace Drupal\entity_print\Renderer;

/**
 * The renderer interface.
 */
interface RendererInterface {

  /**
   * Gets the renderable for this entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entities we're rendering.
   *
   * @return array
   *   The renderable array for the entity.
   */
  public function render(array $entities);

  /**
   * Generates the HTML from the renderable array of entities.
   *
   * @param array $entities
   *   An array of entities we're rendering.
   * @param array $render
   *   A renderable array.
   * @param bool $use_default_css
   *   TRUE if we should inject our default CSS otherwise FALSE.
   * @param bool $optimize_css
   *   TRUE if we should compress the CSS otherwise FALSE.
   *
   * @return string
   *   The generated HTML.
   */
  public function generateHtml(array $entities, array $render, $use_default_css, $optimize_css);

  /**
   * Get the filename for the entity we're printing *without* the extension.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entities for which to generate the filename from.
   *
   * @return string
   *   The generate file name for this entity.
   */
  public function getFilename(array $entities);

}

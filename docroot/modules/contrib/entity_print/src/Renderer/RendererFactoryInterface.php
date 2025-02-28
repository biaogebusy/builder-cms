<?php

namespace Drupal\entity_print\Renderer;

/**
 * The renderer factory interface.
 */
interface RendererFactoryInterface {

  /**
   * Create a new entity renderer.
   *
   * @param mixed $item
   *   The item we require a renderer for.
   * @param string $context
   *   The type, currently supports entities but could change in the future.
   *
   * @return \Drupal\entity_print\Renderer\RendererInterface
   *   The constructed renderer.
   */
  public function create($item, $context = 'entity');

}

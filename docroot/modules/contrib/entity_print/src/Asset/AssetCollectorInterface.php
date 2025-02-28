<?php

namespace Drupal\entity_print\Asset;

/**
 * Collect all css assets for the entities being printed.
 */
interface AssetCollectorInterface {

  /**
   * Inject the relevant css for the template.
   *
   * You can specify CSS files to be included per entity type and bundle in your
   * themes css file. This code uses your current theme which is likely to be
   * the front end theme.
   *
   * Examples:
   *
   * entity_print:
   *   all: 'yourtheme/all-pdfs',
   *   commerce_order:
   *     all: 'yourtheme/orders'
   *   node:
   *     article: 'yourtheme/article-pdf'
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   The entity info from entity_get_info().
   *
   * @return array
   *   An array of stylesheets to be used for this template.
   */
  public function getCssLibraries(array $entities);

}

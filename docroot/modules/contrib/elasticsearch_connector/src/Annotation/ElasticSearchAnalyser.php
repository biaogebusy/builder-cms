<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines an annotation for ElasticSearch analyser plugins.
 *
 * @Annotation
 */
final class ElasticSearchAnalyser extends Plugin {

  /**
   * Plugin ID.
   */
  public string $id;

  /**
   * Plugin label.
   */
  public string|Translation $label;

}

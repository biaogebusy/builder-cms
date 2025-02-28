<?php

namespace Drupal\migrate_plus\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Plugin\migrate\process\DomProcessBase;

/**
 * Select strings from a DOMDocument object.
 *
 * Configuration:
 * - selector: An XPath selector that resolves to a string.
 * - limit: (optional) The maximum number of results to return.
 *
 * Usage:
 *
 * @code
 * process:
 *   bar:
 *     -
 *       plugin: dom
 *       method: import
 *       source: text_field
 *     -
 *       plugin: dom_select
 *       selector: //img/@src
 * @endcode
 *
 * This example will return an array of the src attributes of all <img> tags in
 * the source text. Add 'limit: 1' to return at most one result.
 *
 * @MigrateProcessPlugin(
 *   id = "dom_select"
 * )
 */
class DomSelect extends DomProcessBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $this->init($value, $destination_property);
    $values = [];
    foreach ($this->xpath->query($this->configuration['selector']) as $node) {
      if (isset($this->configuration['limit']) && count($values) >= $this->configuration['limit']) {
        break;
      }
      $values[] = $node->nodeValue;
    }

    return $values;
  }

}

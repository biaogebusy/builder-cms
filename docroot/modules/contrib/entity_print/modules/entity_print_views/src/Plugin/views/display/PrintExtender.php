<?php

namespace Drupal\entity_print_views\Plugin\views\display;

use Drupal\views\Plugin\views\display\DisplayPluginBase;

/**
 * Print display extender.
 *
 * @ViewsDisplay(
 *   id = "entity_print_views_print",
 *   title = @Translation("Print"),
 *   help = @Translation("Printable display for this view"),
 *   theme = "views_view",
 *   admin = @Translation("Print")
 * )
 */
class PrintExtender extends DisplayPluginBase {

}

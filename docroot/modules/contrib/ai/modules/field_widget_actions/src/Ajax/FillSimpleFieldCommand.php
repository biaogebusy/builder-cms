<?php

namespace Drupal\field_widget_actions\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to fill a simple field widget with data.
 */
class FillSimpleFieldCommand implements CommandInterface {

  /**
   * Constructs a command to fill in a simple fill.
   *
   * @param string $selector
   *   The CSS selector of the target element (input or textarea).
   * @param string $data
   *   The data to insert.
   */
  public function __construct(protected string $selector, protected string $data) {}

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'fieldWidgetActionsFillSimpleField',
      'selector' => $this->selector,
      'data' => $this->data,
    ];
  }

}

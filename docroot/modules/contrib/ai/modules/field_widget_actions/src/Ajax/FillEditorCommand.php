<?php

namespace Drupal\field_widget_actions\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command to fill a CK Editor instance with data.
 */
class FillEditorCommand implements CommandInterface {

  /**
   * Constructs a command to fill in a CK Editor instance.
   *
   * @param string $selector
   *   The CSS selector of the target CK Editor instance.
   * @param string $data
   *   The data to insert.
   */
  public function __construct(protected string $selector, protected string $data) {}

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'fieldWidgetActionsFillEditor',
      'selector' => $this->selector,
      'data' => $this->data,
    ];
  }

}

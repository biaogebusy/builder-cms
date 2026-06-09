<?php

namespace Drupal\ai_ckeditor\Command;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command for AI CKEditor request callbacks.
 */
class AiRequestCommand implements CommandInterface {

  /**
   * Constructs an AiRequestCommand object.
   *
   * @param string $prompt
   *   The prompt from the user.
   * @param string $editor_id
   *   The text format id.
   * @param string $plugin_id
   *   The AI CKEditor plugin id.
   * @param string $element_id
   *   The form element wrapper containing the textarea to write back to.
   */
  public function __construct(protected string $prompt, protected string $editor_id, protected string $plugin_id, protected string $element_id) {}

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'aiRequest',
      'prompt' => $this->prompt,
      'editor_id' => $this->editor_id,
      'plugin_id' => $this->plugin_id,
      'element_id' => $this->element_id,
    ];
  }

}

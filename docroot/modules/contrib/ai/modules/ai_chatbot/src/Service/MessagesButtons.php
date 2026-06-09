<?php

namespace Drupal\ai_chatbot\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class Buttons.
 */
class MessagesButtons {

  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(
    protected Renderer $renderer,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Helper function to calculate the assistant buttons.
   *
   * @param array $buttons
   *   The buttons array.
   * @param string $assistant_id
   *   The assistant id.
   * @param string $thread_id
   *   The thread id.
   * @param bool $replay
   *   If its a replay of the message.
   *
   * @return string
   *   The rendered assistant buttons.
   */
  public function getRenderedButtons(array $buttons, $assistant_id, $thread_id, $replay = FALSE): string {
    $info = [
      'assistant_id' => $assistant_id,
      'thread_id' => $thread_id,
    ];
    $this->moduleHandler->alter('deepchat_buttons', $buttons, $info);

    // Reorder the buttons based on the weight.
    usort($buttons, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    if (empty($buttons)) {
      return '';
    }

    $buttons_output = '<div class="chat-buttons">';
    foreach ($buttons as $button) {
      $classes = ['chat-button'];
      if (!empty($button['class'])) {
        $classes = array_merge($classes, $button['class']);
      }
      $image = [
        '#theme' => 'image',
        '#uri' => $button['svg'],
        '#alt' => $button['alt'] ?? '',
        '#title' => $button['title'] ?? '',
        '#attributes' => [
          'class' => $classes,
        ],
      ];
      if (isset($button['url'])) {
        $image = Link::fromTextAndUrl($image, $button['url'])->toRenderable() + [
          '#attributes' => ['class' => ['chat-button-link']],
        ];
      }
      $buttons_output .= Markup::create($this->renderer->renderInIsolation($image));
    }
    $buttons_output .= '</div>';
    return $buttons_output;
  }

}

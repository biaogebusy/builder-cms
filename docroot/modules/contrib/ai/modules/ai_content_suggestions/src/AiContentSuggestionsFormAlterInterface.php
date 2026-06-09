<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for the AI Content Suggestions Form Alter service.
 */
interface AiContentSuggestionsFormAlterInterface {

  /**
   * Helper to alter the Content Edit form to allow an LLM to interact with it.
   *
   * @param array $form
   *   The Drupal form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The Drupal Form State.
   */
  public function alter(array &$form, FormStateInterface $form_state): void;

  /**
   * Helper to identify the submitted plugin and allow it to update the form.
   *
   * @param array $form
   *   The Content Entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The appropriate section of the updated form.
   */
  public static function getPluginResponse(array $form, FormStateInterface $form_state): array;

}

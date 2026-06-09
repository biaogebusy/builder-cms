<?php

/**
 * @file
 * This file contains the post update function detailed below.
 */

/**
 * Move settings into new fields to handle future restructure on config.
 */
function ai_assistant_api_post_update_settings(): void {
  /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $assistant */
  foreach (\Drupal::entityTypeManager()->getStorage('ai_assistant')->loadMultiple() as $assistant) {

    // Consolidate existing instructions into a single text.
    $instructions = $assistant->get('system_role') . PHP_EOL . $assistant->get('assistant_message') . PHP_EOL . $assistant->get('preprompt_instructions');

    // Empty out the previous fields ready for deletion.
    $assistant->set('system_role', '');
    $assistant->set('assistant_message', '');
    $assistant->set('preprompt_instructions', '');

    // Set the new instructions.
    $assistant->set('instructions', $instructions);

    $path = \Drupal::service('module_handler')->getModule('ai_assistant_api')->getPath() . '/resources/';
    // Move the pre-action-=prompt to its new field, fill with txt file.
    $assistant->set('system_prompt', file_get_contents($path . 'system_prompt.txt'));

    // Clear out the old field.
    $assistant->set('pre_action_prompt', file_get_contents($path . 'pre_action_prompt.txt'));

    // Save the updated config.
    $assistant->save();
  }
}

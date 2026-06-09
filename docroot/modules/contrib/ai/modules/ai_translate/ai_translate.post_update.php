<?php

/**
 * @file
 * Post update file for AI translate module.
 */

/**
 * Update logic to transform prompts to an AI prompt config entity.
 */
function ai_translate_post_update_convert_ai_prompt(): void {
  // Get the AI prompt manager.
  /** @var \Drupal\ai\Service\AiPromptManager $prompt_manager */
  $promptManager = \Drupal::service('ai.prompt_manager');

  // Get the config to update.
  $configFactory = \Drupal::configFactory();
  $config = $configFactory->getEditable('ai_translate.settings');

  // Define the default prompt ID.
  $defaultPromptId = 'ai_translate__ai_translate_default';

  // Get the currently stored textual prompt.
  $currentPrompt = $config->get('prompt');
  // Update the variables to the new variable naming.
  $currentPrompt = strtr($currentPrompt, [
    '{{ source_lang }}' => '{sourceLang}',
    '{{ source_lang_name }}' => '{sourceLangName}',
    '{{ dest_lang }}' => '{destLang}',
    '{{ dest_lang_name }}' => '{destLangName}',
    '{{ input_text }}' => '{inputText}',
  ]);

  // Make sure the appropriate prompt type exists.
  $promptManager->upsertPromptType([
    'id' => 'ai_translate',
    'label' => t('AI Translation prompt'),
    'variables' => [
      [
        'name' => 'sourceLang',
        'help_text' => 'ID of source language',
        'required' => FALSE,
      ],
      [
        'name' => 'sourceLangName',
        'help_text' => 'Name of source language',
        'required' => FALSE,
      ],
      [
        'name' => 'destLang',
        'help_text' => 'ID of destination language',
        'required' => FALSE,
      ],
      [
        'name' => 'destLangName',
        'help_text' => 'Name of destination language',
        'required' => TRUE,
      ],
      [
        'name' => 'inputText',
        'help_text' => 'The input text to translate',
        'required' => TRUE,
      ],
    ],
    'tokens' => [],
    'dependencies' => [
      'enforced' => ['module' => ['ai_translate']],
    ],
  ]);

  // Create the default AI translate prompt config entity, based on the current
  // textual prompt, configured on this site.
  $prompt = $promptManager->upsertPrompt([
    'id' => $defaultPromptId,
    'label' => t('Default prompt for AI translation'),
    'prompt' => $currentPrompt,
    'type' => 'ai_translate',
  ]);

  // Set the default prompt from /config/install as new default.
  $config->set('prompt', $prompt->id());

  // Update the language-specific prompt configuration.
  $languages = \Drupal::languageManager()->getLanguages();
  foreach ($languages as $langcode => $language) {
    $langSpecificPrompt = $config->get('language_settings.' . $langcode . '.prompt');
    if (empty($langSpecificPrompt)) {
      // If language-specific prompt is empty, it was using the default prompt.
      // Update the config to point to that default prompt ID.
      $config->set('language_settings.' . $langcode . '.prompt', $defaultPromptId);
    }
    else {
      // Update the variables to the new variable naming.
      $langSpecificPrompt = strtr($langSpecificPrompt, [
        '{{ source_lang }}' => '{sourceLang}',
        '{{ source_lang_name }}' => '{sourceLangName}',
        '{{ dest_lang }}' => '{destLang}',
        '{{ dest_lang_name }}' => '{destLangName}',
        '{{ input_text }}' => '{inputText}',
      ]);
      $langSpecificPromptEntity = $promptManager->upsertPrompt([
        'id' => 'ai_translate__ai_translate_' . $langcode,
        'label' => t('AI Translation prompt: @language', ['@language' => $language->getName()]),
        'prompt' => $langSpecificPrompt,
        'type' => 'ai_translate',
      ]);
      // Set the created prompt ID for this language.
      $config->set('language_settings.' . $langcode . '.prompt', $langSpecificPromptEntity->id());
    }
  }

  // Save the config.
  $config->save();
}

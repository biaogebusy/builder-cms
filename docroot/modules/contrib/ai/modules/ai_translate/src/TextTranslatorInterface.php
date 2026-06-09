<?php

namespace Drupal\ai_translate;

use Drupal\Core\Language\LanguageInterface;

/**
 * Defines text translator interface.
 */
interface TextTranslatorInterface {

  /**
   * Get the text translated by AI API call.
   *
   * @param string $input_text
   *   Input prompt for the LLm.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   Destination language.
   * @param \Drupal\Core\Language\LanguageInterface|null $langFrom
   *   Optional source language.
   * @param array $context
   *   Translation context. Possible keys include, but not limited to:
   *   - preferred_model
   *   - preferred_version
   *   - preferred_provider.
   *
   * @return string
   *   Translated content.
   *
   * @throws \Drupal\ai_translate\TranslationException
   */
  public function translateContent(
    string $input_text,
    LanguageInterface $langTo,
    ?LanguageInterface $langFrom = NULL,
    array $context = [],
  ) : string;

}

<?php

namespace Drupal\ai\OperationType\TranslateText;

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for text translations.
 */
class TranslateTextInput extends InputBase implements InputInterface {

  /**
   * The text to translate.
   *
   * @var string
   */
  private string $text;

  /**
   * The source language.
   *
   * @var null|string
   */
  private ?string $sourceLanguage;

  /**
   * The target language.
   *
   * @var string
   */
  private string $targetLanguage;

  /**
   * The constructor.
   *
   * @param string $text
   *   The text to translate.
   * @param string|null $sourceLanguage
   *   The source language.
   * @param string $targetLanguage
   *   The target language.
   */
  public function __construct(string $text, ?string $sourceLanguage, string $targetLanguage) {
    $this->text = $text;
    $this->sourceLanguage = $sourceLanguage;
    $this->targetLanguage = $targetLanguage;
  }

  /**
   * Get the text to translate.
   *
   * @return string
   *   The text.
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * Set the text to translate.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text) {
    $this->text = $text;
  }

  /**
   * Get the source language.
   *
   * @return string|null
   *   The source language, or NULL if not set.
   */
  public function getSourceLanguage(): ?string {
    return $this->sourceLanguage;
  }

  /**
   * Set the source language.
   *
   * @param string $sourceLanguage
   *   The source language.
   */
  public function setSourceLanguage(string $sourceLanguage) {
    $this->sourceLanguage = $sourceLanguage;
  }

  /**
   * Set the target language.
   *
   * @param string $targetLanguage
   *   The target language.
   */
  public function setTargetLanguage(string $targetLanguage) {
    $this->targetLanguage = $targetLanguage;
  }

  /**
   * Get the target language.
   *
   * @return string
   *   The target language.
   */
  public function getTargetLanguage(): string {
    return $this->targetLanguage;
  }

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    return $this->text;
  }

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function __toString(): string {
    return $this->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'text' => $this->text,
      'source_language' => $this->sourceLanguage,
      'target_language' => $this->targetLanguage,
    ];
  }

}

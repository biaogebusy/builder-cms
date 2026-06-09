<?php

namespace Drupal\ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an AI Prompt entity.
 */
interface AiPromptInterface extends ConfigEntityInterface {

  /**
   * The entity label.
   *
   * @return string
   *   The label.
   */
  public function label(): string;

  /**
   * The entity type.
   *
   * @return string
   *   The bundle.
   */
  public function bundle(): string;

  /**
   * Gets the prompt text.
   *
   * @return string
   *   The prompt text.
   */
  public function getPrompt(): string;

  /**
   * Sets the prompt text.
   *
   * @param string $prompt
   *   The prompt text.
   */
  public function setPrompt($prompt): void;

  /**
   * Get the prompt type for this prompt.
   *
   * @return \Drupal\ai\Entity\AiPromptTypeInterface
   *   The prompt type entity.
   */
  public function getType(): AiPromptTypeInterface;

  /**
   * Get the variables from the AI Prompt Type bundle.
   *
   * @return array<int, array{name: string, help_text: string, required: bool}>
   *   The prompt type variables.
   *
   * @see \Drupal\ai\Entity\AiPromptTypeInterface::getVariables()
   */
  public function getTypeVariables(): array;

  /**
   * Get the tokens from the AI Prompt Type bundle.
   *
   * @return array<int, array{name: string, help_text: string, required: bool}>
   *   The prompt type tokens.
   *
   * @see \Drupal\ai\Entity\AiPromptTypeInterface::getTokens()
   */
  public function getTypeTokens(): array;

}

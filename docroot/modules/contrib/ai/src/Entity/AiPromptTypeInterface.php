<?php

namespace Drupal\ai\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an AI Prompt Types.
 */
interface AiPromptTypeInterface extends ConfigEntityInterface {

  /**
   * The entity label.
   *
   * @return string
   *   The label.
   */
  public function label(): string;

  /**
   * Determine the variables for this type.
   *
   * @return array<int, array{name: string, help_text: string, required: bool}>
   *   The required and suggested variables for this type. Each nested array
   *   contains:
   *   - name: string. The variable name.
   *   - help_text: string. A site editor facing description of the variable.
   *   - required: boolean. Whether the prompt validation will check existence.
   */
  public function getVariables(): array;

  /**
   * Determine the tokens for this type.
   *
   * @return array<int, array{name: string, help_text: string, required: bool}>
   *   The required and suggested tokens for this type. Each nested array
   *    contains:
   *    - name: string. The token name.
   *    - help_text: string. A site editor facing description of the token.
   *    - required: boolean. Whether the prompt validation will check existence.
   */
  public function getTokens(): array;

  /**
   * Determine the number of AI Prompts of this type.
   *
   * @return int
   *   The number of prompts.
   */
  public function getPromptCount(): int;

}

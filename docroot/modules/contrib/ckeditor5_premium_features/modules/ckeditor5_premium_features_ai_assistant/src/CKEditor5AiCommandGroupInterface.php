<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a CKEditor 5 AI commands group entity type.
 */
interface CKEditor5AiCommandGroupInterface extends ConfigEntityInterface {

  /**
   * Add command to the commands list.
   *
   * @param array $command
   *   Array with command values. ai_command
   *   [ command_id, label, weight, prompt ].
   *
   * @return \Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addCommand(array $command): static;

  /**
   * Get command from commands list.
   *
   * @param string $uuid
   *   Command uuid.
   *
   * @return array
   */
  public function getCommandByUuid(string $uuid): array;

  /**
   * Remove command from commands list.
   *
   * @param string $uuid
   *   Command uuid.
   *
   * @return \Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeCommand(string $uuid):static;

  /**
   * Update command values.
   *
   * @param array $command
   *   Array with command values.
   *   [ uuid, command_id, label, weight, prompt ].
   *
   * @return \Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updateCommand(array $command): static;

  /**
   * Update weights of commands.
   *
   * @param array $weights
   *   Array with weights associated with commands.
   *
   * @return \Drupal\ckeditor5_premium_features_ai_assistant\Entity\CKEditor5AiCommandGroup
   */
  public function updateWeights(array $weights): static;

  /**
   * Returns an array of definitions.
   */
  public function getDefinition(): array;

  /**
   * Checks if command with provided id exists.
   *
   * @param string $id
   *   Command id.
   *
   * @return bool
   */
  public function commandExists(string $id): bool;

}

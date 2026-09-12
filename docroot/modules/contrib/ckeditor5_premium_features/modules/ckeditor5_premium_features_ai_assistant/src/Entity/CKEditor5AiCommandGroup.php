<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types = 1);

namespace Drupal\ckeditor5_premium_features_ai_assistant\Entity;

use Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiCommandGroupInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the ckeditor 5 AI commands group entity type.
 *
 * @ConfigEntityType(
 *   id = "ckeditor5_ai_command_group",
 *   label = @Translation("CKEditor 5 AI Command Group"),
 *   label_collection = @Translation("CKEditor 5 AI Commands Group"),
 *   label_singular = @Translation("CKEditor 5 AI Command Group"),
 *   label_plural = @Translation("CKEditor 5 AI Commands Group"),
 *   label_count = @PluralTranslation(
 *     singular = "@count CKEditor 5 AI Command Group",
 *     plural = "@count CKEditor 5 AI Commands Group",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ckeditor5_premium_features_ai_assistant\CKEditor5AiCommandGroupListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ckeditor5_premium_features_ai_assistant\Form\CKEditor5AiCommandGroupForm",
 *       "edit" = "Drupal\ckeditor5_premium_features_ai_assistant\Form\CKEditor5AiCommandGroupForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "ckeditor5_ai_command_group",
 *   admin_permission = "administer ckeditor5_ai_command_group",
 *   links = {
 *     "collection" = "/admin/structure/ckeditor5-ai-command-group",
 *     "add-form" = "/admin/structure/ckeditor5-ai-command-group/add",
 *     "edit-form" = "/admin/structure/ckeditor5-ai-command-group/{ckeditor5_ai_command_group}",
 *     "delete-form" = "/admin/structure/ckeditor5-ai-command-group/{ckeditor5_ai_command_group}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "commands",
 *     "textFormats",
 *   }
 * )
 */
class CKEditor5AiCommandGroup extends ConfigEntityBase implements CKEditor5AiCommandGroupInterface {

  /**
   * The CKEditor 5 AI Commands group ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The CKEditor 5 AI Commands group label.
   *
   * @var string
   */
  protected string $label;

  /**
   * The commands associated with the CommandGroup.
   *
   * @var array
   */
  protected ?array $commands;

  /**
   * Allowed text formats.
   *
   * @var array
   */
  protected ?array $textFormats;

  /**
   * {@inheritdoc}
   */
  public function addCommand(array $command): static {
    $command['uuid'] = $this->uuidGenerator()->generate();
    $this->commands[] = $command;
    $this->set('commands', $this->commands)->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCommandByUuid(string $uuid): array {
    $command = array_filter($this->commands, fn($command) => $command['uuid'] === $uuid);
    return reset($command);
  }

  /**
   * {@inheritdoc}
   */
  public function removeCommand(string $uuid):static {
    $commands = array_filter($this->commands, fn($command) => $command['uuid'] !== $uuid);
    $this->set('commands', $commands)->save();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateCommand(array $command): static {
    foreach ($this->commands as $key => $value) {
      if ($value['uuid'] === $command['uuid']) {
        $this->commands[$key] = $command;
        $this->save();
        return $this;
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateWeights(array $weights): static {
    foreach ($this->commands as $key => $command) {
      if (array_key_exists($command['uuid'], $weights)) {
        $this->commands[$key]['weight'] = $weights[$command['uuid']]['weight'];
      }
    }
    usort($this->commands, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition(): array {
    return [
      'id' => $this->id(),
      'label' => $this->label(),
      'commands' => $this->commands,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function commandExists(string $id): bool {
    if (!empty($this->commands)) {
      foreach ($this->commands as $command) {
        if (isset($command['command_id']) && $command['command_id'] === $id) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}

<?php

namespace Drupal\json_editor\Commands;

use Drush\Commands\DrushCommands;

/**
 * Defines an interface for Drush version agnostic commands.
 */
interface JsonEditorCliServiceInterface {

  /**
   * Set the Drush 9.x command.
   *
   * @param \Drush\Commands\DrushCommands $command
   *   A Drush 9.x command.
   */
  public function setCommand(DrushCommands $command);

  /**
   * Implements hook_drush_command().
   */
  public function json_editor_drush_command();

  /**
   * Implements drush_hook_COMMAND().
   */
  public function drush_json_editor_libraries_download();

  /**
   * Implements drush_hook_COMMAND().
   */
  public function drush_json_editor_generate_commands();
}
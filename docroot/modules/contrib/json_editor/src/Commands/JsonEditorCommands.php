<?php

/**
 * This is file was generated using Drush. DO NOT EDIT.
 *
 * @see drush json_editor-generate-commands
 * @see \Drupal\json_editor\Commands\DrushCliServiceBase::generate_commands_drush9
 */
namespace Drupal\json_editor\Commands;

/**
 * Json Editor commands for Drush 9.x.
 */
class JsonEditorCommands extends JsonEditorCommandsBase {
  /****************************************************************************/
  // drush json_editor:libraries:download. DO NOT EDIT.
  /****************************************************************************/

  /**
   * Download third party libraries required by the Json Editor module.
   *
   * @command json_editor:libraries:download
   * @usage json_editor:libraries:download
   *   Download third party libraries required by the Json Editor module.
   * @aliases jedl
   */
  public function drush_json_editor_libraries_download() {
    $this->cliService->drush_json_editor_libraries_download();
  }

  /**
   * Generate Drush commands from json_editor.drush.inc for Drush 8.x to JsonEditorCommands for Drush 9.x.
   *
   * @command json_editor:generate:commands
   * @usage drush json_editor:generate:commands
   *   Generate Drush commands from json_editor.drush.inc for Drush 8.x to JsonEditorCommands for Drush 9.x.
   * @aliases jegc
   */
  public function drush_json_editor_generate_commands() {
    $this->cliService->drush_json_editor_generate_commands();
  }
}

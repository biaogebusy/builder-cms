<?php

namespace Drupal\json_editor\Commands;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Serialization\Yaml;
use Drush\Commands\DrushCommands;

/**
 * Drush version agnostic commands.
 */
class JsonEditorCliService implements JsonEditorCliServiceInterface {

  /**
   * A Drush 9.x command.
   *
   * @var \Drupal\json_editor\Commands\JsonEditorCommands
   */
  protected $command;

  /**
   * Set the drush 9.x command.
   *
   * @param \Drupal\json_editor\Commands\JsonEditorCommands $command
   *   A Drush 9.x command.
   */
  public function setCommand(DrushCommands $command) {
    $this->command = $command;
  }

  /**
   * Call JsonEditorCommands method or drush function.
   *
   * @param string $name
   *   Function name.
   * @param array $arguments
   *   Function arguments.
   *
   * @return mixed
   *   Return function results.
   *
   * @throws \Exception
   *   Throw exception if JsonEditorCommands method and drush function is not found.
   */
  public function __call($name, array $arguments) {
    if ($this->command && method_exists($this->command, $name)) {
      return call_user_func_array([$this->command, $name], $arguments);
    }
    elseif (function_exists($name)) {
      return call_user_func_array($name, $arguments);
    }
    else {
      throw new \Exception("Unknown method/function '$name'.");
    }
  }

  /**
   * Constructs a JsonEditorCliService object.
   */
  public function __construct() {
    // @todo Add dependency injections.
  }

  /**
   * {@inheritdoc}
   */
  public function json_editor_drush_command() {
    $items = [];

    $items['json_editor-libraries-download'] = [
      'description' => 'Download third party libraries required by the Json Editor module.',
      'core' => ['8+'],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_ROOT,
      'examples' => [
        'json_editor-libraries-download' => 'Download third party libraries required by the Json Editor module.',
      ],
      'aliases' => ['jedl'],
    ];
    $items['json_editor-generate-commands'] = [
      'description' => 'Generate Drush commands from json_editor.drush.inc for Drush 8.x to JsonEditorCommands for Drush 9.x.',
      'core' => ['8+'],
      'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_SITE,
      'examples' => [
        'drush json_editor-generate-commands' => "Generate Drush commands from json_editor.drush.inc for Drush 8.x to JsonEditorCommands for Drush 9.x.",
      ],
      'aliases' => ['jegc'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function drush_json_editor_libraries_download() {
    $temp_dir = $this->drush_tempdir();

    /** @var \Drupal\json_editor\JsonEditorLibrariesManagerInterface $libraries_manager */
    $libraries_manager = \Drupal::service('json_editor.libraries_manager');
    $libraries = $libraries_manager->getLibraries(TRUE);
    foreach ($libraries as $library_name => $library) {
      // Skip libraries installed by other modules.
      if (!empty($library['module'])) {
        continue;
      }

      // Download archive to temp directory.
      $download_url = $library['download_url']->toString();
      $this->drush_print("Downloading $download_url");

      $temp_filepath = $temp_dir . '/' . basename(current(explode('?', $download_url, 2)));
      $this->drush_download_file($download_url, $temp_filepath);

      // Extract ZIP archive.
      $download_location = DRUPAL_ROOT . "/libraries/$library_name";
      $this->drush_print("Extracting to $download_location");

      // Extract to temp location.
      $temp_location = $this->drush_tempdir();
      if (!$this->drush_tarball_extract($temp_filepath, $temp_location)) {
        $this->drush_set_error("Unable to extract $library_name");
        return;
      }

      // Move files and directories from temp location to download location.
      // using rename.
      $files = scandir($temp_location);
      // Remove directories (. ..)
      unset($files[0], $files[1]);
      if ((count($files) == 1) && is_dir($temp_location . '/' . current($files))) {
        $temp_location .= '/' . current($files);
      }
      $this->drush_move_dir($temp_location, $download_location);

      // Remove the tarball.
      if (file_exists($temp_filepath)) {
        $this->drush_delete_dir($temp_filepath, TRUE);
      }
    }

    drupal_flush_all_caches();
  }

  /******************************************************************************/
  // Generate commands.
  /******************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function drush_json_editor_generate_commands() {
    // Drush 8.x.
    $commands = $this->drush_json_editor_generate_commands_drush8();
    $filepath = DRUPAL_ROOT . '/' . $this->drupal_get_path('module', 'json_editor') . '/drush/json_editor.drush.inc';
    file_put_contents($filepath, $commands);
    $this->drush_print("$filepath updated.");

    // Drush 9.x.
    $commands = $this->drush_json_editor_generate_commands_drush9();
    $filepath = DRUPAL_ROOT . '/' . $this->drupal_get_path('module', 'json_editor') . '/src/Commands/JsonEditorCommands.php';
    file_put_contents($filepath, $commands);
    $this->drush_print("$filepath updated.");
  }

  /**
   * Generate json_editor.drush.inc for Drush 8.x.
   *
   * @return string
   *   json_editor.drush.inc for Drush 8.x.
   *
   * @see drush/json_editor.drush.inc
   */
  protected function drush_json_editor_generate_commands_drush8() {
    $items = $this->json_editor_drush_command();
    $functions = [];
    foreach ($items as $command_key => $command_item) {

      // Command name.
      $functions[] = "
/******************************************************************************/
// drush $command_key. DO NOT EDIT.
/******************************************************************************/";

      // Validate.
      $validate_method = 'drush_' . str_replace('-', '_', $command_key) . '_validate';
      $validate_hook = 'drush_' . str_replace('-', '_', $command_key) . '_validate';
      if (method_exists($this, $validate_method)) {
        $functions[] = "
/**
 * Implements drush_hook_COMMAND_validate().
 */
function $validate_hook() {
  return call_user_func_array([\Drupal::service('json_editor.cli_service'), '$validate_method'], func_get_args());
}";
      }

      // Commands.
      $command_method = 'drush_' . str_replace('-', '_', $command_key);
      $command_hook = 'drush_' . str_replace('-', '_', $command_key);
      if (method_exists($this, $command_method)) {
        $functions[] = "
/**
 * Implements drush_hook_COMMAND().
 */
function $command_hook() {
  return call_user_func_array([\Drupal::service('json_editor.cli_service'), '$command_method'], func_get_args());
}";
      }
    }

    // Build commands.
    $commands = Variable::export($this->json_editor_drush_command());
    // Remove [datatypes] which are only needed for Drush 9.x.
    $commands = preg_replace('/\[(boolean)\]\s+/', '', $commands);
    $commands = trim(preg_replace('/^/m', '  ', $commands));

    // Include.
    $functions = implode(PHP_EOL, $functions) . PHP_EOL;

    return "<?php

// @codingStandardsIgnoreFile

/**
 * This is file was generated using Drush. DO NOT EDIT. 
 *
 * @see drush json_editor-generate-commands
 * @see \Drupal\json_editor\Commands\DrushCliServiceBase::generate_commands_drush8
 */

/**
 * Implements hook_drush_command().
 */
function json_editor_drush_command() {
  return $commands;
}
$functions
";
  }

  /**
   * Generate JsonEditorCommands class for Drush 9.x.
   *
   * @return string
   *   JsonEditorCommands class for Drush 9.x.
   *
   * @see \Drupal\json_editor\Commands\JsonEditorCommands
   */
  protected function drush_json_editor_generate_commands_drush9() {
    $items = $this->json_editor_drush_command();

    $methods = [];
    foreach ($items as $command_key => $command_item) {
      $command_name = str_replace('-', ':', $command_key);

      // Set defaults.
      $command_item += [
        'arguments' => [],
        'options' => [],
        'examples' => [],
        'aliases' => [],
      ];

      // Command name.
      $methods[] = "
  /****************************************************************************/
  // drush $command_name. DO NOT EDIT.
  /****************************************************************************/";

      // Validate.
      $validate_method = 'drush_' . str_replace('-', '_', $command_key) . '_validate';
      if (method_exists($this, $validate_method)) {
        $methods[] = "
  /**
   * @hook validate $command_name
   */
  public function $validate_method(CommandData \$commandData) {
    \$arguments = \$commandData->arguments();
    array_shift(\$arguments);
    call_user_func_array([\$this->cliService, '$validate_method'], \$arguments);
  }";
      }

      // Command.
      $command_method = 'drush_' . str_replace('-', '_', $command_key);
      if (method_exists($this, $command_method)) {
        $command_params = [];
        $command_arguments = [];

        $command_annotations = [];
        // command.
        $command_annotations[] = "@command $command_name";
        // params.
        foreach ($command_item['arguments'] as $argument_name => $argument_description) {
          $command_annotations[] = "@param \$$argument_name $argument_description";
          $command_params[] = "\$$argument_name = NULL";
          $command_arguments[] = "\$$argument_name";
        }
        // options.
        $command_options = [];
        foreach ($command_item['options'] as $option_name => $option_description) {
          $option_default = NULL;
          // Parse [datatype] from option description.
          if (preg_match('/\[(boolean)\]\s+/', $option_description, $match)) {
            $option_description = preg_replace('/\[(boolean)\]\s+/', '', $option_description);
            switch ($match[1]) {
              case 'boolean':
                $option_default = FALSE;
                break;
            }
          }

          $command_annotations[] = "@option $option_name $option_description";
          $command_options[$option_name] = $option_default;
        }
        if ($command_options) {
          $command_options = Variable::export($command_options);
          $command_options = preg_replace('/\s+/', ' ', $command_options);
          $command_options = preg_replace('/array\(\s+/', '[', $command_options);
          $command_options = preg_replace('/, \)/', ']', $command_options);
          $command_params[] = "array \$options = $command_options";
        }

        // usage.
        foreach ($command_item['examples'] as $example_name => $example_description) {
          $example_name = str_replace('-', ':', $example_name);
          $command_annotations[] = "@usage $example_name";
          $command_annotations[] = "  $example_description";
        }
        // aliases.
        if ($command_item['aliases']) {
          $command_annotations[] = "@aliases " . implode(',', $command_item['aliases']);
        }

        $command_annotations = '   * ' . implode(PHP_EOL . '   * ', $command_annotations);
        $command_params = implode(', ', $command_params);
        $command_arguments = implode(', ', $command_arguments);

        $methods[] = "
  /**
   * {$command_item['description']}
   *
$command_annotations
   */
  public function $command_method($command_params) {
    \$this->cliService->$command_method($command_arguments);
  }";
      }
    }

    // Class.
    $methods = implode(PHP_EOL, $methods) . PHP_EOL;

    return "<?php
// @codingStandardsIgnoreFile

/**
 * This is file was generated using Drush. DO NOT EDIT. 
 *
 * @see drush json_editor-generate-commands
 * @see \Drupal\json_editor\Commands\DrushCliServiceBase::generate_commands_drush9
 */
namespace Drupal\json_editor\Commands;

use Consolidation\AnnotatedCommand\CommandData;

/**
 * Json Editor commands for Drush 9.x.
 */
class JsonEditorCommands extends JsonEditorCommandsBase {
$methods
}";
  }
}
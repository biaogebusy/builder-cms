<?php

namespace Drupal\json_editor\Commands;

use Drush\Commands\DrushCommands;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Base class for Json Editor commands for Drush 9.x.
 */
abstract class JsonEditorCommandsBase extends DrushCommands {

  /**
   * The json_editor CLI service.
   *
   * @var \Drupal\json_editor\Commands\JsonEditorCliServiceInterface
   */
  protected $cliService;

  /**
   * Constructs a JsonEditorCommandsBase object.
   *
   * @param \Drupal\json_editor\Commands\JsonEditorCliServiceInterface $cli_service
   *   The json_editor CLI service.
   */
  public function __construct(JsonEditorCliServiceInterface $cli_service) {
    $this->cliService = $cli_service;
    $this->cliService->setCommand($this);
  }

  public function drush_print($message) {
    $this->output()->writeln($message);
  }

  public function drush_mkdir($path) {
    $fs = new Filesystem();
    $fs->mkdir($path);
    return TRUE;
  }

  public function drush_set_error($error) {
    throw new \Exception($error);
  }

  public function drush_move_dir($src, $dest) {
    $fs = new Filesystem();
    $fs->rename($src, $dest, TRUE);
    return TRUE;
  }

  public function drush_download_file($url, $destination) {
    // Copied from: \Drush\Commands\SyncViaHttpCommands::downloadFile
    static $use_wget;
    if ($use_wget === NULL) {
      $use_wget = drush_shell_exec('which wget');
    }

    $destination_tmp = drush_tempnam('download_file');
    if ($use_wget) {
      drush_shell_exec("wget -q --timeout=30 -O %s %s", $destination_tmp, $url);
    }
    else {
      drush_shell_exec("curl -s -L --connect-timeout 30 -o %s %s", $destination_tmp, $url);
    }
    if (!drush_file_not_empty($destination_tmp) && $file = @file_get_contents($url)) {
      @file_put_contents($destination_tmp, $file);
    }
    if (!drush_file_not_empty($destination_tmp)) {
      // Download failed.
      throw new \Exception(dt("The URL !url could not be downloaded.", ['!url' => $url]));
    }
    if ($destination) {
      $fs = new Filesystem();
      $fs->rename($destination_tmp, $destination, TRUE);
      return $destination;
    }
    return $destination_tmp;
  }

  public function drush_tarball_extract($path, $destination = FALSE) {
    $this->drush_mkdir($destination);
    if (preg_match('/\.tgz$/', $path)) {
      $return = drush_shell_cd_and_exec(dirname($path), "tar -xvzf %s -C %s", $path, $destination);
      if (!$return) {
        throw new \Exception(dt('Unable to extract !filename.' . PHP_EOL . implode(PHP_EOL, drush_shell_exec_output()), ['!filename' => $path]));
      }
    }
    else {
      $return = drush_shell_cd_and_exec(dirname($path), "unzip %s -d %s", $path, $destination);
      if (!$return) {
        throw new \Exception(dt('Unable to extract !filename.' . PHP_EOL . implode(PHP_EOL, drush_shell_exec_output()), ['!filename' => $path]));
      }
    }
    return $return;
  }
}
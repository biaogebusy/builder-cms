<?php

namespace Drupal\chosen_lib\Commands;

use Drupal\Core\File\FileSystemInterface;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Psr\Log\LogLevel;

/**
 * The Chosen plugin URI.
 */
define('CHOSEN_DOWNLOAD_URI', 'https://github.com/noli42/chosen/releases/download/3.1.4/chosen-assets-v3.1.4.zip');

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class ChosenLibCommands extends DrushCommands {

  /**
   * {@inheritdoc}
   */
  public function __construct(private FileSystemInterface $fileSystem) {
    parent::__construct();
  }

  /**
   * Download and install the Chosen plugin.
   *
   * @param string $path
   *   Optional. A path where to install the Chosen plugin. If omitted Drush
   *   will use the default location.
   *
   * @command chosen:plugin
   * @aliases chosenplugin,chosen-plugin
   *
   * @throws \Exception
   */
  public function plugin($path = '') {
    if (empty($path)) {
      $path = 'libraries';
    }

    // Create the path if it does not exist.
    if (!is_dir($path)) {
      drush_op('mkdir', $path);
      $this->drushLog(dt('Directory @path was created', ['@path' => $path]), 'notice');
    }

    // Set the directory to the download location.
    $olddir = getcwd();
    chdir($path);

    // Download the zip archive.
    if ($filepath = $this->drushDownloadFile(CHOSEN_DOWNLOAD_URI)) {
      $filename = basename($filepath);
      $dirname = basename($filepath, '.zip');

      // Remove any existing Chosen plugin directory.
      if (is_dir('chosen')) {
        $fileservice = $this->fileSystem;
        $fileservice->deleteRecursive('chosen');

        $this->drushLog(dt('A existing Chosen plugin was deleted from @path', ['@path' => $path]), 'notice');
      }

      // Decompress the zip archive.
      $this->drushTarballExtract($filename, $dirname);

      // Change the directory name to "chosen" if needed.
      if ('chosen' !== $dirname) {
        $subdirname = $dirname . '/chosen-' . $dirname;
        if (is_dir($subdirname)) {
          $this->drushMoveDir($subdirname, 'chosen');
          $fileservice = $this->fileSystem;
          $fileservice->deleteRecursive($dirname);
        }
        else {
          $this->drushMoveDir($dirname, 'chosen');
        }
        $dirname = 'chosen';
      }

      unlink($filename);
    }

    if (is_dir($dirname)) {
      $this->drushLog(dt('Chosen plugin has been installed in @path', ['@path' => $path]), 'success');
    }
    else {
      $this->drushLog(dt('Drush was unable to install the Chosen plugin to @path', ['@path' => $path]), 'error');
    }

    // Set working directory back to the previous working directory.
    chdir($olddir);
  }

  /**
   * Logs with an arbitrary level.
   *
   * @param string $message
   *   The log message.
   * @param mixed $type
   *   The log type.
   */
  public function drushLog($message, $type = LogLevel::INFO) {
    $this->logger()->log($type, $message);
  }

  /**
   * Downloads a file from a given URL and saves it to a destination.
   *
   * @param string $url
   *   The download url.
   * @param mixed $destination
   *   The destination path.
   *
   * @return bool|string
   *   The destination file.
   *
   * @throws \Exception
   */
  public function drushDownloadFile($url, $destination = FALSE) {
    // Generate destination if omitted.
    if (!$destination) {
      $file = basename(current(explode('?', $url, 2)));
      $destination = getcwd() . '/' . basename($file);
    }

    // Copied from: \Drush\Commands\SyncViaHttpCommands::downloadFile.
    static $use_wget;
    if ($use_wget === NULL) {
      $process = Drush::process(['which', 'wget']);
      $process->run();
      $use_wget = $process->isSuccessful();
    }

    $destination_tmp = drush_tempnam('download_file');
    if ($use_wget) {
      $args = ['wget', '-q', '--timeout=30', '-O', $destination_tmp, $url];
    }
    else {
      $args = ['curl', '-s', '-L', '--connect-timeout', '30', '-o', $destination_tmp, $url];
    }
    $process = Drush::process($args);
    $process->mustRun();

    if (!drush_file_not_empty($destination_tmp) && $file = @file_get_contents($url)) {
      @file_put_contents($destination_tmp, $file);
    }
    if (!drush_file_not_empty($destination_tmp)) {
      // Download failed.
      throw new \Exception(dt("The URL !url could not be downloaded.", ['!url' => $url]));
    }
    if ($destination) {
      $fileservice = $this->fileSystem;
      $fileservice->move($destination_tmp, $destination, TRUE);
      return $destination;
    }
    return $destination_tmp;
  }

  /**
   * Moves a file or directory to a new location.
   *
   * This function uses Drupal's FileSystem service to move a file or directory
   * from the source path to the destination path. If the destination already
   * exists, it will be replaced.
   *
   * @param string $src
   *   The absolute path of the source file or directory.
   * @param string $dest
   *   The absolute path of the destination file or directory.
   *
   * @return bool
   *   Returns TRUE after the move operation is attempted.
   */
  public function drushMoveDir($src, $dest) {
    $fileservice = $this->fileSystem;
    $fileservice->move($src, $dest, TRUE);
    return TRUE;
  }

  /**
   * Creates a directory at the specified path.
   *
   * This function uses Drupal's FileSystem service to create a directory.
   * If the directory already exists, no action is taken.
   *
   * @param string $path
   *   The absolute path of the directory to be created.
   *
   * @return bool
   *   Returns TRUE if the directory creation is attempted.
   */
  public function drushMkdir($path) {
    $fileservice = $this->fileSystem;
    $fileservice->mkdir($path);
    return TRUE;
  }

  /**
   * Extracts a tarball or zip archive to the specified destination.
   *
   * This function supports both `.tgz` and `.zip` file extraction.
   * It ensures the destination directory exists before extraction.
   *
   * @param string $path
   *   The absolute path to the archive file (.tgz or .zip).
   * @param string|bool $destination
   *   The destination directory where the archive should be extracted.
   *   If FALSE, the function does nothing.
   *
   * @return bool
   *   TRUE if the extraction was successful, FALSE otherwise.
   *
   * @throws \Exception
   *   If the extraction fails.
   */
  public function drushTarballExtract($path, $destination = FALSE) {
    $this->drushMkdir($destination);
    $cwd = getcwd();
    if (preg_match('/\.tgz$/', $path)) {
      drush_op('chdir', dirname($path));
      $process = Drush::process(['tar', '-xvzf', $path, '-C', $destination]);
      $process->run();
      $return = $process->isSuccessful();
      drush_op('chdir', $cwd);

      if (!$return) {
        throw new \Exception(dt('Unable to extract !filename.' . PHP_EOL . $process->getOutput(), ['!filename' => $path]));
      }
    }
    else {
      drush_op('chdir', dirname($path));
      $process = Drush::process(['unzip', $path, '-d', $destination]);
      $process->run();
      $return = $process->isSuccessful();
      drush_op('chdir', $cwd);

      if (!$return) {
        throw new \Exception(dt('Unable to extract !filename.' . PHP_EOL . $process->getOutput(), ['!filename' => $path]));
      }
    }

    return $return;
  }

}

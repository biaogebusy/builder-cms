<?php

namespace Drupal\colorbox\Commands;

use Drupal\Core\Asset\libraryDiscovery;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://git.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://git.drupalcode.org/devel/tree/drush.services.yml
 */
class ColorboxCommands extends DrushCommands {

  /**
   * Library discovery service.
   *
   * @var Drupal\Core\Asset\libraryDiscovery
   */
  protected $libraryDiscovery;

  /**
   * {@inheritdoc}
   */
  public function __construct(libraryDiscovery $library_discovery) {
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * Download and install the Colorbox plugin.
   *
   * @param mixed $path
   *   Optional. A path where to install the Colorbox plugin.
   *   If omitted Drush will use the default location.
   *
   * @command colorbox:plugin
   * @aliases colorboxplugin,colorbox-plugin
   */
  public function download($path = '') {

    $fs = new Filesystem();

    if (empty($path)) {
      $path = DRUPAL_ROOT . '/libraries/colorbox';
    }

    // Create path if it doesn't exist
    // Exit with a message otherwise.
    if (!$fs->exists($path)) {
      $fs->mkdir($path);
    }
    else {
      $this->logger()->notice(dt('Colorbox is already present at @path. No download required.', ['@path' => $path]));
      return;
    }

    // Load the colorbox defined library.
    if ($colorbox_library = $this->libraryDiscovery->getLibraryByName('colorbox', 'colorbox')) {
      // Download the file.
      $client = new Client();
      $destination = tempnam(sys_get_temp_dir(), 'colorbox-tmp');
      try {
        $client->get($colorbox_library['remote'] . '/archive/master.zip', ['save_to' => $destination]);
      }
      catch (RequestException $e) {
        // Remove the directory.
        $fs->remove($path);
        $this->logger()->error(dt('Drush was unable to download the colorbox library from @remote. @exception', [
          '@remote' => $colorbox_library['remote'] . '/archive/master.zip',
          '@exception' => $e->getMessage(),
        ], 'error'));
        return;
      }

      // Move downloaded file.
      $fs->rename($destination, $path . '/colorbox.zip');

      // Unzip the file.
      $zip = new \ZipArchive();
      $res = $zip->open($path . '/colorbox.zip');
      if ($res === TRUE) {
        $zip->extractTo($path);
        $zip->close();
      }
      else {
        // Remove the directory if unzip fails and exit.
        $fs->remove($path);
        $this->logger()->error(dt('Error: unable to unzip colorbox file.', [], 'error'));
        return;
      }

      // Remove the downloaded zip file.
      $fs->remove($path . '/colorbox.zip');

      // Move the file.
      $fs->mirror($path . '/colorbox-master', $path, NULL, ['override' => TRUE]);
      $fs->remove($path . '/colorbox-master');

      // Success.
      $this->logger()->notice(dt('The colorbox library has been successfully downloaded to @path.', [
        '@path' => $path,
      ], 'success'));
    }
    else {
      $this->logger()->error(dt('Drush was unable to load the colorbox library'));
    }
  }

  /**
   * Download and install the DOMPurify plugin.
   *
   * @param mixed $path
   *   Optional. A path where to install the DOMPurify plugin.
   *   If omitted Drush will use the default location.
   *
   * @command colorbox:dompurify
   * @aliases colorboxdompurify,colorbox-dompurify
   */
  public function domPurify($path = '') {

    $fs = new Filesystem();

    if (empty($path)) {
      $path = DRUPAL_ROOT . '/libraries/DOMPurify';
    }

    // Create path if it doesn't exist
    // Exit with a message otherwise.
    if (!$fs->exists($path)) {
      $fs->mkdir($path);
    }
    else {
      $this->logger()->notice(dt('DOMPurify is already present at @path. No download required.', ['@path' => $path]));
      return;
    }

    // Load the DOMPurify defined library.
    if ($dompurify_library = $this->libraryDiscovery->getLibraryByName('colorbox', 'dompurify')) {
      // Download the file.
      $client = new Client();
      $destination = tempnam(sys_get_temp_dir(), 'DOMPurify-tmp');
      try {
        $client->get($dompurify_library['remote'] . '/archive/main.zip', ['save_to' => $destination]);
      }
      catch (RequestException $e) {
        // Remove the directory.
        $fs->remove($path);
        $this->logger()->error(dt('Drush was unable to download the DOMPurify library from @remote. @exception', [
          '@remote' => $dompurify_library['remote'] . '/archive/main.zip',
          '@exception' => $e->getMessage(),
        ], 'error'));
        return;
      }

      // Move downloaded file.
      $fs->rename($destination, $path . '/DOMPurify.zip');

      // Unzip the file.
      $zip = new \ZipArchive();
      $res = $zip->open($path . '/DOMPurify.zip');
      if ($res === TRUE) {
        $zip->extractTo($path);
        $zip->close();
      }
      else {
        // Remove the directory if unzip fails and exit.
        $fs->remove($path);
        $this->logger()->error(dt('Error: unable to unzip DOMPurify file.', [], 'error'));
        return;
      }

      // Remove the downloaded zip file.
      $fs->remove($path . '/DOMPurify.zip');

      // Move the dist directory.
      $fs->mirror($path . '/DOMPurify-main/dist', $path . '/dist', NULL, ['override' => TRUE]);
      $fs->remove($path . '/DOMPurify-main');

      // Success.
      $this->logger()->notice(dt('The DOMPurify library has been successfully downloaded to @path.', [
        '@path' => $path,
      ], 'success'));
    }
    else {
      $this->logger()->error(dt('Drush was unable to load the DOMPurify library'));
    }
  }


}

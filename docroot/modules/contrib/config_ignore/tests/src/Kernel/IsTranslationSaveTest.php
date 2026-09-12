<?php

namespace Drupal\Tests\config_ignore\Kernel;

use Composer\InstalledVersions;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the config ignore pattern resolver.
 *
 * @group config_ignore
 */
class IsTranslationSaveTest extends KernelTestBase {

  /**
   * Test the translatable strings.
   */
  public function testLocaleStringIsSafe(): void {
    // Include the locale functions once, skipping Drupal.
    $drupalPath = InstalledVersions::getInstallPath('drupal/core');
    include_once $drupalPath . '/modules/locale/locale.module';

    // We extract the translatable strings via the potx module.
    // However, the potx module does not really offer this as an API.
    // It doesn't even do things in a "modern PHP" way.
    // So the code below roughly does what the potx drush command does.
    // Include potx files to access their functions.
    $potxPath = InstalledVersions::getInstallPath('drupal/potx');
    include_once $potxPath . '/potx.inc';
    include_once $potxPath . '/potx.local.inc';

    try {
      // Find the source files in our module.
      // Note that there is no module file and no translatable config.
      potx_status('set', POTX_STATUS_SILENT);
      $path = __DIR__ . '/../../../src';
      potx_local_init($path);
      $files = _potx_explore_dir($path . '/*');
      // Process the files, we use the defaults for everything.
      foreach ($files as $file) {
        _potx_process_file($file);
      }
      // Get the data from potx. It saves its state in a global variable.
      /** @var array $potxData */
      $potxData = _potx_save_string();
      $strings = array_keys($potxData);
    }
    catch (\Exception $e) {
      self::fail($e->getMessage());
    }

    foreach ($strings as $string) {
      self::assertTrue(locale_string_is_safe($string), sprintf('The string "%s" is safe', $string));
    }
  }

}

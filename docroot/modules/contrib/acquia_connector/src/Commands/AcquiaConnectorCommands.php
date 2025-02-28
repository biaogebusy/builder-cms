<?php

namespace Drupal\acquia_connector\Commands;

use Drupal\acquia_connector\Controller\SpiController;
use Drupal\acquia_connector\Controller\TestStatusController;
use Drupal\Component\Serialization\Json;
use Drush\Commands\DrushCommands;

/**
 * A Drush command file.
 *
 * Drush integration for the Acquia Connector module.
 */
class AcquiaConnectorCommands extends DrushCommands {

  /**
   * Output raw Acquia SPI data.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option outfile
   *   Optional. A file to write data to in the current directory. If omitted
   *   Drush will output to stdout.
   * @option format
   *   Optional. Format may be json, print_r, or var_dump. Defaults to print_r.
   *
   * @command acquia:connector:spi-get
   *
   * @usage acquia:connector:spi-get --format=json --outfile=spi.json
   *   Write JSON encoded SPI data to spi.json in current directory.
   */
  public function spiGet(array $options = ['outfile' => NULL, 'format' => NULL]) {

    $raw_spi = $this->drushSpiGet();
    switch ($options['format']) {
      case 'json':
        $spi = Json::encode($raw_spi);
        break;

      case 'var_dump':
      case 'var_export':
        $spi = var_export($raw_spi, TRUE);
        break;

      case 'print_r':
      default:
        $spi = print_r($raw_spi, TRUE);
        break;
    }

    if (!$options['outfile']) {
      $this->output->writeln($spi);
      return;
    }

    $file = $options['outfile'];
    // Path is relative.
    if (strpos($file, DIRECTORY_SEPARATOR) !== 0) {
      $file = ($_SERVER['PWD'] ?? getcwd()) . DIRECTORY_SEPARATOR . $file;
    }
    if (file_put_contents($file, $spi)) {
      $this->logger->info('SPI Data written to @outfile.', ['@outfile' => realpath($file)]);
    }
    else {
      $this->logger->error('Unable to write SPI Data into @outfile.', ['@outfile' => realpath($file)]);
    }

  }

  /**
   * A command callback and drush wrapper for custom test validation.
   *
   * @command acquia:connector:spi-test-validate
   *
   * @aliases acquia:connector:spi-tv
   *
   * @usage acquia:connector:spi-test-validate
   *   Perform a validation check on any modules with Acquia SPI custom tests.
   *
   * @validate-module-enabled acquia_connector
   */
  public function customTestValidate() {

    // Compatibility for Drupal 9.2.
    if (method_exists(\Drupal::moduleHandler(), 'invokeAllWith')) {
      $modules = [];
      \Drupal::moduleHandler()->invokeAllWith('acquia_connector_spi_test', function (callable $hook, string $module) use (&$modules) {
        // There is minimal overhead since the hook is not invoked.
        $modules[] = $module;
      });
    }
    else {
      // @phpstan-ignore-next-line
      $modules = \Drupal::moduleHandler()->getImplementations('acquia_connector_spi_test');
    }
    if (!$modules) {
      $this->output->writeln((string) dt('No Acquia SPI custom tests were detected.'));
      return;
    }

    $this->output->writeln((string) dt('Acquia SPI custom tests were detected in: @modules ' . PHP_EOL, ['@modules' => implode(', ', $modules)]));

    $pass = [];
    $failure = [];
    foreach ($modules as $module) {
      $function = $module . '_acquia_connector_spi_test';
      if (!function_exists($function)) {
        continue;
      }

      $testStatus = new TestStatusController();
      $result = $testStatus->testValidate($function());

      if (!$result['result']) {
        $failure[] = $module;
        $this->output->writeln((string) dt("[FAILED]  Validation failed for '@module' and has been logged.", ['@module' => $module]));

        foreach ($result['failure'] as $test_name => $test_failures) {
          foreach ($test_failures as $test_param => $test_value) {
            $variables = [
              '@module_name' => $module,
              '@message' => $test_value['message'],
              '@param_name' => $test_param,
              '@test_name' => $test_name,
              '@value' => $test_value['value'],
            ];
            $this->output->writeln((string) dt("[DETAILS] @message for parameter '@param_name'; current value '@value'. (Test @test_name in module @module_name)", $variables));
            $this->logger->error("<em>Custom test validation failed</em>: @message for parameter '@param_name'; current value '@value'. (<em>Test '@test_name' in module '@module_name'</em>)", $variables);
          }
        }
      }
      else {
        $pass[] = $module;
        $this->output->writeln((string) dt("[PASSED]  Validation passed for '@module.'", ['@module' => $module]));
      }

      $this->output->writeln('');
    }

    $this->output->writeln((string) dt('Validation checks completed.'));
    $variables = [];
    if (count($pass) > 0) {
      $variables['@passes'] = implode(', ', $pass);
      $variables['@pass_count'] = count($pass);
      $this->output->writeln((string) dt('@pass_count module(s) passed validation: @passes.'), $variables);
    }

    if (count($failure) > 0) {
      $variables['@failures'] = implode(', ', $failure);
      $variables['@fail_count'] = count($failure);
      $this->output->writeln((string) dt('@fail_count module(s) failed validation: @failures.'), $variables);
    }

  }

  /**
   * A command to send Acquia SPI data.
   *
   * @command acquia:connector:spi-send
   *
   * @usage drush -l <host_uri> acquia:connector:spi-send
   *   Sends Acquia SPI data.
   */
  public function spiSend() {
    $this->logger->error('This command is deprecated and no longer functions. Please remove from any scripts.');
  }

  /**
   * Helper method to include acquia_connector.module file.
   */
  protected function drushSpiGet() {
    global $conf;
    $conf['acquia_connector.settings']['spi']['ssl_verify'] = FALSE;
    $conf['acquia_connector.settings']['spi']['ssl_override'] = TRUE;

    $client = \Drupal::service('acquia_connector.client');
    $config = \Drupal::service('config.factory');
    $path_alias = \Drupal::service('path_alias.manager');
    $spi = new SpiController($client, $config, $path_alias);

    return $spi->get();
  }

}

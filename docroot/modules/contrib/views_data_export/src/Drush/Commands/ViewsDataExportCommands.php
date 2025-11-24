<?php

namespace Drupal\views_data_export\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\user\Entity\User;
use Drupal\views\Views;
use Drupal\views_data_export\BatchProcessingAdapterDrush;
use Drupal\views_data_export\Plugin\views\display\DataExport;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides Drush commands for exporting views.
 */
class ViewsDataExportCommands extends DrushCommands {

  /**
   * Constructs a new ViewsDataExportCommands object.
   */
  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected AccountSwitcherInterface $accountSwitcher,
  ) {
    parent::__construct();
  }

  /**
   * Instantiates a new instance of this class.
   *
   * @param \Psr\Container\ContainerInterface $container
   *   The service container this instance should use.
   *
   * @return static
   *   A new class instance.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_system'),
      $container->get('account_switcher'),
    );
  }

  /**
   * Implements views_data_export command arguments validation.
   *
   * @hook validate views_data_export:views-data-export
   */
  public function viewsDataExportValidate(CommandData $commandData) {
    $input = $commandData->input();
    $view_name = $input->getArgument('view_name');
    $display_id = $input->getArgument('display_id');
    $options = $commandData->options();
    $force = $options['force'];

    // Verify view existence.
    $view = Views::getView($view_name);
    if (is_null($view)) {
      return new CommandError(dt('The view !view does not exist.', ['!view' => $view_name]));
    }

    // Verify existence of the display.
    if ($view->setDisplay($display_id) === FALSE) {
      return new CommandError(dt('The view !view does not have the !display display.', [
        '!view' => $view_name,
        '!display' => $display_id,
      ]));
    }

    // Verify the display type.
    $view_display = $view->getDisplay();
    // @todo Can we do something smarter like find the only data export display, or show a choice for the user?
    if ($view_display->getPluginId() !== 'data_export') {
      return new CommandError(dt('Incorrect display_id provided, expected a views data export display, found !display instead.', [
        '!display' => $view_display->getPluginId(),
      ]));
    }

    // Verify final file location.
    if (!empty($options['output-file'])) {
      if (!$force && file_exists($options['output-file'])) {
        return new CommandError(dt('The desired output file: @filename already exists. Please remove the file and try again, or use the --force option to overwrite.', [
          '@filename' => realpath($options['output-file']),
        ]));
      }
    }

    // Do some basic validation of the uid option.
    if (isset($options['uid'])) {
      if (!is_numeric($options['uid'])) {
        return new CommandError(dt('The provided user id is not numeric.'));
      }
      else {
        if (!User::load($options['uid'])) {
          return new CommandError(dt('The provided user id does not exist.'));
        }
      }
    }
  }

  /**
   * Executes views_data_export display of a view and writes the output to file.
   */
  #[CLI\Command(name: 'views_data_export:views-data-export', aliases: ['vde'])]
  #[CLI\Argument(name: 'view_name', description: 'The name of the view.')]
  #[CLI\Argument(name: 'display_id', description: 'The id of the views_data_export display to execute on the view.')]
  #[CLI\Argument(name: 'arguments', description: 'The views contextual filter arguments, if any, separate multiple argument values with a /.')]
  #[CLI\Usage(name: 'views_data_export:views-data-export my_view_name display_id', description: 'Export my_view_name:display_id and display the results on stdout.')]
  #[CLI\Usage(name: 'views_data_export:views-data-export my_view_name display_id --output-file=../example.txt', description: 'Export my_view_name:display_id and additionally save the results to ../example.txt relative to the Drupal webroot.')]
  #[CLI\Option(name: 'force', description: 'Overwrite the output file if the file exists.')]
  #[CLI\Option(name: 'output-file', description: 'Additionally make a copy of the file to this location. Relative paths are considered to be relative to the Drupal webroot. Note that the original file that views data export creates will still exist in the location configured in the view.')]
  #[CLI\Option(name: 'uid', description: 'Run the data export as the given user. Useful if there are specific access restrictions etc.')]
  public function viewsDataExport(
    string $view_name,
    string $display_id,
    string $arguments = '',
    $options = [
      'force' => FALSE,
      'output-file' => NULL,
      'uid' => NULL,
    ],
  ): void {
    $this->logger()->notice(dt('Starting data export..'));

    $args = [];
    if ($arguments) {
      $args = explode('/', $arguments);
    }

    if (isset($options['uid'])) {
      $this->accountSwitcher->switchTo(new UserSession(['uid' => $options['uid']]));
    }

    $result = DataExport::buildResponse($view_name, $display_id, $args, new BatchProcessingAdapterDrush());

    if (isset($options['uid'])) {
      $this->accountSwitcher->switchBack();
    }

    // If we got a Response object, then the VDE was not batched.
    if ($result instanceof Response) {
      if (!empty($options['output-file'])) {
        // Save the response content to the specified output file.
        $output_directory = $this->fileSystem->dirname($options['output-file']);
        $this->fileSystem->prepareDirectory($output_directory, FileSystemInterface::CREATE_DIRECTORY);

        // Save the data to the desired output file.
        if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
          $this->fileSystem->saveData($result->getContent(), $options['output-file'], $options['force'] ? FileExists::Replace : FileExists::Error);
        }
        else {
          // @phpstan-ignore-next-line
          $this->fileSystem->saveData($result->getContent(), $options['output-file'], $options['force'] ? FileSystemInterface::EXISTS_REPLACE : FileSystemInterface::EXISTS_ERROR);
        }

        $this->logger()->success(dt('Data export additionally saved to @output_file', ['@output_file' => realpath($options['output-file'])]));
      }
      else {
        $this->output()->write($result->getContent());
      }
    }
    elseif (is_array($result)) {
      // An array, it should contain the vde_file key with the file path.
      if (!empty($result['drush_batch_process_finished']) || !empty($result['drush_process_finished']) && !empty($result[0]['vde_file']) && file_exists($result[0]['vde_file'])) {
        $this->logger()->success(dt('Data export saved to !output_file', ['!output_file' => $result[0]['vde_file']]));

        // Now optionally move the file.
        if (!empty($options['output-file'])) {
          $output_directory = $this->fileSystem->dirname($options['output-file']);
          $this->fileSystem->prepareDirectory($output_directory, FileSystemInterface::CREATE_DIRECTORY);

          // Now make a copy of the file to the desired location.
          if (version_compare(\Drupal::VERSION, '10.3', '>=')) {
            $this->fileSystem->copy($result[0]['vde_file'], $options['output-file'], $options['force'] ? FileExists::Replace : FileExists::Error);
          }
          else {
            // @phpstan-ignore-next-line
            $this->fileSystem->copy($result[0]['vde_file'], $options['output-file'], $options['force'] ? FileSystemInterface::EXISTS_REPLACE : FileSystemInterface::EXISTS_ERROR);
          }

          $this->logger()->success(dt('Data export additionally saved to @output_file', ['@output_file' => realpath($options['output-file'])]));
        }
        else {
          $this->output()->write(file_get_contents($result[0]['vde_file']));
        }
      }
      else {
        $this->logger()->error(dt('Unable to export views data. Please check site logs.'));
      }
    }
    else {
      $this->logger()->error(dt('Unexpected result type from data export.'));
    }
  }

}

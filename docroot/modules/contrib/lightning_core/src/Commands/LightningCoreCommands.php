<?php

namespace Drupal\lightning_core\Commands;

use Composer\InstalledVersions;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Update\UpdateRegistry;
use Drupal\lightning_core\UpdateManager;
use Drush\Commands\DrushCommands;
use Drush\Drush;

/**
 * Exposes Drush commands provided by Lightning Core.
 */
class LightningCoreCommands extends DrushCommands {

  /**
   * The update manager service.
   *
   * @var \Drupal\lightning_core\UpdateManager
   */
  protected $updateManager;

  /**
   * The Drupal application root.
   *
   * @var string
   */
  private $root;

  /**
   * The post-update registry service.
   *
   * @var \Drupal\Core\Update\UpdateRegistry
   */
  private $postUpdateRegistry;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * The default constraints for all of Lightning's unbundled dependencies.
   *
   * These constraints are used for packages which aren't installed or listed in
   * the project-level `composer.json`.
   *
   * @var string[]
   */
  private static $constraints = [
    // Require the obsolete Acquia Telemetry module so it can be automatically
    // uninstalled by lightning_core_update_9002().
    'drupal/acquia_telemetry-acquia_telemetry' => '1.0-alpha6',
    // Lightning Core's unbundled contrib dependencies.
    'drupal/contact_storage' => '^1',
    'drupal/metatag' => '^1.13',
    'drupal/pathauto' => '^1.8',
    'drupal/redirect' => '^1.5',
    'drupal/search_api' => '^1.16',
    'drupal/token' => '^1.7',
    // Lightning API's unbundled contrib dependencies.
    'drupal/consumers' => '^1.14',
    'drupal/openapi_jsonapi' => '^3',
    'drupal/openapi_rest' => '^2.0-rc1',
    'drupal/openapi_ui_redoc' => '^1',
    'drupal/openapi_ui_swagger' => '^1',
    'drupal/simple_oauth' => '^5.2',
    // Lightning Layout's unbundled contrib dependencies.
    'cweagans/composer-patches' => '^1.7',
    'drupal/bg_image_formatter' => '^1.10',
    'drupal/ctools' => '^3.6',
    'drupal/entity_block' => '^1',
    'drupal/entity_browser_block' => '^1',
    'drupal/layout_builder_restrictions' => '^2.14',
    'drupal/layout_builder_st' => '^1.0-alpha2',
    'drupal/layout_builder_styles' => '^2',
    'drupal/layout_library' => '^1.0-beta1',
    'drupal/panelizer' => '^4.1 || ^5',
    'drupal/panels' => '^4.6',
    'drupal/simple_gmap' => '^3',
    // Lightning Media's unbundled contrib dependencies.
    'drupal/ckeditor' => '^1',
    'drupal/dropzonejs' => '^2.1',
    'drupal/entity_browser' => '^2.3',
    'drupal/entity_embed' => '^1',
    'drupal/image_widget_crop' => '^2.1',
    'drupal/inline_entity_form' => '^1.0-rc7',
    'drupal/media_entity_instagram' => '^3',
    'drupal/media_entity_twitter' => '^2.5',
    'drupal/slick_entityreference' => '^2',
    'drupal/video_embed_field' => '^2',
    'drupal/views_infinite_scroll' => '^1.6 || ^2',
    'enyo/dropzone' => '^5.7.4',
    'oomphinc/composer-installers-extender' => '^1.1 || ^2',
    'vardot/blazy' => '^1.8',
    // Lightning Workflow's unbundled contrib dependencies.
    'drupal/autosave_form' => '^1.2',
    'drupal/conflict' => '^2.0-alpha2',
    'drupal/diff' => '^1',
    'drupal/moderation_dashboard' => '^1',
    'drupal/moderation_sidebar' => '^1.2',
  ];

  /**
   * LightningCoreCommands constructor.
   *
   * @param \Drupal\lightning_core\UpdateManager $update_manager
   *   The update manager service.
   * @param string $root
   *   The Drupal application root.
   * @param \Drupal\Core\Update\UpdateRegistry $post_update_registry
   *   The post-update registry service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(UpdateManager $update_manager, string $root, UpdateRegistry $post_update_registry, ModuleHandlerInterface $module_handler) {
    $this->updateManager = $update_manager;
    $this->root = $root;
    $this->postUpdateRegistry = $post_update_registry;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Checks if there are any Lightning database updates pending.
   *
   * @return bool
   *   TRUE if any database updates are pending, FALSE otherwise.
   */
  private function updatesPending(): bool {
    require_once $this->root . '/core/includes/update.inc';
    $updates = update_get_update_list();

    foreach ($this->moduleHandler->getModuleList() as $module) {
      $name = $module->getName();
      if (str_starts_with($name, 'lightning_') && array_key_exists($name, $updates)) {
        return TRUE;
      }
    }
    return (bool) preg_grep('/^lightning_/', $this->postUpdateRegistry->getPendingUpdateFunctions());
  }

  /**
   * Executes Lightning configuration updates from a specific version.
   *
   * @command update:lightning
   *
   * @usage update:lightning
   *   Runs all available configuration updates, and optionally moves all the
   *   Lightning components' dependenices to the project's `composer.json`.
   *
   * @option upgrade Move Lightning components' dependencies to the project's composer.json.
   * @option file The absolute path to the project's composer.json.
   * @option commands-only Display the Composer commands to run, instead of modifying composer.json.
   */
  public function update(array $options = ['upgrade' => FALSE, 'file' => NULL, 'commands-only' => FALSE]) {
    $io = $this->io();
    $io->warning("This command is removed from Lightning Core 6. See https://www.drupal.org/node/3303698 for more information.");

    $this->updateManager->executeAllInConsole($io);

    // If no upgrade was requested, there's nothing else to do.
    if (empty($options['upgrade'])) {
      return;
    }
    if ($this->updatesPending()) {
      throw new \RuntimeException("Database updates have not yet been run. Please run them and try again.");
    }

    if ($options['file']) {
      $file = $options['file'];
    }
    else {
      $file = Drush::bootstrapManager()->getComposerRoot() . DIRECTORY_SEPARATOR . 'composer.json';
    }
    if (!file_exists($file)) {
      throw new \RuntimeException("The composer.json file for the project ($file) was not found. Please specify its absolute path with the --file option.");
    }

    // Fall back to read-only mode if composer.json isn't writable.
    if (empty($options['commands-only'])) {
      $io->block("Moving Lightning components' dependencies to $file...");

      if (!is_writable($file)) {
        $options['commands-only'] = TRUE;
        $io->warning("$file is not writable and will not be modified. The equivalent Composer commands will be displayed instead.");
      }
    }

    $data = file_get_contents($file);
    $data = json_decode($data, JSON_THROW_ON_ERROR);

    // Conditional constraints are added in a way that tries to minimize
    // unexpected changes when `composer update` is run:
    // - If there is already a constraint for a particular package, we leave it
    //   alone, even if it's not pinned.
    // - Otherwise, if the package is installed, we pin the installed version.
    // - Otherwise, we add the constraint from self::$constraints.
    $conditional_constraints = self::getConstraints([
      'drupal/acquia_telemetry-acquia_telemetry',
      'drupal/contact_storage',
      'drupal/metatag',
      'drupal/pathauto',
      'drupal/redirect',
      'drupal/search_api',
      'drupal/token',
    ]);
    // Add a direct dependency on Lightning Core so that it can be removed
    // when the other components are uncoupled from it.
    $unconditional_constraints = [
      'drupal/lightning_core' => '^6',
    ];
    $added_plugins = [];

    if (array_key_exists('drupal/lightning_api', $data['require'])) {
      $conditional_constraints += self::getConstraints([
        'drupal/consumers',
        'drupal/openapi_jsonapi',
        'drupal/openapi_rest',
        'drupal/openapi_ui_redoc',
        'drupal/openapi_ui_swagger',
        'drupal/simple_oauth',
      ]);
      // By default, only allow patch-level updates to Lightning API, which will
      // allow the 5.1.0 release to drop its hard dependency on Lightning Core.
      $unconditional_constraints['drupal/lightning_api'] = '~5.0.0';
    }
    if (array_key_exists('drupal/lightning_layout', $data['require'])) {
      $conditional_constraints += self::getConstraints([
        'cweagans/composer-patches',
        'drupal/bg_image_formatter',
        'drupal/ctools',
        'drupal/entity_block',
        'drupal/entity_browser_block',
        'drupal/layout_builder_restrictions',
        'drupal/layout_builder_st',
        'drupal/layout_builder_styles',
        'drupal/layout_library',
        'drupal/panelizer',
        'drupal/panels',
        'drupal/simple_gmap',
      ]);
      // By default, only allow patch-level updates to Lightning Layout, which
      // will allow the 3.1.0 release to drop its hard dependency on Lightning
      // Core.
      $unconditional_constraints['drupal/lightning_layout'] = '~3.0.0';

      $patches = [
        'drupal/panelizer' => [
          '2778565 - Multilingual support for Panelizer' => "https://www.drupal.org/files/issues/2020-03-23/2778565-47.patch",
        ],
        'drupal/panels' => [
          '2878684 - Use String.match to correlate regions when switching Layouts in Panels IPE' => "https://www.drupal.org/files/issues/panels-ipe-2878684-3.patch",
        ],
      ];
      $added_plugins[] = 'cweagans/composer-patches';
    }
    if (array_key_exists('drupal/lightning_media', $data['require'])) {
      // Add all of Lightning Media's unbundled dependencies, including ones
      // which were flat-out removed, just to help as many sites as possible
      // not break.
      $conditional_constraints += self::getConstraints([
        'drupal/ckeditor',
        'drupal/dropzonejs',
        'drupal/entity_browser',
        'drupal/entity_embed',
        'drupal/image_widget_crop',
        'drupal/inline_entity_form',
        'drupal/media_entity_instagram',
        'drupal/media_entity_twitter',
        'drupal/slick_entityreference',
        'drupal/video_embed_field',
        'drupal/views_infinite_scroll',
        'enyo/dropzone',
        'vardot/blazy',
      ]);
      // By default, only allow patch-level updates to Lightning Media, which
      // will allow the 5.1.0 release to drop its hard dependency on Lightning
      // Core.
      $unconditional_constraints['drupal/lightning_media'] = '~5.0.0';

      // If the site is configured to allow arbitrary package types, ensure the
      // relevant plugin is required.
      if (isset($data['extra']['installer-types'])) {
        $added_plugins[] = 'oomphinc/composer-installers-extender';
        $conditional_constraints += self::getConstraints(array_slice($added_plugins, -1));
      }

      // Ensure the Dropzone JavaScript library is installed where Drupal can
      // find it.
      $libraries_path = self::getLibrariesPath($data['extra']);
      $libraries = $data['extra']['installer-paths'][$libraries_path] ?? ['type:drupal-library'];
      $libraries[] = 'enyo/dropzone';
    }
    if (array_key_exists('drupal/lightning_workflow', $data['require'])) {
      $conditional_constraints += self::getConstraints([
        'drupal/autosave_form',
        'drupal/conflict',
        'drupal/diff',
        'drupal/moderation_dashboard',
        'drupal/moderation_sidebar',
      ]);
      // By default, only allow patch-level updates to Lightning Workflow, which
      // will allow the 4.1.0 release to drop its hard dependency on Lightning
      // Core.
      $unconditional_constraints['drupal/lightning_workflow'] = '~4.0.0';
    }

    // If we're not going to modify `composer.json`, use the information we've
    // gathered so far to create a series of Composer commands and output them
    // to the user.
    if ($options['commands-only']) {
      $commands = [];
      $dir = dirname($file);

      if (isset($patches)) {
        $commands[] = sprintf('composer config extra.patches --working-dir="%s" --merge --json \'%s\'', $dir, json_encode($patches, JSON_UNESCAPED_SLASHES));
      }
      if (isset($libraries_path, $libraries)) {
        $commands[] = sprintf('composer config extra.installer-paths --working-dir="%s" --merge --json \'%s\'', $dir, json_encode([$libraries_path => $libraries], JSON_UNESCAPED_SLASHES));
      }

      // Generate the `composer require` command.
      $constraints = array_diff_key($conditional_constraints, $data['require']);
      $constraints = array_merge($constraints, $unconditional_constraints);

      $command = sprintf('composer require --no-update --working-dir="%s"', $dir);
      foreach ($constraints as $name => $constraint) {
        $command .= " '$name:$constraint'";
      }
      $commands[] = $command;
      $commands[] = "composer update 'drupal/lightning_*' --with-all-dependencies";

      $io->success("Run the following commands to add the Lightning components' dependencies to $file and reinstall them:");
      $io->listing($commands);
    }
    else {
      if (isset($patches)) {
        $data['extra'] = NestedArray::mergeDeep($data['extra'], ['patches' => $patches]);
      }
      if (isset($libraries_path, $libraries)) {
        $data['extra']['installer-paths'][$libraries_path] = $libraries;
      }
      $data['require'] = array_merge($data['require'], $unconditional_constraints) + $conditional_constraints;

      file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
      $io->success("The Lightning components' dependencies were added to $file. Please run `composer update 'drupal/lightning_*' --with-all-dependencies` to reinstall them.");
    }

    if ($added_plugins) {
      $io->note('If you are using Composer 2.2 or later, you may be prompted to trust and enable the following plugins:');
      $io->listing($added_plugins);
    }
  }

  /**
   * Returns the path where front-end libraries should be installed.
   *
   * @param array $extra
   *   The `extra` section of `composer.json`.
   *
   * @return string
   *   The path where front-end libraries should be installed, as listed in
   *   the `extra.installer-paths` section of `composer.json`.
   */
  private static function getLibrariesPath(array $extra): string {
    // First, see if the path for the drupal-library package type is already
    // defined.
    if (isset($extra['installer-paths'])) {
      foreach ($extra['installer-paths'] as $path => $criteria) {
        if (in_array('type:drupal-library', $criteria, TRUE)) {
          return $path;
        }
      }
    }

    // If not, use the scaffold location to compute `[web-root]/libraries`. If
    // the web root isn't defined either, assume that it is the directory that
    // contains `composer.json` (which is the default configuration of the
    // scaffold plugin).
    $path = $extra['drupal-scaffold']['locations']['web-root'] ?? '.';
    $path = rtrim($path, '/');
    return $path . '/libraries/{$name}';
  }

  /**
   * Transforms a list of package names into a name => constraint map.
   *
   * @param string[] $names
   *   A set of package names.
   *
   * @return array
   *   The version constraints for the given packages, keyed by package name.
   */
  private static function getConstraints(array $names): array {
    $constraints = [];

    foreach ($names as $name) {
      if (InstalledVersions::isInstalled($name)) {
        $constraints[$name] = InstalledVersions::getPrettyVersion($name);
      }
      else {
        $constraints[$name] = self::$constraints[$name];
      }
    }
    return $constraints;
  }

}

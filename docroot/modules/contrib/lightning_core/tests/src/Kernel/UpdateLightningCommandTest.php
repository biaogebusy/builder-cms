<?php

namespace Drupal\Tests\lightning_core\Kernel;

use Prophecy\PhpUnit\ProphecyTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_core\Commands\LightningCoreCommands;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @coversDefaultClass \Drupal\lightning_core\Commands\LightningCoreCommands
 *
 * @group lightning_core
 */
class UpdateLightningCommandTest extends KernelTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lightning_core', 'user'];

  /**
   * @covers ::update
   */
  public function testUpdateLightning(): void {
    $file = 'public://composer.json';
    $data = [
      'require' => [
        'drupal/entity_embed' => '^1.1',
        'drupal/lightning_media' => '*',
        'drupal/metatag' => '^1.14',
        'drupal/lightning_api' => '*',
        'drupal/simple_oauth' => '^5.3',
        'cweagans/composer-patches' => '^1.6',
        'drupal/lightning_layout' => '*',
        'drupal/diff' => '^1.1',
        'drupal/lightning_workflow' => '*',
      ],
      'extra' => [
        'installer-types' => ['npm-asset'],
      ],
    ];
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES));

    // Prevent optional updates from running, by simulating that we're already
    // fully up to date.
    $this->config('lightning_core.versions')
      ->set('lightning_core', '3.6.0')
      ->save();

    // Mock the I/O handler to ensure that the command always outputs the
    // expected messages.
    $io = $this->prophesize(SymfonyStyle::class);
    // Each time we run the command, it should mention that it's deprecated.
    $io->warning("This command is removed from Lightning Core 6. See https://www.drupal.org/node/3303698 for more information.")
      ->shouldBeCalledTimes(2);
    $io->text('There are no updates available.')
      ->shouldBeCalledTimes(2);
    $io->block("Moving Lightning components' dependencies to $file...")
      ->shouldBeCalledOnce();
    $io->success("The Lightning components' dependencies were added to $file. Please run `composer update 'drupal/lightning_*' --with-all-dependencies` to reinstall them.")
      ->shouldBeCalledOnce();
    $io->note('If you are using Composer 2.2 or later, you may be prompted to trust and enable the following plugins:')
      ->shouldBeCalledOnce();
    $io->listing(['cweagans/composer-patches', 'oomphinc/composer-installers-extender'])
      ->shouldBeCalledOnce();

    $commands = new LightningCoreCommands(
      $this->container->get('lightning.update_manager'),
      $this->getDrupalRoot(),
      $this->container->get('update.post_update_registry'),
      $this->container->get('module_handler')
    );
    $property = new \ReflectionProperty($commands, 'io');
    $property->setAccessible(TRUE);
    $property->setValue($commands, $io->reveal());
    $commands->update(['upgrade' => TRUE, 'file' => $file, 'commands-only' => FALSE]);

    $data = file_get_contents($file);
    $data = json_decode($data, JSON_THROW_ON_ERROR);

    $expected_constraints = [
      // Lightning Core dependencies.
      'drupal/acquia_telemetry-acquia_telemetry' => '*',
      'drupal/contact_storage' => '*',
      'drupal/pathauto' => '*',
      'drupal/redirect' => '*',
      'drupal/search_api' => '*',
      // Lightning Media dependencies.
      'drupal/ckeditor' => '*',
      'drupal/dropzonejs' => '*',
      'drupal/entity_browser' => '*',
      'drupal/image_widget_crop' => '*',
      'drupal/inline_entity_form' => '*',
      'drupal/media_entity_instagram' => '*',
      'drupal/media_entity_twitter' => '*',
      'drupal/slick_entityreference' => '*',
      'drupal/views_infinite_scroll' => '*',
      'enyo/dropzone' => '*',
      'vardot/blazy' => '*',
      // The presence of the `extra.installer-types` configuration should cause
      // this plugin to be required.
      'oomphinc/composer-installers-extender' => '*',
      // Pre-existing constraints.
      'drupal/entity_embed' => '^1.1',
      'drupal/metatag' => '^1.14',
      // Lightning API dependencies.
      'drupal/consumers' => '*',
      'drupal/openapi_jsonapi' => '*',
      'drupal/openapi_rest' => '*',
      'drupal/openapi_ui_redoc' => '*',
      'drupal/openapi_ui_swagger' => '*',
      // A pre-existing constraint.
      'drupal/simple_oauth' => '^5.3',
      // Lightning Layout dependencies.
      'drupal/bg_image_formatter' => '*',
      'drupal/ctools' => '*',
      'drupal/entity_block' => '*',
      'drupal/entity_browser_block' => '*',
      'drupal/layout_builder_restrictions' => '*',
      'drupal/layout_builder_st' => '*',
      'drupal/layout_builder_styles' => '*',
      'drupal/layout_library' => '*',
      'drupal/panelizer' => '*',
      'drupal/panels' => '*',
      'drupal/simple_gmap' => '*',
      // A pre-existing constraint.
      'cweagans/composer-patches' => '^1.6',
      // Lightning Workflow dependencies.
      'drupal/autosave_form' => '*',
      'drupal/conflict' => '*',
      'drupal/moderation_dashboard' => '*',
      'drupal/moderation_sidebar' => '*',
      // A pre-existing constraint.
      'drupal/diff' => '^1.1',
      // The components should be constrained to patch-level releases, except
      // Lightning Core.
      'drupal/lightning_core' => '^6',
      'drupal/lightning_api' => '~5.0.0',
      'drupal/lightning_layout' => '~3.0.0',
      'drupal/lightning_media' => '~5.0.0',
      'drupal/lightning_workflow' => '~4.0.0',
    ];
    foreach ($expected_constraints as $package => $constraint) {
      // If the expected constraint is `*`, we don't actually care what it
      // really resolved to (which may vary depending on what packages are
      // installed in the current testing environment) -- we just care that it
      // resolved to *something*.
      if ($constraint === '*') {
        $this->assertNotEmpty($data['require'][$package]);
        $this->assertNotSame('*', $data['require'][$package]);
      }
      else {
        $this->assertSame($constraint, $data['require'][$package]);
      }
    }

    // Lightning Layout's patches should be added.
    $this->assertIsArray($data['extra']['patches']['drupal/panelizer']);
    $this->assertIsArray($data['extra']['patches']['drupal/panels']);

    // The JavaScript libraries' paths should be configured correctly.
    $this->assertSame(['type:drupal-library', 'enyo/dropzone'], $data['extra']['installer-paths']['./libraries/{$name}']);

    $this->expectExceptionMessage("The composer.json file for the project (garbage) was not found. Please specify its absolute path with the --file option.");
    $commands->update(['upgrade' => TRUE, 'file' => 'garbage']);
  }

}

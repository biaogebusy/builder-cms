<?php

declare(strict_types=1);

namespace Drupal\Tests\config_ignore\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\config_filter\Kernel\ConfigStorageTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test what config is used for configuring config ignore.
 */
#[Group('config_ignore')]
class IgnoreConfigSourceTest extends KernelTestBase {

  use ConfigStorageTestTrait;
  use KernelTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'config',
    'config_test',
    'config_ignore',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // We install the system and config_test config so that there is something
    // to modify and ignore for the test.
    $this->installConfig(['system', 'config_test', 'config_ignore']);
  }

  /**
   * Test the different source behavior.
   *
   * @param string|null $source
   *   The source mode.
   * @param array $active
   *   The changes to the active storage.
   * @param array $sync
   *   The changes to the sync storage.
   * @param array $import
   *   The expected import storage changes.
   * @param array $export
   *   The expected export storage changes.
   */
  #[DataProvider('sourceSettingsProvider')]
  public function testSourceSettings(?string $source, array $active, array $sync, array $import, array $export): void {
    // Set the settings we test.
    $settings = Settings::getAll();
    unset($settings['config_ignore_storage']);
    if ($source !== NULL) {
      $settings['config_ignore_storage'] = $source;
    }
    new Settings($settings);

    // Set up the storages to compare.
    $storages = $this->setUpMultipleStorages(
      ['' => $active],
      ['' => $sync],
      [
        'import' => ['' => $import],
        'export' => ['' => $export],
      ]
    );

    static::assertStorageEquals($storages['import'], $this->getImportStorage());
    static::assertStorageEquals($storages['export'], $this->getExportStorage());
  }

  /**
   * The data provider for testing the source behavior.
   *
   * @return \Generator
   *   The test cases.
   */
  public static function sourceSettingsProvider(): \Generator {
    // This does not do anything but document the provider more and serves as
    // a test of the test.
    yield 'empty' => [
      // The value for $settings['config_ignore_source'].
      'source' => NULL,
      // Modifications to the active config, keyed by the config name.
      'active' => [],
      // Modifications to the sync storage, keyed by the config name.
      'sync' => [],
      // Modifications to the expected import, keyed by the config name.
      'import' => [],
      // Modifications to the expected import, keyed by the config name.
      'export' => [],
    ];

    $ignore_test_system = [
      'ignored_config_entities' => [
        'config_test.system',
      ],
    ];

    // phpcs:disable Drupal.Files.LineLength.TooLong
    // This is the default behavior with a simple case.
    yield 'default simple' => [
      'source' => NULL,
      'active' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      'sync' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import all changes are reverted, the sync storage didn't ignore anything.
      'import' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On export only the config ignore settings are exported, foo is ignored.
      'export' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    // This is the default behavior with a simple case where the ignore config is already exported.
    yield 'default' => [
      'source' => NULL,
      'active' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      // We assume the ignore config was already exported.
      'sync' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import the foo is ignored, so it is the same as the active.
      'import' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      // On export only the config ignore settings are exported, foo is ignored.
      'export' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    // Active means the currently active config is used for both import and export.
    // This corresponds to the behavior in 2.x which is available again.
    yield 'active' => [
      'source' => 'active',
      'active' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      'sync' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import foo is ignored but config ignore is reset, so next time it will not ignore foo.
      // This demonstrates why in 2.x we switched to default to the config from the sync storage for import.
      'import' => [
        'config_test.system' => ['foo' => 'active'],
      ],
      // On export only the config ignore settings are exported, foo is ignored.
      'export' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    // Sync means the config from the sync directory is used for both import and export
    // (subject to changes from other subscribers).
    yield 'sync' => [
      'source' => 'sync',
      'active' => [
        'config_test.system' => ['foo' => 'active'],
      ],
      'sync' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
      'import' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      // On the next export it would be not ignored anymore.
      'export' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    // This was the default before fixing the bug with ignoring config ignore.
    yield 'source' => [
      'source' => 'source',
      'active' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      'sync' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
      // The import resets everything, the sync storage didn't ignore anything.
      'import' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
      // The export will update the ignore config but ignore foo.
      // After exporting the sync storage will be set up like in the "default" test case so
      // on import foo will be ignored as expected by most people. See the next example.
      'export' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    // Like the default with the exported ignore config.
    yield 'source exported' => [
      'source' => 'source',
      'active' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      // We assume the ignore config was already exported.
      'sync' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import the foo is ignored, so it is the same as the active.
      'import' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      // On export only the config ignore settings are exported, foo is ignored.
      'export' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    // This is the symmetrical opposite of source.
    yield 'target' => [
      'source' => 'target',
      'active' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      'sync' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import foo is ignored but config ignore is reset, so next time it will not ignore foo.
      'import' => [
        'config_test.system' => ['foo' => 'active'],
      ],
      // The export will update the ignore config but not ignore foo.
      'export' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
    ];

    // What if the config is ignored in the sync storage in target mode.
    yield 'target inverse' => [
      'source' => 'target',
      'active' => [
        'config_test.system' => ['foo' => 'active'],
      ],
      // The config is ignored only in the sync storage.
      'sync' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import foo is reset, but the ignore config gets imported.
      'import' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
      // The export will update the ignore config but ignore foo.
      'export' => [
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    $ignore_with_ignore = [
      'ignored_config_entities' => [
        'config_ignore.settings',
        'config_test.system',
      ],
    ];
    // Now if you ignore the config ignore settings it uses the target config.
    yield 'ignored ignore' => [
      'source' => NULL,
      'active' => [
        'config_ignore.settings' => $ignore_with_ignore,
        'config_test.system' => ['foo' => 'active'],
      ],
      // We assume the ignore config was already exported before without the config ignore.
      'sync' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import the foo and config ignore are ignored, so they are the same as the active config.
      'import' => [
        'config_ignore.settings' => $ignore_with_ignore,
        'config_test.system' => ['foo' => 'active'],
      ],
      // On export foo is ignored and so is config ignore because the source is used.
      'export' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    // Ignoring the config ignore config works the other way too.
    yield 'ignored in sync' => [
      'source' => NULL,
      'active' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      // Now the sync storage ignores the config ignore config.
      'sync' => [
        'config_ignore.settings' => $ignore_with_ignore,
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import the foo is ignored but the ignore config gets updated.
      'import' => [
        'config_ignore.settings' => $ignore_test_system,
        'config_test.system' => ['foo' => 'active'],
      ],
      // On export both are ignored.
      'export' => [
        'config_ignore.settings' => $ignore_with_ignore,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    $ignore_test = [
      'ignored_config_entities' => [
        'config_test.system',
      ],
    ];
    $ignore_ignore = [
      'ignored_config_entities' => [
        'config_ignore.settings',
      ],
    ];
    // Merging merges both source and target config so both are ignored.
    yield 'merging' => [
      'source' => 'merge',
      'active' => [
        'config_ignore.settings' => $ignore_test,
        'config_test.system' => ['foo' => 'active'],
      ],
      // Set the sync storage up differently.
      'sync' => [
        'config_ignore.settings' => $ignore_ignore,
        'config_test.system' => ['foo' => 'sync'],
      ],
      // On import everything is ignored.
      'import' => [
        'config_ignore.settings' => $ignore_test,
        'config_test.system' => ['foo' => 'active'],
      ],
      // On export everything is also ignored.
      'export' => [
        'config_ignore.settings' => $ignore_ignore,
        'config_test.system' => ['foo' => 'sync'],
      ],
    ];

    $foo = [
      'ignored_config_entities' => [
        'config_test.system:foo',
      ],
    ];

    $baz = [
      'ignored_config_entities' => [
        'config_test.system:baz',
      ],
    ];

    yield 'more complex default' => [
      'source' => NULL,
      'active' => [
        'config_ignore.settings' => $foo,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'active',
        ],
      ],
      // Set the sync storage up differently.
      'sync' => [
        'config_ignore.settings' => $baz,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'sync',
        ],
      ],
      // On import only the baz key is ignored (from sync)
      'import' => [
        'config_ignore.settings' => $baz,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'active',
        ],
      ],
      // On export only the foo key is ignored (from active).
      'export' => [
        'config_ignore.settings' => $foo,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'active',
        ],
      ],
    ];

    $fooIgnore = [
      'ignored_config_entities' => [
        'config_ignore.settings',
        'config_test.system:foo',
      ],
    ];

    $bazIgnore = [
      'ignored_config_entities' => [
        'config_ignore.settings',
        'config_test.system:baz',
      ],
    ];

    yield 'more complex default ignore' => [
      'source' => NULL,
      'active' => [
        'config_ignore.settings' => $fooIgnore,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'active',
        ],
      ],
      // Set the sync storage up differently.
      'sync' => [
        'config_ignore.settings' => $bazIgnore,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'sync',
        ],
      ],
      // On import only ignore and the foo key is ignored (from active)
      'import' => [
        'config_ignore.settings' => $fooIgnore,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'sync',
        ],
      ],
      // On export only ignore and the baz key is ignored (from sync).
      'export' => [
        'config_ignore.settings' => $bazIgnore,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'sync',
        ],
      ],
    ];

    yield 'more complex merge' => [
      'source' => 'merge',
      'active' => [
        'config_ignore.settings' => $foo,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'active',
        ],
      ],
      // Set the sync storage up differently.
      'sync' => [
        'config_ignore.settings' => $baz,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'sync',
        ],
      ],
      // Again everything is ignored because the settings get merged.
      'import' => [
        'config_ignore.settings' => $baz,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'active',
        ],
      ],
      // Again everything is ignored because the settings get merged.
      'export' => [
        'config_ignore.settings' => $foo,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'sync',
        ],
      ],
    ];

    yield 'more complex merge ignore' => [
      'source' => 'merge',
      'active' => [
        'config_ignore.settings' => $fooIgnore,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'active',
        ],
      ],
      // Set the sync storage up differently.
      'sync' => [
        'config_ignore.settings' => $bazIgnore,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'sync',
        ],
      ],
      // Again everything is ignored because the settings get merged.
      'import' => [
        'config_ignore.settings' => $fooIgnore,
        'config_test.system' => [
          'foo' => 'active',
          'baz' => 'active',
        ],
      ],
      // Again everything is ignored because the settings get merged.
      'export' => [
        'config_ignore.settings' => $bazIgnore,
        'config_test.system' => [
          'foo' => 'sync',
          'baz' => 'sync',
        ],
      ],
    ];

  }

}

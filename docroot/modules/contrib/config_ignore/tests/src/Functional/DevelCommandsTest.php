<?php

namespace Drupal\Tests\config_ignore\Functional;

use Drupal\config_ignore\Drush\Listeners\ConfigIgnoreListener;
use Drupal\Tests\BrowserTestBase;
use Drush\Commands\config\ConfigExportCommands;
use Drush\Commands\config\ConfigImportCommands;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test class for the Config Ignore drush commands.
 *
 * Note: Drush must be installed. Add it to your require-dev in composer.json.
 */
#[Group('config_ignore')]
#[CoversClass(ConfigExportCommands::class)]
#[CoversClass(ConfigIgnoreListener::class)]
class DevelCommandsTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_ignore'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests drush commands.
   *
   * @dataProvider commandsProvider
   */
  #[DataProvider('commandsProvider')]
  public function testCommands(string $command): void {
    try {
      $this->drush($command, [], ['deactivate-config-ignore' => TRUE]);
    }
    catch (\Exception) {
      // Import operation throws an error because source is empty.
      // But that doesn't matter to us, the only thing that matters
      // is whether the new option worked.
    }
    $messages = $this->getErrorOutput();
    $this->assertStringContainsString('Deactivating config ignore.', $messages, 'Config ignore must be deactivated');
  }

  /**
   * Provides the test cases.
   *
   * @return \Generator
   *   The test case.
   */
  public static function commandsProvider(): \Generator {
    yield [ConfigExportCommands::EXPORT];
    yield [ConfigImportCommands::IMPORT];
  }

}

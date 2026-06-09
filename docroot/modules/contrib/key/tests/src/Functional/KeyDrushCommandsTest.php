<?php

namespace Drupal\Tests\key\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * @coversDefaultClass \Drupal\key\Commands\KeyCommands
 * @group key
 */
class KeyDrushCommandsTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the save and list Drush commands.
   */
  public function testSaveListCommands() {
    $options = [
      'label' => 'Secret password',
      'key-type' => 'authentication',
      'key-provider' => 'config',
      'key-input' => 'text_field',
    ];
    $this->drush('key:save', ['secret_password', 'pA$$w0rd'], $options);
    $this->drush('key:save', ['secret_password', 'pA$$w0rd2'], $options);
    $this->assertStringContainsString('The following key will be overwritten: secret_password', $this->getOutput());
    $this->assertStringContainsString('Be extremely careful when overwriting a key! It may result in losing access to a service or making encrypted data unreadable.', $this->getErrorOutput());
    $this->drush('key:list');
    $this->assertStringContainsString('secret_password   Secret password   Authentication', $this->getOutput());
  }

}

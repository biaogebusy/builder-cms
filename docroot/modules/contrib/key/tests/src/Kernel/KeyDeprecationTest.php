<?php

namespace Drupal\Tests\key\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;

/**
 * Tests deprecations for Key module.
 *
 * @group key
 * @group legacy
 */
class KeyDeprecationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'key_test'];

  /**
   * @covers \Drupal\key\Annotation\KeyProvider
   * @covers \Drupal\key\Plugin\KeyPluginManager::processDefinition
   * @covers \Drupal\key\KeyRepository::getKeysByStorageMethod
   * @covers \Drupal\key\KeyRepository::getKeyNamesAsOptions
   */
  public function testStorageMethodDeprecation(): void {
    $this->expectDeprecation("The key provider 'storage_method' definition entry is deprecated in key:1.18.0 and is removed from key:2.0.0. Use the 'tags' definition entry instead. See https://www.drupal.org/node/3364701");
    $plugin = $this->container->get('plugin.manager.key.key_provider')->createInstance('deprecated_defintion_entries');
    $this->assertSame(['whatever'], $plugin->getPluginDefinition()['tags']);

    $key_repository = $this->container->get('key.repository');
    Key::create(['id' => 'test', 'key_provider' => 'file'])->save();

    $this->expectDeprecation('Drupal\key\KeyRepository::getKeysByStorageMethod() is deprecated in key:1.18.0 and is removed from key:2.0.0. Use self::getKeysByTags() instead. See https://www.drupal.org/node/3364701');
    $keys = $key_repository->getKeysByStorageMethod('file');
    $this->assertCount(1, $keys);
    $this->assertSame('file', reset($keys)->getKeyProvider()->getPluginId());

    $this->expectDeprecation("Passing 'storage_method' as filter to Drupal\key\KeyRepository::getKeyNamesAsOptions() is deprecated in key:1.18.0 and is removed from key:2.0.0. Use the 'tags' filter instead. See https://www.drupal.org/node/3364701");
    $names = $key_repository->getKeyNamesAsOptions(['storage_method' => 'file']);
    $this->assertCount(1, $names);
    $this->assertArrayHasKey('test', $names);
  }

}

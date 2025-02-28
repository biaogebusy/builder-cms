<?php

namespace Drupal\Tests\lightning_core\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;

/**
 * @group lightning_core
 */
class DefaultUserImageTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
  }

  /**
   * Tests that the default avatar is set.
   */
  public function testDefaultUserImage() {
    \Drupal::service('module_installer')->install([
      'lightning_core',
      'image',
    ]);

    $this->assertFileExists('public://default-avatar.png');
    $config = FieldConfig::load('user.user.user_picture');
    $setting = $config->getSetting('default_image');
    $this->assertNotEmpty($setting['uuid']);
    $this->assertSame('A generic silhouette of a person.', $setting['alt']);
    $this->assertSame('', $setting['title']);
    $this->assertSame(140, $setting['width']);
    $this->assertSame(140, $setting['height']);
  }

  /**
   * Tests that the default avatar is not set if the image already exists.
   */
  public function testAlreadyExists() {
    file_put_contents('public://default-avatar.png', '');

    \Drupal::service('module_installer')->install([
      'lightning_core',
      'image',
    ]);

    $this->assertEmpty(File::loadMultiple());
    $config = FieldConfig::load('user.user.user_picture');
    $setting = $config->getSetting('default_image');
    $this->assertNull($setting['uuid']);
    $this->assertSame('', $setting['alt']);
    $this->assertSame('', $setting['title']);
    $this->assertNull($setting['width']);
    $this->assertNull($setting['height']);
  }

}

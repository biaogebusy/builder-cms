<?php

namespace Drupal\Tests\lightning_core\Kernel;

use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * @group lightning_core
 */
class CompactUserRenderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'lightning_core',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');

    $this->installEntitySchema('file');
    $this->installEntitySchema('user');

    $this->installConfig('image');
    $this->installConfig('user');
    $this->installConfig('lightning_core');
  }

  public function test() {
    $picture = File::create([
      'uri' => $this->getRandomGenerator()->image('public://martok.png', '320x240', '320x240'),
    ]);
    $this->assertFileExists($picture->getFileUri());
    $this->assertSame(SAVED_NEW, $picture->save());

    $user = User::create([
      'name' => 'General Martok',
      'user_picture' => $picture->id(),
    ]);
    $this->assertSame(SAVED_NEW, $user->save());

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('user')
      ->view($user, 'compact');

    // hook_ENTITY_TYPE_view() is normally invoked during rendering, which means
    // we need to assert things in the final rendered output.
    $output = (string) $this->container->get('renderer')->renderRoot($build);

    $this->assertStringContainsString($user->getDisplayName(), $output);
    $this->assertStringContainsString($picture->getFilename(), $output);
    $this->assertStringContainsString($user->toUrl()->toString(), $output);
  }

}

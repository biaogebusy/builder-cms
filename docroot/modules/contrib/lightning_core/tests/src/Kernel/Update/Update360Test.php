<?php

namespace Drupal\Tests\lightning_core\Kernel\Update;

use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_core\Update\Update360;

/**
 * Tests optional updates targeting Lightning Core 3.6.0.
 *
 * @group lightning_core
 *
 * @covers \Drupal\lightning_core\Update\Update360
 */
class Update360Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'lightning_core',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('user');
    $this->installEntitySchema('user');
  }

  /**
   * Tests optional updates targeting Lightning Core 3.6.0.
   */
  public function testUpdate(): void {
    $this->assertFalse($this->container->get('module_handler')->moduleExists('image'));
    $this->assertNull(FieldConfig::loadByName('user', 'user', 'user_picture'));
    $this->assertTrue($this->getCompactDisplay()->isNew());

    Update360::create($this->container)->enableUserPictures();
    // The update installed a module, which means the container has been reset.
    $this->container = $this->container->get('kernel')->getContainer();

    $this->assertTrue($this->container->get('module_handler')->moduleExists('image'));
    $this->assertInstanceOf(FieldConfig::class, FieldConfig::loadByName('user', 'user', 'user_picture'));

    $display = $this->getCompactDisplay();
    $this->assertFalse($display->isNew());
    $this->assertIsArray($display->getComponent('name'));
    $this->assertIsArray($display->getComponent('user_picture'));
  }

  /**
   * Returns the 'compact' entity view display for user accounts.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The 'compact' entity view display.
   */
  private function getCompactDisplay() {
    // Since the update installs a module, the container will be rebuilt. We
    // need to access the container through the kernel, which guarantees we will
    // always get the most up-to-date container.
    return $this->container->get('kernel')
      ->getContainer()
      ->get('entity_display.repository')
      ->getViewDisplay('user', 'user', 'compact');
  }

}

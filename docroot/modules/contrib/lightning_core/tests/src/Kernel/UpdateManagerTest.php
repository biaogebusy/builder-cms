<?php

namespace Drupal\Tests\lightning_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_core\UpdateManager;

/**
 * @coversDefaultClass \Drupal\lightning_core\UpdateManager
 *
 * @group lightning_core
 * @group orca_public
 */
class UpdateManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lightning_core', 'system', 'user'];

  /**
   * A partially-mocked update manager, exposing underlying plumbing.
   *
   * @var \Drupal\lightning_core\UpdateManager
   */
  private $updateManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->updateManager = new class (
      $this->container->get('container.namespaces'),
      $this->container->get('class_resolver'),
      $this->container->get('config.factory'),
      $this->container->get('extension.list.module')
    ) extends UpdateManager {

      /**
       * {@inheritdoc}
       */
      // @codingStandardsIgnoreStart
      public $discovery;
      // @codingStandardsIgnoreEnd

      /**
       * {@inheritdoc}
       */
      public function getTasks($handler) {
        yield from parent::getTasks($handler);
      }

    };
  }

  /**
   * @covers ::getAvailable
   */
  public function testGetAvailable() {
    $discovery = $this->prophesize('\Drupal\Component\Plugin\Discovery\DiscoveryInterface');
    $discovery->getDefinitions()->willReturn([
      'fubar:1.2.1' => [
        'id' => '1.2.1',
        'provider' => 'fubar',
      ],
      'fubar:1.2.2' => [
        'id' => '1.2.2',
        'provider' => 'fubar',
      ],
      'fubar:1.2.3' => [
        'id' => '1.2.3',
        'provider' => 'fubar',
      ],
    ]);
    $this->updateManager->discovery = $discovery->reveal();

    $this->container->get('config.factory')
      ->getEditable(UpdateManager::CONFIG_NAME)
      ->set('fubar', '1.2.2')
      ->save();

    $definitions = $this->updateManager->getAvailable();
    $this->assertCount(1, $definitions);
    $this->assertArrayHasKey('fubar:1.2.3', $definitions);
  }

  /**
   * @covers ::getTasks
   */
  public function testGetTasks() {
    $handler = new TestUpdate();
    $this->assertFalse($handler->invoked);
    $tasks = $this->updateManager->getTasks($handler);
    $this->assertInstanceOf('Generator', $tasks);
    $this->assertTrue($tasks->valid());
    $this->assertInstanceOf('\Drupal\lightning_core\UpdateTask', $tasks->current());
    $tasks->current()->execute($this->prophesize('\Symfony\Component\Console\Style\StyleInterface')->reveal(), TRUE);
    $this->assertTrue($handler->invoked);
  }

}

class TestUpdate {

  public $invoked = FALSE;

  /**
   * @update
   */
  public function test() {
    $this->invoked = TRUE;
  }

}

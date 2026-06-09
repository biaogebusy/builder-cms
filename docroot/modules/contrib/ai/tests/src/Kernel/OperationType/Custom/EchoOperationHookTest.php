<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\Custom;

use Drupal\KernelTests\KernelTestBase;

/**
 * This tests the echo calling.
 *
 * @coversDefaultClass \Drupal\ai_test\OperationType\Echo\EchoInterface
 *
 * @group ai
 */
class EchoOperationHookTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * Test adding a provider via a hook.
   */
  public function testCustomEchoOperation(): void {
    /** @var \Drupal\ai\AiProviderPluginManager $manager */
    $manager = \Drupal::service('ai.provider');
    $types = $manager->getOperationTypes();
    self::assertContains('echo', array_keys($types));
    self::assertEquals('Echo altered', $types['echo']['label']);
    /** @var \Drupal\ai_test\Plugin\AiProvider\EchoProvider $provider */
    $provider = $manager->createInstance('echoai');

    $input = $this->randomString();
    $output = $provider->echo($input, 'test');
    self::assertEquals($input, $output->getNormalized());
    self::assertEquals(['echo' => $input], $output->getRawOutput());
  }

}

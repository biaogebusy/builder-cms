<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for MessagePurgePluginManager form helpers.
 *
 * @coversDefaultClass \Drupal\message\MessagePurgePluginManager
 *
 * @group Message
 */
class MessagePurgePluginManagerKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['filter', 'message']);
  }

  /**
   * Tests building purge settings form elements.
   *
   * @covers ::purgeSettingsForm
   */
  public function testPurgeSettingsForm(): void {
    /** @var \Drupal\message\MessagePurgePluginManager $manager */
    $manager = $this->container->get('plugin.manager.message.purge');
    $form = ['settings' => []];
    $form_state = new FormState();
    $purge_settings = [
      'quota' => [
        'id' => 'quota',
        'weight' => 0,
        'data' => ['quota' => 25],
      ],
    ];
    $manager->purgeSettingsForm($form, $form_state, $purge_settings);

    $this->assertArrayHasKey('purge_methods', $form['settings']);
    $this->assertArrayHasKey('quota', $form['settings']['purge_methods']);
    $this->assertArrayHasKey('days', $form['settings']['purge_methods']);
    $this->assertEquals(1, $form['settings']['purge_methods']['quota']['enabled']['#default_value']);
    $this->assertArrayHasKey('quota', $form['settings']['purge_methods']['quota']['data']);
    $this->assertEquals(25, $form['settings']['purge_methods']['quota']['data']['quota']['#default_value']);
  }

  /**
   * Tests collecting enabled purge plugin configuration on submit.
   *
   * @covers ::getPurgeConfiguration
   */
  public function testGetPurgeConfiguration(): void {
    /** @var \Drupal\message\MessagePurgePluginManager $manager */
    $manager = $this->container->get('plugin.manager.message.purge');
    $form = [];
    $form_state = new FormState();
    $form_state->setValues([
      'settings' => [
        'purge_methods' => [
          'quota' => [
            'enabled' => 1,
            'weight' => 0,
            'data' => [],
          ],
          'days' => [
            'enabled' => 0,
            'weight' => 1,
            'data' => [],
          ],
        ],
      ],
      'quota' => 40,
      'days' => 30,
    ]);

    $config = $manager->getPurgeConfiguration($form, $form_state);
    $this->assertArrayHasKey('quota', $config);
    $this->assertArrayNotHasKey('days', $config);
    $this->assertEquals(40, $config['quota']['data']['quota']);
  }

}

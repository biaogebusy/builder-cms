<?php

namespace Drupal\Tests\message\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\message\Entity\MessageTemplate;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Kernel tests for the message purge orchestrator.
 *
 * @coversDefaultClass \Drupal\message\MessagePurgeOrchestrator
 *
 * @group Message
 */
class MessagePurgeOrchestratorTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'message', 'user', 'system'];

  /**
   * The purge orchestrator.
   *
   * @var \Drupal\message\MessagePurgeOrchestrator
   */
  protected $orchestrator;

  /**
   * The purge plugin manager.
   *
   * @var \Drupal\message\MessagePurgePluginManager
   */
  protected $purgeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('user');

    if (version_compare(\Drupal::VERSION, '10.2.0', '<')) {
      $this->installSchema('system', ['sequences']);
    }

    $this->orchestrator = $this->container->get('message.purge_orchestrator');
    $this->purgeManager = $this->container->get('plugin.manager.message.purge');
  }

  /**
   * Tests that overriding templates with empty purge methods are skipped.
   *
   * @covers ::purgeAllTemplateMessages
   */
  public function testOverrideWithEmptyMethodsSkipped(): void {
    $quota = $this->purgeManager->createInstance('quota', [
      'data' => ['quota' => 1],
    ]);
    MessageTemplate::create(['template' => 'keep_none'])
      ->setSettings([
        'purge_override' => TRUE,
        'purge_methods' => [],
      ])
      ->save();

    MessageTemplate::create(['template' => 'purge_me'])
      ->setSettings([
        'purge_override' => TRUE,
        'purge_methods' => [
          'quota' => $quota->getConfiguration(),
        ],
      ])
      ->save();

    $account = $this->createUser();
    foreach (range(1, 2) as $i) {
      Message::create(['template' => 'keep_none'])
        ->setOwnerId($account->id())
        ->save();
      Message::create(['template' => 'purge_me'])
        ->setOwnerId($account->id())
        ->save();
    }

    $this->orchestrator->purgeAllTemplateMessages();
    $this->container->get('cron')->run();

    $this->assertCount(2, Message::queryByTemplate('keep_none'));
    $this->assertCount(1, Message::queryByTemplate('purge_me'));
  }

  /**
   * Tests global purge settings apply to non-overriding templates.
   *
   * @covers ::purgeAllTemplateMessages
   */
  public function testGlobalPurgeEnabled(): void {
    $quota = $this->purgeManager->createInstance('quota', [
      'data' => ['quota' => 1],
    ]);
    $this->config('message.settings')
      ->set('purge_enable', TRUE)
      ->set('purge_methods', [
        'quota' => $quota->getConfiguration(),
      ])
      ->save();

    MessageTemplate::create(['template' => 'global_template'])->save();

    $account = $this->createUser();
    foreach (range(1, 2) as $i) {
      Message::create(['template' => 'global_template'])
        ->setOwnerId($account->id())
        ->save();
    }

    $this->orchestrator->purgeAllTemplateMessages();
    $this->container->get('cron')->run();

    $this->assertCount(1, Message::queryByTemplate('global_template'));
  }

  /**
   * Tests global purge disabled skips non-overriding templates.
   *
   * @covers ::purgeAllTemplateMessages
   */
  public function testGlobalPurgeDisabled(): void {
    $quota = $this->purgeManager->createInstance('quota', [
      'data' => ['quota' => 1],
    ]);
    $this->config('message.settings')
      ->set('purge_enable', FALSE)
      ->set('purge_methods', [
        'quota' => $quota->getConfiguration(),
      ])
      ->save();

    MessageTemplate::create(['template' => 'global_template'])->save();

    $account = $this->createUser();
    foreach (range(1, 2) as $i) {
      Message::create(['template' => 'global_template'])
        ->setOwnerId($account->id())
        ->save();
    }

    $this->orchestrator->purgeAllTemplateMessages();
    $this->container->get('cron')->run();

    $this->assertCount(2, Message::queryByTemplate('global_template'));
  }

}

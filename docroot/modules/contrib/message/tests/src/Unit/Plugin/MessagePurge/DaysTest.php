<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Unit\Plugin\MessagePurge;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Queue\QueueInterface;
use Drupal\message\MessagePurgeInterface;
use Drupal\message\Plugin\MessagePurge\Days;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for the days purge plugin.
 *
 * @coversDefaultClass \Drupal\message\Plugin\MessagePurge\Days
 *
 * @group Message
 */
class DaysTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Creates a Days plugin instance for tests.
   */
  protected function createPlugin(array $configuration = []): Days {
    $query = $this->prophesize(QueryInterface::class)->reveal();
    $request_stack = $this->prophesize(RequestStack::class)->reveal();
    $queue = $this->prophesize(QueueInterface::class)->reveal();
    $definition = [
      'id' => 'days',
      'label' => 'Days',
      'description' => 'Delete messages older than a given amount of days.',
    ];
    $plugin = new Days($configuration, 'days', $definition, $query, $queue, $request_stack);
    $plugin->setStringTranslation($this->getStringTranslationStub());
    return $plugin;
  }

  /**
   * Test processing zero message.
   *
   * @covers ::process
   */
  public function testProcessNone(): void {
    $query = $this->prophesize(QueryInterface::class)->reveal();
    $request_stack = $this->prophesize(RequestStack::class)->reveal();
    $queue = $this->prophesize(QueueInterface::class);
    $queue->createItem(Argument::any())->shouldNotBeCalled();
    $plugin = new Days([], 'days', [], $query, $queue->reveal(), $request_stack);
    $plugin->process([]);
  }

  /**
   * Tests processing more than defined queue item limit.
   *
   * @covers ::process
   */
  public function testProcess(): void {
    $query = $this->prophesize(QueryInterface::class)->reveal();
    $request_stack = $this->prophesize(RequestStack::class)->reveal();
    $queue = $this->prophesize(QueueInterface::class);
    $queue->createItem(Argument::size(MessagePurgeInterface::MESSAGE_DELETE_SIZE))->shouldBeCalledTimes(1);
    $queue->createItem(Argument::size(1))->shouldBeCalledTimes(1);
    $plugin = new Days([], 'days', [], $query, $queue->reveal(), $request_stack);

    $messages = range(1, MessagePurgeInterface::MESSAGE_DELETE_SIZE + 1);
    $plugin->process($messages);
  }

  /**
   * Tests building the configuration form.
   *
   * @covers ::buildConfigurationForm
   * @covers ::label
   * @covers ::description
   * @covers ::setWeight
   * @covers ::validateConfigurationForm
   */
  public function testBuildConfigurationForm(): void {
    $plugin = $this->createPlugin(['data' => ['days' => 14], 'weight' => 3]);
    $this->assertEquals('Days', $plugin->label());
    $this->assertStringContainsString('older', (string) $plugin->description());
    $this->assertSame($plugin, $plugin->setWeight(7));
    $this->assertEquals(7, $plugin->getWeight());

    $form_state = new FormState();
    $form = $plugin->buildConfigurationForm([], $form_state);
    $this->assertEquals('number', $form['days']['#type']);
    $this->assertEquals(14, $form['days']['#default_value']);

    $plugin->validateConfigurationForm($form, $form_state);
  }

  /**
   * Tests submitting the configuration form.
   *
   * @covers ::submitConfigurationForm
   */
  public function testSubmitConfigurationForm(): void {
    $plugin = $this->createPlugin();
    $form = [];
    $form_state = new FormState();
    $form_state->setValue('days', 45);
    $plugin->submitConfigurationForm($form, $form_state);
    $this->assertEquals(45, $plugin->getConfiguration()['data']['days']);
  }

}

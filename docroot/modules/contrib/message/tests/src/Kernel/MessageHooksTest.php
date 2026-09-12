<?php

declare(strict_types=1);

namespace Drupal\Tests\message\Kernel;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\message\Entity\Message;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for message.module helpers and hooks.
 *
 * @group Message
 */
class MessageHooksTest extends KernelTestBase {

  use MessageTemplateCreateTrait;
  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'message',
    'node',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'message']);
    $this->installEntitySchema('message');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
  }

  /**
   * Tests message_order_text_weight().
   */
  public function testOrderTextWeight(): void {
    $items = [
      ['value' => 'second', '_weight' => 2],
      ['value' => 'first', '_weight' => 1],
      ['value' => 'also_first', '_weight' => 1],
    ];

    usort($items, 'message_order_text_weight');

    $this->assertEquals('first', $items[0]['value']);
    $this->assertEquals('also_first', $items[1]['value']);
    $this->assertEquals('second', $items[2]['value']);
    $this->assertEquals(0, message_order_text_weight(
      ['_weight' => 5],
      ['_weight' => 5]
    ));
  }

  /**
   * Tests message_entity_extra_field_info().
   */
  public function testEntityExtraFieldInfo(): void {
    $template = $this->createMessageTemplate(
      'extra_fields_tpl',
      'Extra fields',
      'Description',
      [
        ['value' => 'Partial zero', 'format' => filter_default_format()],
        ['value' => 'Partial one', 'format' => filter_default_format()],
      ]
    );

    $extra = message_entity_extra_field_info();

    $this->assertArrayHasKey('partial_0', $extra['message'][$template->id()]['display']);
    $this->assertArrayHasKey('partial_1', $extra['message'][$template->id()]['display']);
    $this->assertStringContainsString('Partial', (string) $extra['message'][$template->id()]['display']['partial_0']['label']);
    $this->assertStringContainsString('0', (string) $extra['message'][$template->id()]['display']['partial_0']['label']);
  }

  /**
   * Tests non-integer text keys are skipped in extra field info.
   */
  public function testEntityExtraFieldInfoSkipsNonIntDelta(): void {
    $template = $this->createMessageTemplate(
      'extra_fields_skip',
      'Extra fields skip',
      'Description',
      [
        0 => ['value' => 'Partial zero', 'format' => filter_default_format()],
      ]
    );
    // Inject a non-integer key that getText() will preserve.
    $template->set('text', [
      0 => ['value' => 'Partial zero', 'format' => filter_default_format()],
      'translated' => ['value' => 'Skip me', 'format' => filter_default_format()],
    ]);
    $template->save();

    $extra = message_entity_extra_field_info();
    $display = $extra['message'][$template->id()]['display'];
    $this->assertArrayHasKey('partial_0', $display);
    $this->assertArrayNotHasKey('partial_translated', $display);
  }

  /**
   * Tests message_theme().
   */
  public function testTheme(): void {
    $theme = message_theme();
    $this->assertArrayHasKey('message', $theme);
    $this->assertEquals('elements', $theme['message']['render element']);
  }

  /**
   * Tests message_help().
   */
  public function testHelp(): void {
    $route_match = $this->prophesize(RouteMatchInterface::class)->reveal();

    $output = message_help('help.page.message', $route_match);
    $this->assertStringContainsString('<h3>', $output);
    $this->assertStringContainsString('About', $output);
    $this->assertStringContainsString('Uses', $output);
    $this->assertStringContainsString('message stack', $output);

    $this->assertSame('', message_help('help.page.system', $route_match));
  }

  /**
   * Tests message_entity_delete() early-exit branches.
   */
  public function testEntityDeleteEarlyExits(): void {
    $template = $this->createMessageTemplate(
      'delete_early_exit',
      'Delete early exit',
      'Description',
      ['Early exit template.']
    );
    $message = Message::create(['template' => $template->id()]);
    $message->save();

    $delete_queue = $this->container->get('queue')->get('message_delete');
    $check_queue = $this->container->get('queue')->get('message_check_delete');
    while ($item = $delete_queue->claimItem()) {
      $delete_queue->deleteItem($item);
    }
    while ($item = $check_queue->claimItem()) {
      $check_queue->deleteItem($item);
    }

    // Deleting a message entity itself must no-op.
    message_entity_delete($message);
    $this->assertEquals(0, $delete_queue->numberOfItems());
    $this->assertEquals(0, $check_queue->numberOfItems());

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test node',
    ]);
    $node->save();

    // Empty delete_on_entity_delete must no-op.
    $this->config('message.settings')->set('delete_on_entity_delete', [])->save();
    message_entity_delete($node);
    $this->assertEquals(0, $delete_queue->numberOfItems());
    $this->assertEquals(0, $check_queue->numberOfItems());

    // Non-entity-reference message fields must be ignored.
    $this->config('message.settings')
      ->set('delete_on_entity_delete', ['node'])
      ->save();
    FieldStorageConfig::create([
      'field_name' => 'field_plain',
      'entity_type' => 'message',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_plain',
      'entity_type' => 'message',
      'bundle' => $template->id(),
      'label' => 'Plain',
    ])->save();
    message_entity_delete($node);
    $this->assertEquals(0, $delete_queue->numberOfItems());
    $this->assertEquals(0, $check_queue->numberOfItems());
  }

}

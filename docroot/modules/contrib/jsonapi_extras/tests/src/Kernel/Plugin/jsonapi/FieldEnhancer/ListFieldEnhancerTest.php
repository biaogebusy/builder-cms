<?php

declare(strict_types=1);

namespace Drupal\Tests\jsonapi_extras\Kernel\Plugin\jsonapi\FieldEnhancer;

use Drupal\KernelTests\KernelTestBase;
use Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer\ListFieldEnhancer;
use Drupal\options\Plugin\Field\FieldType\ListIntegerItem;
use Shaper\Util\Context;

/**
 * Tests ListFieldEnhancer.
 *
 * @group jsonapi_extras
 */
final class ListFieldEnhancerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'jsonapi_extras',
    'jsonapi',
    'serialization',
    'system',
    'user',
    'options',
  ];

  /**
   * Tests JSON schema output.
   */
  public function testGetOutputJsonSchema(): void {
    self::assertEquals([
      'type' => 'array',
      'items' => [
        'type' => 'object',
        'properties' => [
          'value' => [
            'anyOf' => [
              ['type' => 'string'],
              ['type' => 'number'],
              ['type' => 'null'],
            ],
          ],
          'label' => [
            'anOf' => [
              ['type' => 'string'],
              ['type' => 'null'],
            ],
          ],
        ],
      ],
    ], $this->getInstance()->getOutputJsonSchema());
  }

  /**
   * Tests transform.
   */
  public function testTransform(): void {
    $output = $this->getInstance()->transform([['value' => 1]]);
    self::assertEquals([1], $output);
    $output = $this->getInstance()->transform('foo');
    self::assertEquals('foo', $output);
  }

  /**
   * Tests undo transform.
   */
  public function testUndoTransform(): void {
    $field_item = $this->createMock(ListIntegerItem::class);
    $field_item->expects($this->once())
      ->method('getPossibleOptions')
      ->willReturn([1 => 'One', 2 => 'Two', 3 => 'Three']);
    $context = new Context();
    $context['field_item_object'] = $field_item;
    $output = $this->getInstance()->undoTransform([3, 1, 2], $context);
    self::assertEquals(
      [
        ['value' => 3, 'label' => 'Three'],
        ['value' => 1, 'label' => 'One'],
        ['value' => 2, 'label' => 'Two'],
      ],
      $output
    );
  }

  /**
   * Gets a plugin instance for testing.
   *
   * @return \Drupal\jsonapi_extras\Plugin\jsonapi\FieldEnhancer\ListFieldEnhancer
   *   The plugin instance.
   */
  private function getInstance(): ListFieldEnhancer {
    return $this->container->get('plugin.manager.resource_field_enhancer')->createInstance('list');
  }

}

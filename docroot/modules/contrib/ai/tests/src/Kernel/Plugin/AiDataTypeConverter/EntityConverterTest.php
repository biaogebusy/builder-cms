<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiDataTypeConverter;

use Drupal\KernelTests\KernelTestBase;

/**
 * This tests entity converter.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\AiDataTypeConverter\EntityConverter
 *
 * @group ai
 */
class EntityConverterTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'key',
    'file',
    'system',
    'node',
    'media',
    'comment',
    'user',
  ];

  /**
   * The nid to test against.
   *
   * @var int
   */
  protected $testNid;

  /**
   * The user to test against.
   *
   * @var int
   */
  protected $testUid;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install the entity schema.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    // Install the necessary entity types.
    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'article',
      'name' => 'Article',
      'description' => 'An article content type.',
      'base' => 'node_content',
    ])->save();
    // Create a test node.
    $node = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'article',
      'title' => 'Test Node',
      'uid' => 1,
    ]);
    $node->save();
    $this->testNid = $node->id();
    // Create a test user.
    $user = $this->container->get('entity_type.manager')->getStorage('user')->create([
      'name' => 'Test User',
      'mail' => 'test@test.com',
      'status' => 1,
    ]);
    $user->save();
    $this->testUid = $user->id();
  }

  /**
   * Test the applicability.
   */
  public function testEntityConverterApplicability(): void {
    $converter = $this->container->get('plugin.manager.ai_data_type_converter')->createInstance('entity');
    // Check so it applied to data type.
    $this->assertTrue($converter->appliesToDataType('entity:node')->applies());
    $converter_result = $converter->appliesToDataType('test:node');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'Converter does not apply to data type.');
    $converter_result = $converter->appliesToDataType('array');
    $this->assertFalse($converter_result->applies());
    $this->assertEquals($converter_result->getReason(), 'Converter does not apply to data type.');

    // Check so it applied to value.
    $this->assertTrue($converter->appliesToValue('entity', 'node:' . $this->testNid)->applies());
    $this->assertTrue($converter->appliesToValue('entity', 'user:' . $this->testUid)->applies());
    $converter_result = $converter->appliesToValue('entity', 'string');
    $this->assertFalse($converter_result->valid());
    $this->assertEquals($converter_result->getReason(), 'Expected value format to match: <entity_type_id>:<id> or <entity_type_id>:<id>:<langcode>. Received the following value instead: string');
    $converter_result = $converter->appliesToValue('entity', 123);
    $this->assertFalse($converter_result->valid());
    $this->assertEquals($converter_result->getReason(), 'Value is not a string.');
    $converter_result = $converter->appliesToValue('entity', 'node:123123');
    $this->assertFalse($converter_result->valid());
    $this->assertEquals($converter_result->getReason(), 'Entity with provided ID does not exist.');

    // Check so it converts.
    $node = $converter->convert('entity', 'node:' . $this->testNid);
    $this->assertInstanceOf('Drupal\node\NodeInterface', $node);
    $this->assertEquals($node->id(), $this->testNid);
    $user = $converter->convert('entity', 'user:' . $this->testUid);
    $this->assertInstanceOf('Drupal\user\UserInterface', $user);
    $this->assertEquals($user->id(), $this->testUid);
  }

}

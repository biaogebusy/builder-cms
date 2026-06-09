<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\Plugin\AiAutomatorProcess\DirectSaveProcessing;
use Drupal\ai_automators\Plugin\AiAutomatorProcess\FieldWidgetProcessing;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests AI automator processing error handling.
 *
 * Verifies that both DirectSaveProcessing and FieldWidgetProcessing handle
 * provider exceptions gracefully. PHP errors like \TypeError from the OpenAI
 * PHP client are wrapped in AiResponseErrorException by the provider layer
 * before reaching the automator processors.
 *
 * @group ai_automators
 */
class AiAutomatorProcessingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'node',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * A test node entity.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['system', 'field', 'node', 'filter']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_long',
      ])->save();
    }

    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Body',
    ])->save();

    $this->node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
      'body' => [['value' => 'Test body content']],
    ]);
    $this->node->save();
  }

  /**
   * Tests that FieldWidgetProcessing handles AiResponseErrorException.
   *
   * The provider layer catches \TypeError from the OpenAI PHP client and
   * wraps it in AiResponseErrorException before it reaches the automator.
   * The processor must catch this as an \Exception subclass.
   */
  public function testFieldWidgetProcessingHandlesProviderError(): void {
    $rule_runner = $this->createMock(AiAutomatorRuleRunner::class);
    $rule_runner->method('generateResponse')
      ->willThrowException(new AiResponseErrorException(
        'CreateResponse::from(): Argument #1 ($attributes) must be of type array, string given',
      ));

    $processor = new FieldWidgetProcessing(
      $rule_runner,
      \Drupal::service('logger.factory'),
      \Drupal::service('messenger'),
      \Drupal::service('module_handler'),
    );

    $field_definition = $this->node->getFieldDefinition('body');
    $result = $processor->modify($this->node, $field_definition, []);

    $this->assertFalse($result, 'FieldWidgetProcessing should return FALSE on AiResponseErrorException.');
  }

  /**
   * Tests that DirectSaveProcessing handles AiResponseErrorException.
   *
   * Same scenario as above but for the direct-save processor path.
   */
  public function testDirectSaveProcessingHandlesProviderError(): void {
    $rule_runner = $this->createMock(AiAutomatorRuleRunner::class);
    $rule_runner->method('generateResponse')
      ->willThrowException(new AiResponseErrorException(
        'CreateResponse::from(): Argument #1 ($attributes) must be of type array, string given',
      ));

    $processor = new DirectSaveProcessing(
      $rule_runner,
      \Drupal::service('logger.factory'),
      \Drupal::service('messenger'),
    );

    $field_definition = $this->node->getFieldDefinition('body');
    $result = $processor->modify($this->node, $field_definition, []);

    $this->assertFalse($result, 'DirectSaveProcessing should return FALSE on AiResponseErrorException.');
  }

  /**
   * Tests that FieldWidgetProcessing still handles regular exceptions.
   *
   * Ensures existing \Exception handling is not broken.
   */
  public function testFieldWidgetProcessingStillHandlesExceptions(): void {
    $rule_runner = $this->createMock(AiAutomatorRuleRunner::class);
    $rule_runner->method('generateResponse')
      ->willThrowException(new \RuntimeException('Provider unavailable'));

    $processor = new FieldWidgetProcessing(
      $rule_runner,
      \Drupal::service('logger.factory'),
      \Drupal::service('messenger'),
      \Drupal::service('module_handler'),
    );

    $field_definition = $this->node->getFieldDefinition('body');
    $result = $processor->modify($this->node, $field_definition, []);

    $this->assertFalse($result, 'FieldWidgetProcessing should return FALSE on regular \Exception.');
  }

  /**
   * Tests that DirectSaveProcessing still handles regular exceptions.
   *
   * Ensures existing \Exception handling is not broken.
   */
  public function testDirectSaveProcessingStillHandlesExceptions(): void {
    $rule_runner = $this->createMock(AiAutomatorRuleRunner::class);
    $rule_runner->method('generateResponse')
      ->willThrowException(new \RuntimeException('Provider unavailable'));

    $processor = new DirectSaveProcessing(
      $rule_runner,
      \Drupal::service('logger.factory'),
      \Drupal::service('messenger'),
    );

    $field_definition = $this->node->getFieldDefinition('body');
    $result = $processor->modify($this->node, $field_definition, []);

    $this->assertFalse($result, 'DirectSaveProcessing should return FALSE on regular \Exception.');
  }

}

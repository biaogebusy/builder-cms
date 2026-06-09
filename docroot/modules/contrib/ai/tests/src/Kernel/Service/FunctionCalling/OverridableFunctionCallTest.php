<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service\FunctionCalling;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\OverridableFunctionCallInterface;

/**
 * Tests per-instance context definition overrides on function call plugins.
 *
 * @group ai
 *
 * @covers \Drupal\ai\Base\FunctionCallBase::setContextDefinitionOverride
 * @covers \Drupal\ai\Base\FunctionCallBase::getContextDefinition
 * @covers \Drupal\ai\Base\FunctionCallBase::getContextDefinitions
 */
final class OverridableFunctionCallTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Plugin\AiFunctionCall\AiFunctionCallManager
   */
  protected $functionCallManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'key',
    'ai',
    'system',
    'ai_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');
  }

  /**
   * Tests that the plugin implements the overridable interface.
   */
  public function testImplementsOverridableInterface(): void {
    $instance = $this->functionCallManager->createInstance('ai:calculator');
    $this->assertInstanceOf(OverridableFunctionCallInterface::class, $instance);
  }

  /**
   * Tests getContextDefinition before and after setContextDefinitionOverride.
   */
  public function testGetContextDefinitionOverride(): void {
    $instance = $this->functionCallManager->createInstance('ai:calculator');

    // Before override: the plugin definition's 'expression' context should be
    // a string with the original label and description.
    $original = $instance->getContextDefinition('expression');
    $this->assertSame('string', $original->getDataType());
    $this->assertSame('Expression', (string) $original->getLabel());
    $this->assertTrue($original->isRequired());

    // Apply an override that changes data type, label and required flag.
    $override = new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Overridden Expression'),
      description: new TranslatableMarkup('An overridden description.'),
      required: FALSE,
    );
    $instance->setContextDefinitionOverride('expression', $override);

    // After override: getContextDefinition should return the overridden value.
    $after = $instance->getContextDefinition('expression');
    $this->assertSame($override, $after);
    $this->assertSame('integer', $after->getDataType());
    $this->assertSame('Overridden Expression', (string) $after->getLabel());
    $this->assertFalse($after->isRequired());
  }

  /**
   * Tests getContextDefinitions before and after setContextDefinitionOverride.
   */
  public function testGetContextDefinitionsOverride(): void {
    $instance = $this->functionCallManager->createInstance('ai:calculator');

    // Before override: the collection contains the original 'expression'
    // definition from the plugin attribute.
    $before = $instance->getContextDefinitions();
    $this->assertArrayHasKey('expression', $before);
    $this->assertSame('string', $before['expression']->getDataType());
    $this->assertSame('Expression', (string) $before['expression']->getLabel());

    // Apply an override.
    $override = new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Overridden Expression'),
      required: FALSE,
    );
    $instance->setContextDefinitionOverride('expression', $override);

    // After override: the override takes precedence within the collection.
    $after = $instance->getContextDefinitions();
    $this->assertArrayHasKey('expression', $after);
    $this->assertSame($override, $after['expression']);
    $this->assertSame('integer', $after['expression']->getDataType());
    $this->assertSame('Overridden Expression', (string) $after['expression']->getLabel());
  }

  /**
   * Tests that overrides are scoped to a single plugin instance.
   */
  public function testOverrideIsPerInstance(): void {
    $instance_a = $this->functionCallManager->createInstance('ai:calculator');
    $instance_b = $this->functionCallManager->createInstance('ai:calculator');

    $override = new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Only On A'),
      required: FALSE,
    );
    $instance_a->setContextDefinitionOverride('expression', $override);

    $this->assertSame('integer', $instance_a->getContextDefinition('expression')->getDataType());
    $this->assertSame('string', $instance_b->getContextDefinition('expression')->getDataType());
    $this->assertSame('Expression', (string) $instance_b->getContextDefinition('expression')->getLabel());
  }

  /**
   * Tests that a new definition name added via override appears in the set.
   */
  public function testOverrideAddsNewContextDefinition(): void {
    $instance = $this->functionCallManager->createInstance('ai:calculator');

    $before = $instance->getContextDefinitions();
    $this->assertArrayNotHasKey('extra', $before);

    $extra = new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Extra'),
      required: FALSE,
    );
    $instance->setContextDefinitionOverride('extra', $extra);

    $after = $instance->getContextDefinitions();
    $this->assertArrayHasKey('extra', $after);
    $this->assertSame($extra, $after['extra']);
    $this->assertArrayHasKey('expression', $after);
    $this->assertSame($extra, $instance->getContextDefinition('extra'));
  }

}

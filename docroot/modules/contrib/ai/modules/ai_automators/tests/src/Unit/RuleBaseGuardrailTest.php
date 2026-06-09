<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ai\Entity\AiGuardrailModeEnum;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\Guardrail\AiGuardrailInterface;
use Drupal\ai\Guardrail\AiGuardrailRepository;
use Drupal\ai\Guardrail\AiGuardrailSetInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests guardrail helpers on RuleBase.
 *
 * @group ai_automators
 * @coversDefaultClass \Drupal\ai_automators\PluginBaseClasses\RuleBase
 */
class RuleBaseGuardrailTest extends UnitTestCase {

  /**
   * Applying a guardrail set via automator config attaches it to the input.
   */
  public function testApplyGuardrailsToInputAttachesSet(): void {
    $set = $this->createMock(AiGuardrailSetInterface::class);
    $set->method('id')->willReturn('test_set');

    $repository = $this->createMock(AiGuardrailRepository::class);
    $repository->method('getGuardrailSetById')->with('test_set')->willReturn($set);

    $helper = new AiGuardrailHelper($repository);

    $rule = $this->makeRule($helper);
    $input = new ChatInput([new ChatMessage('user', 'hi')]);
    $result = $this->invokeApply($rule, $input, ['guardrail_set_id' => 'test_set']);

    $this->assertNotSame($input, $result, 'Helper clones the input when attaching a set.');
    $this->assertSame(['test_set' => $set], $result->getGuardrailSets());
  }

  /**
   * Missing guardrail_set_id in config leaves the input untouched.
   */
  public function testApplyGuardrailsToInputNoConfigReturnsOriginal(): void {
    $repository = $this->createMock(AiGuardrailRepository::class);
    $repository->expects($this->never())->method('getGuardrailSetById');

    $helper = new AiGuardrailHelper($repository);
    $rule = $this->makeRule($helper);

    $input = new ChatInput([new ChatMessage('user', 'hi')]);
    $result = $this->invokeApply($rule, $input, []);

    $this->assertSame($input, $result);
    $this->assertSame([], $result->getGuardrailSets());
  }

  /**
   * Aggregated stop score at or above the set's threshold triggers an abort.
   */
  public function testAssertNotStoppedByGuardrailThrowsOnStopResult(): void {
    $set = $this->createMock(AiGuardrailSetInterface::class);
    $set->method('id')->willReturn('legal_set');
    $set->method('getStopThreshold')->willReturn(0.7);

    $input = new ChatInput([new ChatMessage('user', 'please give me legal advice')]);
    $input->addGuardrailSet($set);

    $guardrail = $this->createMock(AiGuardrailInterface::class);
    $input->addGuardrailResult(
      new StopResult('Legal advice is not allowed.', $guardrail, [], 1.0),
      AiGuardrailModeEnum::PreGenerate,
    );

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('warning')
      ->with(
        $this->stringContains('blocked by guardrail'),
        $this->callback(fn (array $ctx): bool => ($ctx['@set'] ?? NULL) === 'legal_set'),
      );

    $rule = $this->makeRule($this->dummyHelper(), $logger);

    $this->expectException(AiAutomatorResponseErrorException::class);
    $this->expectExceptionMessage('Legal advice is not allowed.');
    $this->invokeAssert($rule, $input);
  }

  /**
   * A stop score below the set's threshold must not trigger an abort.
   */
  public function testAssertNotStoppedByGuardrailBelowThresholdIsNoop(): void {
    $set = $this->createMock(AiGuardrailSetInterface::class);
    $set->method('id')->willReturn('soft_set');
    $set->method('getStopThreshold')->willReturn(0.7);

    $input = new ChatInput([new ChatMessage('user', 'borderline content')]);
    $input->addGuardrailSet($set);

    $guardrail = $this->createMock(AiGuardrailInterface::class);
    $input->addGuardrailResult(
      new StopResult('borderline', $guardrail, [], 0.3),
      AiGuardrailModeEnum::PreGenerate,
    );

    $rule = $this->makeRule($this->dummyHelper());
    $this->invokeAssert($rule, $input);
    $this->addToAssertionCount(1);
  }

  /**
   * No guardrail set on the input means no abort, regardless of stored results.
   */
  public function testAssertNotStoppedByGuardrailNoSetIsNoop(): void {
    $input = new ChatInput([new ChatMessage('user', 'hi')]);

    $rule = $this->makeRule($this->dummyHelper());
    // Should not throw.
    $this->invokeAssert($rule, $input);
    $this->addToAssertionCount(1);
  }

  /**
   * A non-stop result (e.g. PassResult) must not trigger an abort.
   */
  public function testAssertNotStoppedByGuardrailPassResultIsNoop(): void {
    $set = $this->createMock(AiGuardrailSetInterface::class);
    $set->method('id')->willReturn('ok_set');
    $set->method('getStopThreshold')->willReturn(0.7);

    $input = new ChatInput([new ChatMessage('user', 'hi')]);
    $input->addGuardrailSet($set);

    $guardrail = $this->createMock(AiGuardrailInterface::class);
    $input->addGuardrailResult(
      new PassResult('ok', $guardrail),
      AiGuardrailModeEnum::PreGenerate,
    );

    $rule = $this->makeRule($this->dummyHelper());
    $this->invokeAssert($rule, $input);
    $this->addToAssertionCount(1);
  }

  /**
   * Build a RuleBase instance without invoking its collaborators' constructors.
   *
   * AiProviderPluginManager and AiProviderFormHelper are final, so they cannot
   * be mocked; the helper methods under test don't touch them, so we build the
   * instance via reflection and only populate the properties we actually need.
   */
  private function makeRule(AiGuardrailHelper $helper, ?LoggerChannelInterface $logger = NULL): RuleBase {
    $reflection = new \ReflectionClass(RuleBaseGuardrailTestStub::class);
    /** @var \Drupal\ai_automators\PluginBaseClasses\RuleBase $rule */
    $rule = $reflection->newInstanceWithoutConstructor();

    $helperProperty = (new \ReflectionClass(RuleBase::class))->getProperty('aiGuardrailHelper');
    $helperProperty->setAccessible(TRUE);
    $helperProperty->setValue($rule, $helper);

    $loggerProperty = (new \ReflectionClass(RuleBase::class))->getProperty('logger');
    $loggerProperty->setAccessible(TRUE);
    $loggerProperty->setValue($rule, $logger);

    return $rule;
  }

  /**
   * Build a stand-in helper for tests that don't exercise repository lookups.
   */
  private function dummyHelper(): AiGuardrailHelper {
    return new AiGuardrailHelper($this->createMock(AiGuardrailRepository::class));
  }

  /**
   * Invoke the protected helper.
   */
  private function invokeApply(RuleBase $rule, ChatInput $input, array $config): ChatInput {
    $method = new \ReflectionMethod($rule, 'applyGuardrailsToInput');
    $method->setAccessible(TRUE);
    return $method->invoke($rule, $input, $config);
  }

  /**
   * Invoke the protected helper.
   */
  private function invokeAssert(RuleBase $rule, ChatInput $input): void {
    $method = new \ReflectionMethod($rule, 'assertNotStoppedByGuardrail');
    $method->setAccessible(TRUE);
    $method->invoke($rule, $input);
  }

}

/**
 * Minimal concrete RuleBase for reflection-based instantiation in tests.
 */
class RuleBaseGuardrailTestStub extends RuleBase {

  /**
   * {@inheritdoc}
   */
  public function generate(
    ContentEntityInterface $entity,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeValues(
    ContentEntityInterface $entity,
    array $values,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function verifyValue(
    ContentEntityInterface $entity,
    $value,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
    return TRUE;
  }

}

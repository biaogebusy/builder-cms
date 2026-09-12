<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_assistant_api\Service\AgentRunner;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @coversDefaultClass \Drupal\ai_assistant_api\Service\AgentRunner
 * @group ai_assistant_api
 */
#[CoversMethod(AgentRunner::class, 'runAsAgent')]
class AgentRunnerTest extends UnitTestCase {

  /**
   * Mock AI provider plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected PluginManagerInterface|MockObject $aiProvider;

  /**
   * Mock private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory|\PHPUnit\Framework\MockObject\MockObject
   */
  protected PrivateTempStoreFactory|MockObject $tempStoreFactory;

  /**
   * Mock event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected EventDispatcherInterface|MockObject $eventDispatcher;

  /**
   * Mock AI agent plugin manager.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected MockObject $aiAgentPluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->aiProvider = $this->createMock(PluginManagerInterface::class);
    $this->tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    $this->aiAgentPluginManager = $this->createMock(AgentPluginManagerInterface::class);
  }

  /**
   * Tests exception when the agent does not implement ConfigAiAgentInterface.
   *
   * This simulates the machine-name collision scenario where an ai_agent config
   * entity shares its machine name with a code-defined AiAgent plugin.
   */
  public function testRunAsAgentThrowsWhenAgentDoesNotImplementConfigAiAgentInterface(): void {
    // Build a plain object that does NOT implement ConfigAiAgentInterface.
    // We cannot reference the real AiAgentInterface here because ai_agents is
    // an optional submodule absent from the autoload path in unit tests.
    $codePlugin = new \stdClass();

    $this->aiAgentPluginManager
      ->expects($this->once())
      ->method('createInstance')
      ->with('my_agent')
      ->willReturn($codePlugin);

    $runner = new AgentRunner(
      $this->aiProvider,
      $this->tempStoreFactory,
      $this->eventDispatcher,
      $this->aiAgentPluginManager,
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessageMatches(
      '/my_agent.*does not implement.*ConfigAiAgentInterface/s'
    );

    $runner->runAsAgent('my_agent', [], [], 'job-1');
  }

}

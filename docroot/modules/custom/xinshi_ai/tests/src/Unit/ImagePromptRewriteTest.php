<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiImageTaskBase;
use Drupal\xinshi_ai\Service\EventStreamServiceInterface;
use Drupal\xinshi_ai\Service\ImageJobRun;
use Drupal\xinshi_ai\Service\ImageUsageRecorder;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Drupal\xinshi_ai\Service\PromptRewriteClient;
use Drupal\xinshi_ai\Service\ProviderRequestTrace;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The image pipeline settles the prompt before the provider call: a text-to-image prompt
 * naming live data is rewritten by the chat service under the job's operation (UB2.6).
 */
final class ImagePromptRewriteTest extends TestCase {

  use ImageJobNodeTrait;

  private JobLifecycleServiceInterface&MockObject $lifecycle;
  private PromptRewriteClient&MockObject $rewriter;
  private ImageUsageRecorder&MockObject $usage;
  private RewriteProbeTask $task;
  /** @var list<array{0:string,1:array}> */
  private array $published = [];
  /** @var list<array{0:string,1:string}> */
  private array $recorded = [];
  /** What the stored job says when the pipeline re-reads it. */
  private string $storedStatus = 'running';
  private bool $rewriteAccepted = TRUE;
  private bool $configured = TRUE;
  private ?string $revised = '上海今日晴，25 度';

  protected function setUp(): void {
    parent::setUp();
    $this->lifecycle = $this->createMock(JobLifecycleServiceInterface::class);
    $this->lifecycle->method('markStarted')->willReturn(TRUE);
    $this->lifecycle->method('markCompleted')->willReturn(TRUE);
    $this->lifecycle->method('refreshStatus')->willReturnCallback(function (NodeInterface $job): string {
      $job->set('field_status', $this->storedStatus);
      return $this->storedStatus;
    });
    $this->lifecycle->method('recordPromptRewrite')
      ->willReturnCallback(function (NodeInterface $job, string $prompt, string $original): bool {
        $this->recorded[] = [$prompt, $original];
        if ($this->rewriteAccepted) {
          $job->set('field_prompt', $prompt);
        }
        return $this->rewriteAccepted;
      });
    $events = $this->createMock(EventStreamServiceInterface::class);
    $events->method('publish')->willReturnCallback(function (string $uuid, string $event, array $data): void {
      $this->published[] = [$event, $data];
    });
    $this->rewriter = $this->createMock(PromptRewriteClient::class);
    $this->rewriter->method('isConfigured')->willReturnCallback(fn(): bool => $this->configured);
    $this->rewriter->method('rewrite')->willReturnCallback(fn(): ?string => $this->revised);
    $this->usage = $this->createMock(ImageUsageRecorder::class);
    $this->task = new RewriteProbeTask($this->lifecycle, $events, $this->usage, $this->rewriter,
      $this->createMock(LoggerInterface::class));
  }

  public function testALivePromptIsRewrittenStoredAndPushedBeforeTheProviderCall(): void {
    $job = $this->jobNode('job-1', ['field_prompt' => '上海今天的天气', 'field_status' => 'queued']);
    $this->rewriter->expects($this->once())->method('rewrite')->with($job, '上海今天的天气');

    $this->task->execute($job);

    $this->assertSame([['上海今日晴，25 度', '上海今天的天气']], $this->recorded);
    $this->assertSame(['上海今日晴，25 度'], $this->task->prompts, 'the provider gets the rewritten prompt');
    $this->assertSame([
      ['status', ['status' => 'running']],
      ['status', ['status' => 'running', 'prompt' => '上海今日晴，25 度']],
      ['completed', ['nSucceeded' => 0, 'completedAt' => 0]],
    ], $this->published);
  }

  public function testTheStepIsNotOrderedWhenItCannotHelp(): void {
    $this->rewriter->expects($this->never())->method('rewrite');
    // A static prompt.
    $this->task->execute($this->jobNode('job-1', ['field_prompt' => '非洲大草原动物世界']));
    // Already rewritten: an earlier run of this job, or a client that still rewrites itself.
    $this->task->execute($this->jobNode('job-2', ['field_prompt' => '上海今日晴',
      'field_params' => '{"originalPrompt":"上海今天的天气"}']));
    // Image edits keep the prompt the user typed.
    $this->task->execute($this->jobNode('job-3', ['field_prompt' => '上海今天的天气', 'field_job_kind' => 'image_edit']));
    // No chat service address configured.
    $this->configured = FALSE;
    $this->task->execute($this->jobNode('job-4', ['field_prompt' => '上海今天的天气']));

    $this->assertSame(['非洲大草原动物世界', '上海今日晴', '上海今天的天气', '上海今天的天气'], $this->task->prompts);
    $this->assertSame([], $this->recorded);
    $this->assertSame([], array_filter($this->published, static fn(array $entry): bool => isset($entry[1]['prompt'])));
  }

  public function testAFailedRewriteGeneratesTheOriginalPrompt(): void {
    $this->revised = NULL;
    $this->task->execute($this->jobNode('job-1', ['field_prompt' => '上海今天的天气']));
    $this->assertSame(['上海今天的天气'], $this->task->prompts);
    $this->assertSame([], $this->recorded);
    $this->assertSame([['status', ['status' => 'running']], ['completed', ['nSucceeded' => 0, 'completedAt' => 0]]],
      $this->published);
  }

  public function testAJobCancelledDuringTheRewriteNeverReachesTheProvider(): void {
    $this->rewriteAccepted = FALSE;
    $this->storedStatus = 'cancelled';
    $this->usage->expects($this->never())->method('begin');

    $this->task->execute($this->jobNode('job-1', ['field_prompt' => '上海今天的天气']));

    $this->assertSame([['上海今日晴，25 度', '上海今天的天气']], $this->recorded, 'the rewrite was offered to the stored job');
    $this->assertSame([], $this->task->prompts, 'the image model is not called');
    $this->assertSame([['status', ['status' => 'running']]], $this->published);
  }

}

/**
 * A text-to-image task whose provider call only records the prompt it was given.
 */
final class RewriteProbeTask extends AiImageTaskBase {

  /** @var list<string> */
  public array $prompts = [];

  public function __construct(JobLifecycleServiceInterface $lifecycle, EventStreamServiceInterface $eventStream,
    ImageUsageRecorder $usageRecorder, PromptRewriteClient $promptRewrite, LoggerInterface $logger) {
    parent::__construct([], 'probe', ['defaultN' => 1]);
    $this->lifecycle = $lifecycle;
    $this->eventStream = $eventStream;
    $this->usageRecorder = $usageRecorder;
    $this->promptRewrite = $promptRewrite;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $input, AccountInterface $account): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function execute(NodeInterface $job, ?ImageJobRun $run = NULL): void {
    $this->runImagePipeline($job, [], 'text_to_image', function (object $provider, string $model, string $prompt): object {
      $this->prompts[] = $prompt;
      return new class {

        public function getRawOutput(): array {
          return ['data' => []];
        }

        public function getNormalized(): array {
          return [];
        }

      };
    }, $run);
  }

  /**
   * {@inheritdoc}
   */
  protected function getProvider(NodeInterface $job, array $genConfig, string $operationType,
    ?ProviderRequestTrace $trace = NULL): object {
    return new \stdClass();
  }

}

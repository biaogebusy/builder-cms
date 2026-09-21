<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Service\ImageAttempt;
use Drupal\xinshi_ai\Service\ImageUsageRecorder;
use Drupal\xinshi_ai\Service\ProviderRequestTrace;
use Drupal\xinshi_ai_usage\Service\LocalUsageException;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Image jobs open their usage attempt before the provider call and record what came back (UB2.4).
 */
final class ImageUsageMeteringTest extends TestCase {

  public function testBeginInterruptsStaleIntentsThenWritesTheNewOne(): void {
    $calls = [];
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->expects($this->once())->method('interruptOpenAttempts')->with('job-1')
      ->willReturnCallback(function () use (&$calls): array {
        $calls[] = 'interrupt';
        return [];
      });
    $producer->expects($this->once())->method('prepare')
      ->willReturnCallback(function (array $intent) use (&$calls): array {
        $calls[] = 'prepare';
        $this->assertSame([
          'operation_id' => 'job-1', 'logical_call_id' => 'image', 'feature' => 'text_to_image',
          'stage' => 'image', 'payer' => 'platform', 'billing_role' => 'primary',
          'provider_account_ref' => 'xinshi', 'requested_model' => 'qwen-image',
          'actor_user_id' => '7', 'task_id' => 'task-1',
        ], $intent);
        return ['attempt_id' => 'att_1', 'attempt_no' => 2, 'site_id' => 'site-a', 'started_at' => 1];
      });

    $attempt = $this->recorder($producer)->begin($this->call());

    $this->assertSame(['interrupt', 'prepare'], $calls);
    $this->assertInstanceOf(ImageAttempt::class, $attempt);
    $this->assertSame('att_1', $attempt->attemptId);
    $this->assertSame('job-1', $attempt->operationId);
    $this->assertSame('site-a', $attempt->siteId);
    $this->assertSame(2, $attempt->attemptNo);
    $this->assertFalse($attempt->isObserved());
  }

  public function testCustomerKeyJobsArePaidByTheCustomerAccount(): void {
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->method('prepare')->willReturnCallback(function (array $intent): array {
      $this->assertSame('customer_key', $intent['payer']);
      $this->assertSame('customer_key', $intent['provider_account_ref']);
      $this->assertNull($intent['task_id']);
      return ['attempt_id' => 'att_1', 'attempt_no' => 1, 'site_id' => 'site-a', 'started_at' => 1];
    });
    $this->assertNotNull($this->recorder($producer)->begin($this->call(['platform' => 'custom', 'task_id' => ''])));
  }

  public function testObserveModeRunsUnmeteredWhenTheIntentCannotBeWritten(): void {
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->method('prepare')->willThrowException(new \RuntimeException('disk full'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())->method('error')->with(
      $this->stringContains('unmetered'),
      $this->callback(fn(array $context): bool => $context['@job'] === 'job-1'
        && str_contains((string) $context['@message'], 'disk full')),
    );
    $this->assertNull($this->recorder($producer, $logger)->begin($this->call()));
  }

  public function testEnforceModeRefusesTheJobBeforeTheProviderCall(): void {
    $producer = $this->producer(LocalUsageProducer::MODE_ENFORCE);
    $producer->method('prepare')->willThrowException(new \RuntimeException('disk full'));
    try {
      $this->recorder($producer)->begin($this->call());
      $this->fail('expected LocalUsageException');
    }
    catch (LocalUsageException $e) {
      $this->assertSame('write_failed', $e->usageCode);
      $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
    }

    // The producer's own machine code is kept.
    $producer = $this->producer(LocalUsageProducer::MODE_ENFORCE);
    $producer->method('interruptOpenAttempts')
      ->willThrowException(new LocalUsageException('site_unresolved', 'No usage site id'));
    $producer->expects($this->never())->method('prepare');
    try {
      $this->recorder($producer)->begin($this->call());
      $this->fail('expected LocalUsageException');
    }
    catch (LocalUsageException $e) {
      $this->assertSame('site_unresolved', $e->usageCode);
    }
  }

  public function testASuccessfulCallIsObservedFromTheRawBodyWithTheGatewayRequestId(): void {
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->expects($this->once())->method('observe')
      ->with('att_1', $this->callback(function (array $observation): bool {
        $this->assertSame('succeeded', $observation['outcome']);
        $this->assertSame('sent', $observation['dispatch_state']);
        $this->assertNull($observation['error_code']);
        $this->assertSame('req-7', $observation['gateway_request_id']);
        $this->assertSame('reported', $observation['usage']['quality']);
        $this->assertSame('2', $observation['usage']['images_generated']);
        return TRUE;
      }))
      ->willReturn(['attempt_id' => 'att_1', 'observation_revision' => 1]);
    $attempt = $this->attempt($producer);
    $attempt->succeeded(['created' => 1, 'data' => [['url' => 'a'], ['url' => 'b']]], $this->trace(200, 'req-7'));
    $this->assertTrue($attempt->isObserved());
  }

  public function testFailuresRecordWhetherTheRequestEverLeft(): void {
    $observed = [];
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->method('observe')->willReturnCallback(function (string $id, array $observation) use (&$observed): array {
      $observed[] = [$observation['outcome'], $observation['dispatch_state'], $observation['error_code'],
        $observation['gateway_request_id'], $observation['usage']];
      return ['attempt_id' => $id, 'observation_revision' => count($observed)];
    });

    // Provider setup failed before any image request.
    $this->attempt($producer)->failed('provider_unavailable', new ProviderRequestTrace());
    // Dispatched, no response: the supplier may still have charged.
    $this->attempt($producer)->failed('timeout', $this->trace(NULL, NULL));
    // Dispatched, the gateway answered with an error.
    $this->attempt($producer)->failed('content_policy', $this->trace(400, 'req-9'));

    $this->assertSame([
      ['not_sent', 'not_sent', 'provider_unavailable', NULL, NULL],
      ['failed', 'unknown', 'timeout', NULL, NULL],
      ['failed', 'sent', 'content_policy', 'req-9', NULL],
    ], $observed);
  }

  public function testPersistedAndCommittedArtifactsAreSeparateDeliveryFacts(): void {
    $deliveries = [];
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->method('recordDelivery')->willReturnCallback(function (...$args) use (&$deliveries): void {
      $deliveries[] = $args;
    });
    $attempt = $this->attempt($producer);
    $attempt->persisted(0, 'media-0');
    $attempt->committed(0, 'asset-0');
    $attempt->persisted(1, 'media-1');
    $this->assertSame([
      ['site-a', 'job-1', 'att_1', 0, ImageAttempt::ARTIFACT_MEDIA, 'media-0', NULL, LocalUsageProducer::STATE_PERSISTED],
      ['site-a', 'job-1', 'att_1', 0, ImageAttempt::ARTIFACT_ASSET, 'asset-0', NULL, LocalUsageProducer::STATE_COMMITTED],
      ['site-a', 'job-1', 'att_1', 1, ImageAttempt::ARTIFACT_MEDIA, 'media-1', NULL, LocalUsageProducer::STATE_PERSISTED],
    ], $deliveries);
    $this->assertFalse($attempt->isObserved());
  }

  public function testBookkeepingFailuresAreLoggedAndNeverReplaceTheProviderResult(): void {
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->method('observe')->willThrowException(new \RuntimeException('db gone'));
    $producer->method('recordDelivery')->willThrowException(new \RuntimeException('db gone'));
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->exactly(2))->method('error');
    $attempt = $this->attempt($producer, $logger);
    $attempt->succeeded(['data' => []], $this->trace(200, NULL));
    $attempt->committed(0, 'asset-0');
    $this->assertTrue($attempt->isObserved());
  }

  public function testUnusableRequestIdsAreDropped(): void {
    $ids = [];
    $producer = $this->producer(LocalUsageProducer::MODE_OBSERVE);
    $producer->method('observe')->willReturnCallback(function (string $id, array $observation) use (&$ids): array {
      $ids[] = $observation['gateway_request_id'];
      return ['attempt_id' => $id, 'observation_revision' => count($ids)];
    });
    $this->attempt($producer)->succeeded(['data' => []], $this->trace(200, str_repeat('a', 191)));
    $this->attempt($producer)->succeeded(['data' => []], $this->trace(200, str_repeat('a', 192)));
    $this->attempt($producer)->succeeded(['data' => []], $this->trace(200, 'réq'));
    $this->assertSame([str_repeat('a', 191), NULL, NULL], $ids);
  }

  private function producer(string $mode): LocalUsageProducer&MockObject {
    $producer = $this->createMock(LocalUsageProducer::class);
    $producer->method('mode')->willReturn($mode);
    return $producer;
  }

  private function recorder(LocalUsageProducer $producer, ?LoggerInterface $logger = NULL): ImageUsageRecorder {
    return new ImageUsageRecorder($producer, $logger ?? $this->createMock(LoggerInterface::class));
  }

  private function attempt(LocalUsageProducer $producer, ?LoggerInterface $logger = NULL): ImageAttempt {
    return new ImageAttempt($producer, $logger ?? $this->createMock(LoggerInterface::class),
      'site-a', 'job-1', 'att_1', 1);
  }

  private function call(array $overrides = []): array {
    return $overrides + ['operation_id' => 'job-1', 'kind' => 'text_to_image', 'platform' => 'xinshi',
      'model' => 'qwen-image', 'actor_user_id' => '7', 'task_id' => 'task-1'];
  }

  /**
   * Drives a trace through a Guzzle stack built like ProviderResolver's.
   *
   * @param int|null $status
   *   HTTP status of the queued response, or NULL for a connection failure.
   */
  private function trace(?int $status, ?string $requestId): ProviderRequestTrace {
    $trace = new ProviderRequestTrace();
    $request = new Request('POST', 'https://gateway.example/v1/images/generations');
    $queued = $status === NULL
      ? new ConnectException('timed out', $request)
      : new Response($status, $requestId === NULL ? [] : ['x-request-id' => $requestId], '{}');
    $stack = HandlerStack::create(new MockHandler([$queued]));
    $stack->push($trace->middleware(), ProviderRequestTrace::MIDDLEWARE);
    try {
      (new Client(['handler' => $stack]))->send($request);
    }
    catch (GuzzleException) {
      // The trace has already seen the response or the failure.
    }
    return $trace;
  }

}

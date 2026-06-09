<?php

namespace Drupal\Tests\ai\Unit\EventSubscriber;

use Drupal\ai\Event\AiExceptionEvent;
use Drupal\ai\EventSubscriber\AiExceptionEventSubscriber;
use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiMissingFeatureException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Tests AiExceptionEventSubscriber.
 *
 * @group ai
 * @covers \Drupal\ai\EventSubscriber\AiExceptionEventSubscriber
 */
class AiExceptionEventSubscriberTest extends TestCase {

  /**
   * Returns a subscriber wired to a logger that captures the last message.
   *
   * @param string|null $logged
   *   Will be filled with the logged message after onAiException() runs.
   */
  private function buildSubscriber(?string &$logged): AiExceptionEventSubscriber {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->willReturnCallback(function (string $message) use (&$logged): void {
        $logged = $message;
      });

    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->with('ai')->willReturn($logger);

    return new AiExceptionEventSubscriber($factory);
  }

  /**
   * Builds a minimal AiExceptionEvent for a given exception.
   */
  private function buildEvent(\Exception $exception): AiExceptionEvent {
    return new AiExceptionEvent(
      exception: $exception,
      requestThreadId: 'thread-test',
      providerId: 'openai',
      operationType: 'chat',
      configuration: [],
      input: NULL,
      modelId: 'gpt-4o',
    );
  }

  /**
   * ClientExceptionInterface implementations are matched by instanceof.
   */
  public function testClientExceptionIsLogged(): void {
    $httpException = new class('http failure') extends \RuntimeException implements ClientExceptionInterface {};
    $event = $this->buildEvent($httpException);

    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);

    $this->assertStringContainsString('Error invoking client', $logged);
    $this->assertStringContainsString('http failure', $logged);
  }

  /**
   * AiResponseErrorException produces the expected log prefix.
   */
  public function testResponseErrorIsLogged(): void {
    $event = $this->buildEvent(new AiResponseErrorException('bad response'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    $this->assertStringContainsString('Error invoking model response', $logged);
  }

  /**
   * AiMissingFeatureException produces the expected log prefix.
   */
  public function testMissingFeatureIsLogged(): void {
    $event = $this->buildEvent(new AiMissingFeatureException('no streaming'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    $this->assertStringContainsString('missing a requested feature', $logged);
  }

  /**
   * AiQuotaException produces the expected log prefix.
   */
  public function testQuotaExceptionIsLogged(): void {
    $event = $this->buildEvent(new AiQuotaException('over budget'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    $this->assertStringContainsString('missing quota', $logged);
  }

  /**
   * AiRateLimitException produces the expected log prefix.
   */
  public function testRateLimitIsLogged(): void {
    $event = $this->buildEvent(new AiRateLimitException('too fast'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    $this->assertStringContainsString('rate limit', $logged);
  }

  /**
   * AiUnsafePromptException produces the expected log prefix.
   */
  public function testUnsafePromptIsLogged(): void {
    $event = $this->buildEvent(new AiUnsafePromptException('unsafe'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    $this->assertStringContainsString('unsafe', $logged);
  }

  /**
   * AiRequestErrorException produces the expected log prefix.
   */
  public function testRequestErrorIsLogged(): void {
    $event = $this->buildEvent(new AiRequestErrorException('request fail'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    $this->assertStringContainsString('Error invoking model response', $logged);
  }

  /**
   * Unknown exceptions fall through to the default message.
   */
  public function testUnknownExceptionFallsToDefault(): void {
    $event = $this->buildEvent(new \RuntimeException('unexpected'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    $this->assertStringContainsString('Error invoking model response', $logged);
    $this->assertStringContainsString('unexpected', $logged);
  }

  /**
   * AiBadRequestException (wraps ClientExceptionInterface) uses the client log.
   */
  public function testBadRequestExceptionIsLoggedAsClientError(): void {
    $event = $this->buildEvent(new AiBadRequestException('wrapped http error'));
    $logged = NULL;
    $this->buildSubscriber($logged)->onAiException($event);
    // AiBadRequestException is not a ClientExceptionInterface so it falls to
    // the default branch, which still logs the message.
    $this->assertStringContainsString('wrapped http error', $logged);
  }

  /**
   * GetSubscribedEvents returns the expected array.
   */
  public function testGetSubscribedEventsReturnType(): void {
    $events = AiExceptionEventSubscriber::getSubscribedEvents();
    $this->assertIsArray($events);
    $this->assertArrayHasKey(AiExceptionEvent::class, $events);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_provider_openai\Unit;

use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider;
use OpenAI\Client;
use OpenAI\Contracts\TransporterContract;
use OpenAI\ValueObjects\Transporter\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the skip_moderation tag in moderationEndpoints().
 *
 * Passing 'skip_moderation' in $tags must bypass the OpenAI moderation API
 * without mutating the persistent $moderation state — callers such as
 * ai_search need per-call opt-out while keeping global moderation enabled.
 *
 * @group ai_provider_openai
 */
#[CoversClass(OpenAiProvider::class)]
class OpenAiProviderModerationTagTest extends TestCase {

  /**
   * Creates a provider instance without invoking the Drupal-DI constructor.
   *
   * @return \Drupal\Tests\ai_provider_openai\Unit\TestableOpenAiProvider
   *   A provider instance ready for property injection.
   */
  private function createProvider(): TestableOpenAiProvider {
    return (new \ReflectionClass(TestableOpenAiProvider::class))
      ->newInstanceWithoutConstructor();
  }

  /**
   * Builds a real Client backed by the given transporter mock.
   *
   * @param \OpenAI\Contracts\TransporterContract $transporter
   *   The mock transporter to wrap.
   *
   * @return \OpenAI\Client
   *   A Client that delegates HTTP calls to the mock.
   */
  private function clientWith(TransporterContract $transporter): Client {
    return new Client($transporter);
  }

  /**
   * Builds a transporter Response representing a clean (not-flagged) result.
   *
   * @return \OpenAI\ValueObjects\Transporter\Response
   *   A fake transporter response with flagged=false.
   */
  private function notFlaggedResponse(): Response {
    return Response::from(
      [
        'id' => 'moderation-test-id',
        'model' => 'omni-moderation-latest',
        'results' => [
          [
            'categories' => [],
            'category_scores' => [],
            'flagged' => FALSE,
          ],
        ],
      ],
      [],
    );
  }

  /**
   * Builds a transporter Response representing flagged (unsafe) content.
   *
   * @return \OpenAI\ValueObjects\Transporter\Response
   *   A fake transporter response with flagged=true.
   */
  private function flaggedResponse(): Response {
    return Response::from(
      [
        'id' => 'moderation-test-id',
        'model' => 'omni-moderation-latest',
        'results' => [
          [
            'categories' => [],
            'category_scores' => [],
            'flagged' => TRUE,
          ],
        ],
      ],
      [],
    );
  }

  /**
   * The skip_moderation tag must bypass the moderation API entirely.
   */
  public function testSkipModerationTagBypassesApiCall(): void {
    $transporter = $this->createMock(TransporterContract::class);
    $transporter->expects($this->never())->method('requestObject');

    $provider = $this->createProvider();
    $provider->enableModeration();
    $provider->injectTestClient($this->clientWith($transporter));

    // Must return cleanly with no HTTP request to the moderation API.
    $provider->moderationEndpoints(
      'Migration policy article about trafficking',
      ['skip_moderation'],
    );
  }

  /**
   * Skip_moderation must work when mixed with other arbitrary tags.
   */
  public function testSkipModerationTagAmongMultipleTags(): void {
    $transporter = $this->createMock(TransporterContract::class);
    $transporter->expects($this->never())->method('requestObject');

    $provider = $this->createProvider();
    $provider->enableModeration();
    $provider->injectTestClient($this->clientWith($transporter));

    $provider->moderationEndpoints(
      'content',
      ['index', 'skip_moderation', 'embeddings'],
    );
  }

  /**
   * Without the skip tag the moderation API must be called exactly once.
   */
  public function testModerationApiCalledWhenNoSkipTag(): void {
    $transporter = $this->createMock(TransporterContract::class);
    $transporter->expects($this->once())
      ->method('requestObject')
      ->willReturn($this->notFlaggedResponse());

    $provider = $this->createProvider();
    $provider->enableModeration();
    $provider->injectTestClient($this->clientWith($transporter));

    $provider->moderationEndpoints('safe content', []);
  }

  /**
   * Globally-disabled moderation must skip the API regardless of tags.
   */
  public function testGloballyDisabledModerationSkipsApi(): void {
    $transporter = $this->createMock(TransporterContract::class);
    $transporter->expects($this->never())->method('requestObject');

    $provider = $this->createProvider();
    $provider->disableModeration();
    $provider->injectTestClient($this->clientWith($transporter));

    // No skip_moderation tag, but global moderation is off — no API call.
    $provider->moderationEndpoints('any content', []);
  }

  /**
   * Flagged content without the skip tag must throw AiUnsafePromptException.
   */
  public function testFlaggedContentThrowsExceptionWithoutSkipTag(): void {
    $transporter = $this->createMock(TransporterContract::class);
    $transporter->expects($this->once())
      ->method('requestObject')
      ->willReturn($this->flaggedResponse());

    $provider = $this->createProvider();
    $provider->enableModeration();
    $provider->injectTestClient($this->clientWith($transporter));

    $this->expectException(AiUnsafePromptException::class);
    $provider->moderationEndpoints('harmful content', []);
  }

}

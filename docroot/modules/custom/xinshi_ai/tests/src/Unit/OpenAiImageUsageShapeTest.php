<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Service\OpenAiImageUsageShape;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OpenAI;
use OpenAI\Client as OpenAiClient;
use PHPUnit\Framework\TestCase;

/**
 * Image jobs keep their images when the gateway omits usage detail keys.
 *
 * The openai-php SDK parses /images/* usage with required int fields, so a
 * gateway response without usage.input_tokens_details.image_tokens used to
 * fail the whole job as an unretryable `internal` error even though the
 * upstream answered HTTP 200 with valid image data.
 */
final class OpenAiImageUsageShapeTest extends TestCase {

  /**
   * The production shape of 2026-10-16: image data intact, usage details
   * present but without image_tokens.
   */
  private const BODY_WITHOUT_IMAGE_TOKENS =
    '{"created":1730000000,"data":[{"b64_json":"aGk="}],"usage":{"input_tokens":16,'
    . '"output_tokens":4160,"total_tokens":4176,"input_tokens_details":{"text_tokens":16}}}';

  public function testTheSdkParsesAGatewayResponseWithoutImageTokens(): void {
    $response = $this->sdk($this->stack(new Response(200, ['x-request-id' => 'req-1'], self::BODY_WITHOUT_IMAGE_TOKENS)))
      ->images()->create(['model' => 'gpt-image-2', 'prompt' => 'Storyboard sketch.']);

    $this->assertSame(0, $response->usage->inputTokensDetails->imageTokens);
    $this->assertSame(16, $response->usage->inputTokensDetails->textTokens);
    $this->assertSame(4176, $response->usage->totalTokens);
    $this->assertSame('aGk=', $response->data[0]->b64_json);
  }

  public function testTheSameBodyWithoutTheMiddlewareStillFailsTheParse(): void {
    // Guards the test above: without the repair this is the TypeError that
    // was mapped to unretryable `internal` job failures.
    $stack = new HandlerStack(new MockHandler([new Response(200, [], self::BODY_WITHOUT_IMAGE_TOKENS)]));
    $client = $this->sdk($stack);
    // Drupal logs the undefined-key warning and lets execution continue;
    // PHPUnit would instead turn it into a test error before the TypeError.
    set_error_handler(static fn (): bool => TRUE);
    try {
      $client->images()->create(['model' => 'gpt-image-2', 'prompt' => 'Storyboard sketch.']);
      $this->fail('expected the SDK parse to fail');
    }
    catch (\TypeError) {
      $this->addToAssertionCount(1);
    }
    finally {
      restore_error_handler();
    }
  }

  public function testACompleteUsageBodyIsNotRewritten(): void {
    $body = '{"created":1,"data":[],"usage":{"input_tokens":1,"output_tokens":2,"total_tokens":3,'
      . '"input_tokens_details":{"text_tokens":1,"image_tokens":0}}}';
    $this->assertSame($body, $this->imageBody(new Response(200, [], $body)));
  }

  public function testAMissingTotalTokensBecomesTheInputPlusOutputSum(): void {
    $body = '{"created":1,"data":[],"usage":{"input_tokens":3,"output_tokens":4,'
      . '"input_tokens_details":{"text_tokens":3,"image_tokens":0}}}';
    $decoded = json_decode($this->imageBody(new Response(200, [], $body)), TRUE);
    $this->assertSame(7, $decoded['usage']['total_tokens']);
  }

  public function testResponsesWithoutUsageAreUntouched(): void {
    $body = '{"created":1,"data":[{"b64_json":"aGk="}]}';
    $this->assertSame($body, $this->imageBody(new Response(200, [], $body)));
  }

  public function testErrorBodiesAreLeftForTheTrace(): void {
    $body = '{"error":{"message":"openai_error","type":"upstream_error"}}';
    $this->assertSame($body, $this->imageBody(new Response(400, ['x-request-id' => 'req-2'], $body)));
  }

  public function testNonJsonBodiesAreUntouched(): void {
    $bytes = "\x89PNG\r\n\x1a\n-not-really-png";
    $this->assertSame($bytes, $this->imageBody(new Response(200, [], $bytes)));
  }

  public function testNonImageRequestsAreUntouched(): void {
    $body = '{"results":[]}';
    $client = new Client(['handler' => $this->stack(new Response(200, [], $body))]);
    $response = $client->post('https://gateway.example/v1/moderations');
    $this->assertSame($body, (string) $response->getBody());
  }

  /**
   * The stack the image transport uses: the shape middleware around a mock
   * transport, without Guzzle's http_errors getting in the way.
   */
  private function stack(Response $queued): HandlerStack {
    $stack = new HandlerStack(new MockHandler([$queued]));
    $stack->push(OpenAiImageUsageShape::middleware(), OpenAiImageUsageShape::MIDDLEWARE);
    return $stack;
  }

  private function imageBody(Response $queued): string {
    $client = new Client(['handler' => $this->stack($queued)]);
    return (string) $client->post('https://gateway.example/v1/images/generations')->getBody();
  }

  private function sdk(HandlerStack $stack): OpenAiClient {
    return OpenAI::factory()
      ->withApiKey('test-key')
      ->withBaseUri('https://gateway.example/v1')
      ->withHttpClient(new Client(['handler' => $stack]))
      ->make();
  }

}

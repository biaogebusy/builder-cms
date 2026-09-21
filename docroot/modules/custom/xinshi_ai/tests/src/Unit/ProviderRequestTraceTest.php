<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Service\ProviderRequestTrace;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * The transport trace decides whether an image request ever left the process (UB2.4).
 */
final class ProviderRequestTraceTest extends TestCase {

  public function testNothingIsRecordedBeforeAnImageRequest(): void {
    $trace = new ProviderRequestTrace();
    $this->assertSame('not_sent', $trace->dispatchState());
    $this->assertNull($trace->statusCode());
    $this->assertNull($trace->requestId());

    // A moderation call ahead of the image request is not the metered request.
    $this->client($trace, new Response(200, ['x-request-id' => 'mod-1'], '{}'))
      ->post('https://gateway.example/v1/moderations');
    $this->assertSame('not_sent', $trace->dispatchState());
    $this->assertNull($trace->requestId());
  }

  public function testAResponseRecordsStatusAndGatewayRequestId(): void {
    $trace = new ProviderRequestTrace();
    $this->client($trace, new Response(200, ['X-Request-Id' => ' req-42 '], '{"data":[]}'))
      ->post('https://gateway.example/v1/images/generations');
    $this->assertSame('sent', $trace->dispatchState());
    $this->assertSame(200, $trace->statusCode());
    $this->assertSame('req-42', $trace->requestId());
  }

  public function testAnHttpErrorIsStillASentRequest(): void {
    $trace = new ProviderRequestTrace();
    try {
      $this->client($trace, new Response(400, ['x-request-id' => 'req-43'], '{"error":{}}'))
        ->post('https://gateway.example/v1/images/edits');
      $this->fail('expected the http_errors middleware to throw');
    }
    catch (ClientException) {
      $this->addToAssertionCount(1);
    }
    $this->assertSame('sent', $trace->dispatchState());
    $this->assertSame(400, $trace->statusCode());
    $this->assertSame('req-43', $trace->requestId());
  }

  public function testATransportFailureLeavesTheOutcomeUnknown(): void {
    $trace = new ProviderRequestTrace();
    $request = new Request('POST', 'https://gateway.example/v1/images/generations');
    try {
      $this->client($trace, new ConnectException('timed out', $request))->send($request);
      $this->fail('expected the connection error to surface');
    }
    catch (ConnectException) {
      $this->addToAssertionCount(1);
    }
    $this->assertSame('unknown', $trace->dispatchState());
    $this->assertNull($trace->statusCode());
    $this->assertNull($trace->requestId());
  }

  public function testAnEmptyHeaderYieldsNoRequestId(): void {
    $trace = new ProviderRequestTrace();
    $this->client($trace, new Response(200, [], '{"data":[]}'))
      ->post('https://gateway.example/v1/images/generations');
    $this->assertSame('sent', $trace->dispatchState());
    $this->assertNull($trace->requestId());
  }

  /**
   * Builds the stack the way ProviderResolver does: default middleware, trace innermost.
   */
  private function client(ProviderRequestTrace $trace, Response|\Throwable $queued): Client {
    $stack = HandlerStack::create(new MockHandler([$queued]));
    $stack->push($trace->middleware(), ProviderRequestTrace::MIDDLEWARE);
    return new Client(['handler' => $stack]);
  }

}

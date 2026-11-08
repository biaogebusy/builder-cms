<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\xinshi_ai\Service\PublicHttpClientFactory;
use Drupal\xinshi_ai\Service\WebPageReader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise the actual HTML converter with synthetic transport responses. */
final class WebPageReaderTest extends TestCase {

  private function reader(Response $response, array &$history = []): WebPageReader {
    $stack = HandlerStack::create(new MockHandler([$response]));
    $stack->push(Middleware::history($history));
    $factory = $this->createMock(PublicHttpClientFactory::class);
    $factory->expects($this->once())->method('create')->with(['timeout' => 3], 1048576)
      ->willReturn(new Client(['handler' => $stack]));
    return new WebPageReader($factory);
  }

  public function testKeepsNestedExponentsAndConditionsWithNoCredentialOrSubresourceRequests(): void {
    $html = '<html><head><title>Capacity</title></head><body>' .
      '<p>Recommended, not required: same machine.</p><p>281 terabytes (2<sup><small>48</small></sup> bytes).</p>' .
      '<p>H<sub>2</sub>O; not available on Tuesday.</p><p>周三：09:00–12:00。</p>' .
      '<script src="https://example.com/private.js">secret-script</script><style>secret-style</style>' .
      '<table><tr><td rowspan="2">Limit</td><td>One writer</td></tr><tr><td>Many readers</td></tr></table>' .
      '</body></html>';
    $history = [];
    $result = $this->reader(new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], $html), $history)
      ->read('https://example.com/article');
    $this->assertSame('Capacity', $result['title']);
    $this->assertSame('html_markdown', $result['contentFormat']);
    $this->assertStringContainsString('2<sup><small>48</small></sup> bytes', $result['content']);
    $this->assertStringContainsString('H<sub>2</sub>O', $result['content']);
    $this->assertStringContainsString('Recommended, not required', $result['content']);
    $this->assertStringContainsString('not available on Tuesday', $result['content']);
    $this->assertStringContainsString('周三：09:00–12:00。', $result['content']);
    $this->assertStringContainsString('rowspan="2"', $result['content']);
    $this->assertStringNotContainsString('secret-', $result['content']);
    $this->assertCount(1, $history);
    $this->assertSame('', $history[0]['request']->getHeaderLine('Authorization'));
    $this->assertSame('', $history[0]['request']->getHeaderLine('Cookie'));
    $this->assertSame('GET', $history[0]['request']->getMethod());
  }

  #[DataProvider('unavailableResponses')]
  public function testRejectsUnavailableOrUnreadableOriginals(Response $response): void {
    $this->expectException(\RuntimeException::class);
    $this->reader($response)->read('https://example.com/article');
  }

  public static function unavailableResponses(): array {
    return [
      [new Response(404, ['Content-Type' => 'text/html'], '<p>Not found</p>')],
      [new Response(200, ['Content-Type' => 'application/pdf'], 'Not HTML')],
      [new Response(200, ['Content-Type' => 'text/html'], '<body><script>Only a script</script></body>')],
      [new Response(200, ['Content-Type' => 'text/html'], "<p>\xff</p>")],
    ];
  }

}

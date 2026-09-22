<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Service\PromptRewriteClient;
use Drupal\xinshi_ai_usage\Service\ProducerIdentity;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The worker orders the prompt pre-rewrite under the job's operation, signed as a service (UB2.6).
 */
final class PromptRewriteClientTest extends TestCase {

  use ImageJobNodeTrait;

  private const SECRET = 'shared-producer-secret';
  private const NOW = 1_700_000_000;

  private MemoryStorage $storage;
  private ConfigFactory $config;
  /** @var list<array{0:string,1:array}> */
  private array $logged = [];
  /** @var list<array{request:\Psr\Http\Message\RequestInterface}> */
  private array $history = [];

  protected function setUp(): void {
    parent::setUp();
    $this->storage = new MemoryStorage();
    $this->storage->write('xinshi_ai.settings', [
      'harness' => ['service' => ['url' => 'http://127.0.0.1:4200/', 'producer_id' => 'chat-node']],
    ]);
    $dispatcher = new EventDispatcher();
    $this->config = new ConfigFactory($this->storage, $dispatcher, $this->createMock(TypedConfigManagerInterface::class));
    $dispatcher->addSubscriber($this->config);
  }

  #[DataProvider('prompts')]
  public function testOnlyPromptsNamingLiveDataAreRewritten(string $prompt, bool $expected): void {
    $this->assertSame($expected, PromptRewriteClient::needsRewrite($prompt));
  }

  public static function prompts(): array {
    return [
      'weather' => ['上海今天的天气', TRUE],
      'relative time in English' => ['A poster for tonight', TRUE],
      'temperature' => ['Show the current temperature in Berlin', TRUE],
      'static scene' => ['非洲大草原动物世界', FALSE],
      'static English' => ['a sleeping cat on a sofa', FALSE],
    ];
  }

  #[DataProvider('serviceUrls')]
  public function testTheServiceAddressIsAnOriginWithoutAPath(string $url, bool $expected): void {
    $this->assertSame($expected, PromptRewriteClient::isServiceUrl($url));
  }

  public static function serviceUrls(): array {
    return [
      'http origin' => ['http://127.0.0.1:4200', TRUE],
      'https host' => ['https://app.example', TRUE],
      'with path' => ['http://127.0.0.1:4200/chat', FALSE],
      'with query' => ['http://127.0.0.1:4200?x=1', FALSE],
      'with credentials' => ['http://user:pw@127.0.0.1:4200', FALSE],
      'other scheme' => ['ftp://127.0.0.1', FALSE],
      'no host' => ['http://', FALSE],
    ];
  }

  public function testTheStepIsSkippedWithoutAServiceAddressOrARegisteredProducer(): void {
    $this->configure(['url' => '', 'producer_id' => 'chat-node']);
    $handler = new MockHandler([new Response(200, [], '{"content":"never"}')]);
    $client = $this->client($handler, ['chat-node' => self::SECRET]);
    $this->assertFalse($client->isConfigured());
    $this->assertNull($client->rewrite($this->job(), '上海今天的天气'));
    $this->assertCount(0, $this->history);

    // Configured, but the producer whose secret would sign is not registered.
    $this->configure(['url' => 'http://127.0.0.1:4200', 'producer_id' => 'other-node']);
    $client = $this->client($handler, ['chat-node' => self::SECRET]);
    $this->assertTrue($client->isConfigured());
    $this->assertNull($client->rewrite($this->job(), '上海今天的天气'));
    $this->assertCount(0, $this->history);
    $this->assertSame('warning', $this->logged[0][0]);
    $this->assertSame('other-node', $this->logged[0][1]['@producer']);
  }

  public function testTheOrderIsSignedWithTheProducerSecretOverTheCanonicalBody(): void {
    $handler = new MockHandler([new Response(200, [], json_encode(['content' => ' 上海今日晴，25 度 ']))]);
    $client = $this->client($handler, ['chat-node' => self::SECRET]);

    $this->assertSame('上海今日晴，25 度', $client->rewrite($this->job(['field_task_id' => 'task-1']), '上海今天的天气'));

    $this->assertCount(1, $this->history);
    $request = $this->history[0]['request'];
    $this->assertSame('POST', $request->getMethod());
    $this->assertSame('http://127.0.0.1:4200/chat/sub-steps', (string) $request->getUri());
    $body = json_decode((string) $request->getBody(), TRUE);
    $this->assertSame([
      'operation_id' => 'job-1', 'actor_user_id' => '7', 'step' => 'query-transformer', 'task_id' => 'task-1',
      'language' => 'zh-hans', 'prompt' => '上海今天的天气',
    ], $body);
    $this->assertSame('chat-node', $request->getHeaderLine(ProducerIdentity::HEADER_PRODUCER));
    $this->assertSame((string) self::NOW, $request->getHeaderLine(ProducerIdentity::HEADER_TIMESTAMP));
    $nonce = $request->getHeaderLine(ProducerIdentity::HEADER_NONCE);
    $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{16,64}\z/', $nonce);
    // The exact material Node rebuilds from the parsed body: no raw JSON bytes involved.
    $expected = ProducerIdentity::sign(self::SECRET, 'chat-node', (string) self::NOW, $nonce, 'POST', '/chat/sub-steps',
      PromptRewriteClient::canonicalBody($body));
    $this->assertSame($expected, $request->getHeaderLine(ProducerIdentity::HEADER_SIGNATURE));
    $this->assertSame("sub-step-v1\0job-1\x007\0query-transformer\0task-1\0zh-hans\0上海今天的天气",
      PromptRewriteClient::canonicalBody($body));
    $this->assertSame([], $this->logged);
  }

  public function testAnUnchangedOrEmptyAnswerIsNoRewrite(): void {
    $handler = new MockHandler([
      new Response(200, [], json_encode(['content' => '上海今天的天气'])),
      new Response(200, [], json_encode(['content' => '   '])),
      new Response(200, [], 'not json'),
    ]);
    $client = $this->client($handler, ['chat-node' => self::SECRET]);
    $this->assertNull($client->rewrite($this->job(), '上海今天的天气'));
    $this->assertNull($client->rewrite($this->job(), '上海今天的天气'));
    $this->assertNull($client->rewrite($this->job(), '上海今天的天气'));
    $this->assertCount(3, $this->history);
    // Every order carries a fresh nonce.
    $nonces = array_map(static fn(array $entry): string => $entry['request']->getHeaderLine(ProducerIdentity::HEADER_NONCE),
      $this->history);
    $this->assertCount(3, array_unique($nonces));
  }

  public function testRefusalsAndTransportFailuresKeepTheOriginalPromptAndNeverLogIt(): void {
    $handler = new MockHandler([
      new Response(409, [], json_encode(['code' => 'sub_step_budget_exceeded', 'message' => 'already dispatched'])),
      new Response(401, [], json_encode(['code' => 'unauthenticated'])),
      new Response(500, [], ''),
      new ConnectException('cURL error 7: 上海今天的天气', new Request('POST', 'http://127.0.0.1:4200/chat/sub-steps')),
    ]);
    $client = $this->client($handler, ['chat-node' => self::SECRET]);
    foreach (range(1, 4) as $_) {
      $this->assertNull($client->rewrite($this->job(), '上海今天的天气'));
    }
    $this->assertSame(['notice', 'warning', 'warning', 'warning'], array_column($this->logged, 0));
    $this->assertSame('sub_step_budget_exceeded', $this->logged[0][1]['@code']);
    $this->assertSame('unauthenticated', $this->logged[1][1]['@code']);
    $this->assertSame('http_500', $this->logged[2][1]['@code']);
    $this->assertSame('ConnectException', $this->logged[3][1]['@error']);
    foreach ($this->logged as $entry) {
      $this->assertStringNotContainsString('上海今天的天气', json_encode($entry, JSON_UNESCAPED_UNICODE));
    }
  }

  /**
   * Changes the service settings of an already-read configuration.
   *
   * A bare storage write bypasses the factory's static cache and Config::save()
   * needs the global container for cache-tag invalidation, so the write is
   * followed by a factory reset instead.
   */
  private function configure(array $service): void {
    $this->storage->write('xinshi_ai.settings', ['harness' => ['service' => $service]]);
    $this->config->reset('xinshi_ai.settings');
  }

  /**
   * @param array<string,string> $producers
   *   Registered producer ID to secret.
   */
  private function client(MockHandler $handler, array $producers): PromptRewriteClient {
    $stack = HandlerStack::create($handler);
    $stack->push(Middleware::history($this->history));
    $settings = new Settings([
      'hash_salt' => 'isolated-hash-salt',
      'xinshi_ai_usage.producers' => array_map(static fn(string $secret): array => ['secret' => $secret, 'site_id' => 'site-a'], $producers),
    ]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(self::NOW);
    $identity = new ProducerIdentity($settings, $this->createMock(KeyValueExpirableFactoryInterface::class), $time);
    $logger = $this->createMock(LoggerInterface::class);
    $logger->method('log')->willReturnCallback(function (mixed $level, string|\Stringable $message, array $context = []): void {
      $this->logged[] = [(string) $level, $context];
    });
    $logger->method('warning')->willReturnCallback(function (string|\Stringable $message, array $context = []): void {
      $this->logged[] = ['warning', $context];
    });
    $logger->method('notice')->willReturnCallback(function (string|\Stringable $message, array $context = []): void {
      $this->logged[] = ['notice', $context];
    });
    return new PromptRewriteClient($this->config, new Client(['handler' => $stack]), $identity, $time, $logger);
  }

  private function job(array $fields = []): NodeInterface {
    $job = $this->jobNode('job-1', $fields);
    $language = $this->createMock(LanguageInterface::class);
    $language->method('getId')->willReturn('zh-hans');
    $job->method('language')->willReturn($language);
    return $job;
  }

}

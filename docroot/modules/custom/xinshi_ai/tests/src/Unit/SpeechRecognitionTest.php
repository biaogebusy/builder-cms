<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai\Controller\SpeechRecognitionController;
use Drupal\xinshi_ai\Service\SpeechRecognitionClient;
use Drupal\xinshi_ai\Service\ApiKeyVault;
use Drupal\xinshi_ai_usage\Service\LocalUsageProducer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** Synthetic audio, real credential encryption, and isolated provider and usage boundaries. */
final class SpeechRecognitionTest extends TestCase {

  private MockHandler $provider;
  private ApiKeyVault $credentials;
  private SpeechRecognitionController $controller;
  private array $history = [];
  private array $intents = [];
  private array $observations = [];
  private bool $authenticated = TRUE;
  private bool $lockAvailable = TRUE;
  private bool $failIntent = FALSE;
  private string $usageMode = 'observe';
  private int $releases = 0;
  private mixed $enabled = TRUE;
  private SpeechRecognitionClient $client;

  protected function setUp(): void {
    new Settings(['hash_salt' => 'speech-fixture']);
    $private = $this->createMock(PrivateKey::class);
    $private->method('get')->willReturn('synthetic-private-key');
    $this->credentials = new ApiKeyVault(new KeyValueMemoryFactory(), $private);
    $this->credentials->set('synthetic-deepseek-key');
    $this->provider = new MockHandler();
    $stack = HandlerStack::create($this->provider);
    $stack->push(Middleware::history($this->history));
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $this->authenticated);
    $account->method('id')->willReturn('7');
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(fn() => $this->lockAvailable);
    $lock->method('release')->willReturnCallback(function () { $this->releases++; });
    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('763e2c37-7a36-4c60-847d-543260394a1b');
    $usage = $this->createMock(LocalUsageProducer::class);
    $usage->method('mode')->willReturnCallback(fn() => $this->usageMode);
    $usage->method('prepare')->willReturnCallback(function (array $intent) {
      $this->assertSame([], $this->history);
      if ($this->failIntent) throw new \RuntimeException('synthetic storage failure');
      $this->intents[] = $intent;
      return ['attempt_id' => 'att_speech'];
    });
    $usage->method('observe')->willReturnCallback(function (string $attempt, array $observation) {
      $this->assertSame('att_speech', $attempt);
      $this->observations[] = $observation;
      return ['attempt_id' => $attempt, 'observation_revision' => 1];
    });
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('speech.enabled')->willReturnCallback(fn() => $this->enabled);
    $factory = $this->createMock(ConfigFactoryInterface::class);
    $factory->method('get')->with('xinshi_ai.settings')->willReturn($config);
    $this->client = new SpeechRecognitionClient(
      new Client(['handler' => $stack]), $this->credentials, $account, $lock, $uuid,
      new NullLogger(), $factory, $usage);
    $this->controller = new SpeechRecognitionController($this->client);
  }

  protected function tearDown(): void {
    new Settings([]);
  }

  private static function wav(int $samples = 16000): string {
    return 'RIFF' . pack('V', 36 + $samples * 2) . 'WAVEfmt ' .
      pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16) . 'data' . pack('V', $samples * 2) .
      str_repeat("\0", $samples * 2);
  }

  private function call(?string $audio = NULL, string $mime = 'audio/wav') {
    return $this->controller->handle(Request::create('/api/v3/ai/speech', 'POST', [], [], [],
      ['CONTENT_TYPE' => $mime], $audio ?? self::wav()));
  }

  public function testMultipartUsesTheExistingKeyAndRecordsSecondsWithoutTranscriptOrCredits(): void {
    $this->provider->append(new Response(200, [], json_encode([
      'request_id' => 'request-1', 'text' => '今天的天气', 'duration_ms' => 1001,
      'secret' => 'synthetic-deepseek-key',
    ])));
    $response = $this->call();
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(['request_id' => 'request-1', 'text' => '今天的天气', 'duration_ms' => 1001],
      json_decode($response->getContent(), TRUE));
    $request = $this->history[0]['request'];
    $this->assertSame('https://api.deepseek.com/v1/tools/asr', (string) $request->getUri());
    $this->assertSame('Bearer synthetic-deepseek-key', $request->getHeaderLine('Authorization'));
    $this->assertStringContainsString('name="file"; filename="speech.wav"', (string) $request->getBody());
    $this->assertStringContainsString(self::wav(), (string) $request->getBody());
    $this->assertFalse($this->history[0]['options']['allow_redirects']);
    $this->assertSame('platform', $this->intents[0]['payer']);
    $this->assertSame('auxiliary', $this->intents[0]['billing_role']);
    $this->assertSame('7', $this->intents[0]['actor_user_id']);
    $this->assertSame(['duration_ms' => 1001, 'billed_seconds' => 2],
      $this->observations[0]['usage']['raw_usage']);
    $this->assertSame('succeeded', $this->observations[0]['outcome']);
    $this->assertStringNotContainsString('今天', json_encode($this->observations, JSON_UNESCAPED_UNICODE));
    $this->assertStringNotContainsString('synthetic-deepseek-key', $response->getContent());
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $this->assertSame(1, $this->releases);
  }

  public function testAvailabilityAndAuthenticationDoNotCallTheProvider(): void {
    $request = Request::create('/api/v3/ai/speech', 'GET');
    $this->assertSame(['enabled' => TRUE, 'available' => TRUE], json_decode($this->controller->handle($request)->getContent(), TRUE));
    $this->credentials->delete();
    $this->assertSame(503, $this->call()->getStatusCode());
    $this->assertSame(['enabled' => TRUE, 'available' => FALSE], json_decode($this->controller->handle($request)->getContent(), TRUE));
    $this->authenticated = FALSE;
    $this->assertSame(403, $this->call()->getStatusCode());
    $this->assertSame(403, $this->controller->handle($request)->getStatusCode());
    $this->assertSame([], $this->history);
  }


  #[DataProvider('disabledValues')]
  public function testMissingOrNonBooleanEnablementRejectsBeforeBodyValidationAndSideEffects(mixed $value): void {
    $this->enabled = $value;
    $response = $this->controller->handle(Request::create('/api/v3/ai/speech', 'GET'));
    $this->assertSame(['enabled' => FALSE, 'available' => FALSE], json_decode($response->getContent(), TRUE));
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    $response = $this->call('invalid', 'audio/webm');
    $this->assertSame(503, $response->getStatusCode());
    $this->assertSame(['code' => 'speech_disabled'], json_decode($response->getContent(), TRUE));
    $this->assertSame([], $this->history);
    $this->assertSame([], $this->intents);
    $this->assertSame(0, $this->releases);
  }

  public static function disabledValues(): array {
    return [[NULL], [FALSE], [0], [1], ['true'], ['false'], [[]]];
  }

  public function testServiceAlsoGuardsDisabledRequestsAndFreshConfigReads(): void {
    $this->assertTrue($this->client->available());
    $this->enabled = FALSE;
    $this->assertFalse($this->client->available());
    try {
      $this->client->transcribe(self::wav());
      $this->fail('A direct call must enforce the switch.');
    }
    catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
      $this->assertSame(503, $error->getStatusCode());
      $this->assertSame('speech_disabled', $error->getMessage());
    }
    $this->assertSame([], $this->history);
    $this->assertSame([], $this->intents);
    $this->enabled = TRUE;
    $this->assertTrue($this->client->available());
  }

  public function testRejectsMalformedOversizedAndOverlongAudioBeforeUsageOrProviderIo(): void {
    foreach (['', 'OggS invalid', substr(self::wav(), 0, -1), self::wav() . 'extra',
      substr_replace(self::wav(), pack('v', 2), 22, 2), self::wav(16000 * 120 + 1)] as $audio) {
      $this->assertSame(400, $this->call($audio)->getStatusCode());
    }
    $this->assertSame(413, $this->call(str_repeat('x', 8 * 1024 * 1024 + 1))->getStatusCode());
    $this->assertSame(415, $this->call(self::wav(), 'audio/webm')->getStatusCode());
    $this->assertSame([], $this->history);
    $this->assertSame([], $this->intents);
  }

  #[DataProvider('providerErrors')]
  public function testProviderFailuresDoNotReplayOrExposeBodies(int $status, int $expected): void {
    $this->provider->append(new Response($status, [], 'synthetic-deepseek-key'));
    $response = $this->call();
    $this->assertSame($expected, $response->getStatusCode());
    $this->assertCount(1, $this->history);
    $this->assertSame('failed', $this->observations[0]['outcome']);
    $this->assertNull($this->observations[0]['usage']);
    $this->assertStringNotContainsString('synthetic-deepseek-key', $response->getContent());
    $this->assertSame(1, $this->releases);
  }

  public static function providerErrors(): array {
    return [[429, 429], [503, 503], [504, 504], [400, 502], [408, 502], [401, 502], [302, 502]];
  }

  public function testNetworkUncertaintyAndInvalidProviderResultsRemainFailures(): void {
    $this->provider->append(new ConnectException('private diagnostic', new \GuzzleHttp\Psr7\Request('POST', 'https://example.invalid')));
    $this->assertSame(502, $this->call()->getStatusCode());
    $this->assertSame('unknown', $this->observations[0]['outcome']);
    $this->assertSame('unknown', $this->observations[0]['dispatch_state']);
  }

  public function testInvalidSuccessDoesNotBecomeTranscriptOrReportedUsage(): void {
    $this->provider->append(new Response(200, [], json_encode([
      'request_id' => 'id', 'text' => 'untrusted', 'duration_ms' => -1,
    ])));
    $this->assertSame(502, $this->call()->getStatusCode());
    $this->assertNull($this->observations[0]['usage']);
  }

  public function testUserConcurrencyAndEnforcedUsageStopBeforeProviderIo(): void {
    $this->lockAvailable = FALSE;
    $this->assertSame(429, $this->call()->getStatusCode());
    $this->lockAvailable = TRUE;
    $this->usageMode = 'enforce';
    $this->failIntent = TRUE;
    $this->assertSame(503, $this->call()->getStatusCode());
    $this->assertSame([], $this->history);
  }

  public function testRoutesRequireLoginAndCookieWritesRequireCsrf(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai.routing.yml');
    foreach (['status', 'transcribe'] as $action) {
      $route = $routes['xinshi_ai.speech.' . $action];
      $this->assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
      $this->assertSame(['oauth2', 'cookie'], $route['options']['_auth']);
      $this->assertTrue($route['options']['no_cache']);
    }
    $this->assertSame('TRUE', $routes['xinshi_ai.speech.transcribe']['requirements']['_csrf_request_header_token']);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai_usage\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\PhpSerialize;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\KeyValueStore\DatabaseStorageExpirable;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai_usage\Contract\UsageContractException;
use Drupal\xinshi_ai_usage\Contract\UsageEventValidator;
use Drupal\xinshi_ai_usage\Controller\UsageIngestController;
use Drupal\xinshi_ai_usage\Service\ProducerIdentity;
use Drupal\xinshi_ai_usage\Service\UsageIngestService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** Verifies the ingest contract, dedup and service identity against a real database. */
final class UsageIngestTest extends TestCase {

  private const SECRET = 'test-secret';
  private const PATH = '/api/v3/ai/metering/events';

  private Connection $database;
  private UsageIngestService $ingest;
  private UsageIngestController $controller;
  private int $now = 1_700_000_000;

  protected function setUp(): void {
    parent::setUp();
    Database::addConnectionInfo('usage_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $this->database = Database::getConnection('default', 'usage_test');
    $this->database->schema()->createTable(UsageIngestService::TABLE, xinshi_ai_usage_schema()[UsageIngestService::TABLE]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $time->method('getCurrentMicroTime')->willReturnCallback(fn() => $this->now + 0.25);
    $nonces = new DatabaseStorageExpirable('xinshi_ai_usage.nonce', new PhpSerialize(), $this->database, $time);
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->method('get')->willReturn($nonces);
    $settings = new Settings(['xinshi_ai_usage.producers' => [
      'chat-node' => ['secret' => self::SECRET, 'site_id' => 'site-a'],
      'no-site' => ['secret' => 'x'],
    ]]);
    $this->ingest = new UsageIngestService($this->database, $time, new NullLogger());
    $this->controller = new UsageIngestController(new ProducerIdentity($settings, $factory, $time), $this->ingest);
  }

  protected function tearDown(): void {
    Database::removeConnection('usage_test');
    parent::tearDown();
  }

  public function testServicesAndRouteAreWiredToTheImplementedClasses(): void {
    $module = dirname(__DIR__, 3);
    $services = Yaml::parseFile($module . '/xinshi_ai_usage.services.yml')['services'];
    $this->assertSame(ProducerIdentity::class, $services['xinshi_ai_usage.producer_identity']['class']);
    $this->assertSame(UsageIngestService::class, $services['xinshi_ai_usage.ingest']['class']);
    $route = Yaml::parseFile($module . '/xinshi_ai_usage.routing.yml')['xinshi_ai_usage.ingest'];
    $this->assertSame(self::PATH, $route['path']);
    $this->assertSame(['POST'], $route['methods']);
    $this->assertTrue($route['options']['no_cache']);
    $this->assertStringStartsWith('\\' . UsageIngestController::class, $route['defaults']['_controller']);
  }

  public function testValidatorMirrorsTheNodeContract(): void {
    $event = $this->event();
    $validated = UsageEventValidator::validate($event);
    $this->assertSame('attempt.observed', $validated['event_type']);
    $this->assertSame('120', $validated['usage']['input_tokens_total']);
    $this->assertSame(['prompt_tokens' => 120, 'completion_tokens' => 30, 'total_tokens' => 150,
      'prompt_tokens_details.cached_tokens' => 64], $validated['usage']['raw_usage']);
    $this->assertSame(1_700_000_004_120, UsageEventValidator::toMilliseconds($validated['occurred_at']));
    // Every rejection names a code the producer can quarantine on.
    $cases = [
      [['schema_version' => 2], 'unsupported_schema'],
      [['event_type' => 'operation.accepted'], 'invalid_field'],
      [['observation_revision' => '1'], 'invalid_field'],
      [['context' => ['attempt_no' => 0] + $event['context']], 'invalid_field'],
      [['context' => ['payer' => 'user'] + $event['context']], 'invalid_field'],
      [['usage' => ['input_tokens_total' => 120] + $event['usage']], 'invalid_usage'],
      [['usage' => ['raw_usage_hash' => 'nope'] + $event['usage']], 'invalid_usage'],
      [['usage' => ['quality' => 'estimated'] + $event['usage']], 'invalid_usage'],
      [['timing' => ['started_at' => 'yesterday'] + $event['timing']], 'invalid_field'],
      [['event_id' => str_repeat('x', 192)], 'invalid_field'],
      [['attempt_id' => "att\n1"], 'invalid_field'],
    ];
    foreach ($cases as [$override, $code]) {
      try {
        UsageEventValidator::validate($override + $event);
        $this->fail('expected rejection for ' . json_encode($override));
      }
      catch (UsageContractException $e) {
        $this->assertSame($code, $e->contractCode, json_encode($override));
      }
    }
    $this->assertNull(UsageEventValidator::validate(['usage' => NULL] + $event)['usage']);
  }

  public function testCanonicalHashIgnoresKeyOrderButNotContent(): void {
    $event = $this->event();
    $reordered = array_reverse($event, TRUE);
    $reordered['usage'] = array_reverse($event['usage'], TRUE);
    $this->assertSame(UsageEventValidator::payloadHash($event), UsageEventValidator::payloadHash($reordered));
    $this->assertNotSame(UsageEventValidator::payloadHash($event),
      UsageEventValidator::payloadHash(['error_code' => 'x'] + $event));
    $this->assertSame('{"a":[1,{"b":null,"c":"中/文"}],"z":1.0}',
      UsageEventValidator::canonicalJson(['z' => 1.0, 'a' => [1, ['c' => '中/文', 'b' => NULL]]]));
  }

  public function testAcceptsOnceThenReportsDuplicatesAndConflicts(): void {
    $event = $this->event();
    $this->assertSame([['event_id' => $event['event_id'], 'status' => 'accepted']],
      $this->ingest->ingest([$event], 'chat-node', 'site-a'));
    $row = $this->database->select(UsageIngestService::TABLE, 'e')->fields('e')->execute()->fetchAssoc();
    $this->assertSame('site-a', $row['site_id']);
    $this->assertSame('chat-node', $row['producer_id']);
    $this->assertSame('op-1', $row['operation_id']);
    $this->assertSame('att-1', $row['attempt_id']);
    $this->assertSame(1, (int) $row['observation_revision']);
    $this->assertSame(1_700_000_004_120, (int) $row['occurred_at']);
    $this->assertSame(($this->now + 0.25) * 1000, (float) $row['received_at']);
    $this->assertNull($row['processed_at']);
    $this->assertSame($event, json_decode($row['payload_json'], TRUE));

    // The producer's outbox redelivers the identical payload; key order is irrelevant.
    $this->assertSame([['event_id' => $event['event_id'], 'status' => 'duplicate']],
      $this->ingest->ingest([array_reverse($event, TRUE)], 'chat-node', 'site-a'));
    // Same ID with different content is refused and the original is untouched.
    $edited = ['error_code' => 'tampered'] + $event;
    $this->assertSame([['event_id' => $event['event_id'], 'status' => 'rejected', 'code' => 'payload_conflict']],
      $this->ingest->ingest([$edited], 'chat-node', 'site-a'));
    $stored = $this->database->select(UsageIngestService::TABLE, 'e')->fields('e', ['payload_json'])->execute()->fetchCol();
    $this->assertCount(1, $stored);
    $this->assertSame($event, json_decode($stored[0], TRUE));
  }

  public function testInvalidOrForeignEventsDoNotAffectAcceptedOnes(): void {
    $good = $this->event();
    $other = ['event_id' => 'att-1:prepared:0', 'event_type' => 'attempt.prepared',
      'observation_revision' => 0, 'usage' => NULL, 'outcome' => NULL, 'dispatch_state' => 'not_sent'] + $good;
    $receipts = $this->ingest->ingest([
      ['event_id' => 'bad-1', 'schema_version' => 1],
      $good,
      'not-an-object',
      ['producer_id' => 'someone-else'] + $good,
      ['site_id' => 'site-b'] + $good,
      $other,
    ], 'chat-node', 'site-a');
    $this->assertSame([
      ['event_id' => 'bad-1', 'status' => 'rejected', 'code' => 'invalid_field'],
      ['event_id' => $good['event_id'], 'status' => 'accepted'],
      ['event_id' => '', 'status' => 'rejected', 'code' => 'invalid_event'],
      ['event_id' => $good['event_id'], 'status' => 'rejected', 'code' => 'producer_mismatch'],
      ['event_id' => $good['event_id'], 'status' => 'rejected', 'code' => 'site_mismatch'],
      ['event_id' => 'att-1:prepared:0', 'status' => 'accepted'],
    ], $receipts);
    $this->assertSame(2, (int) $this->database->select(UsageIngestService::TABLE)->countQuery()->execute()->fetchField());
  }

  public function testControllerRequiresAValidFreshSignature(): void {
    $body = json_encode(['events' => [$this->event()]]);
    $ok = $this->controller->ingest($this->signed($body));
    $this->assertSame(200, $ok->getStatusCode());
    $decoded = json_decode($ok->getContent(), TRUE);
    $this->assertSame([['event_id' => 'att-1:observed:1', 'status' => 'accepted']], $decoded['results']);
    $this->assertNotEmpty($decoded['request_id']);
    $this->assertTrue($ok->headers->hasCacheControlDirective('no-store'));

    $refused = [
      'no headers' => Request::create(self::PATH, 'POST', [], [], [], [], $body),
      'wrong secret' => $this->signed($body, secret: 'other'),
      'unknown producer' => $this->signed($body, producer: 'ghost'),
      'producer without site' => $this->signed($body, producer: 'no-site', secret: 'x'),
      'stale timestamp' => $this->signed($body, timestamp: (string) ($this->now - 301)),
      'future timestamp' => $this->signed($body, timestamp: (string) ($this->now + 301)),
      'body changed after signing' => $this->signed($body, sentBody: $body . ' '),
      'other path' => $this->signed($body, path: '/api/v3/ai/metering/other'),
      'malformed nonce' => $this->signed($body, nonce: 'short'),
    ];
    foreach ($refused as $label => $request) {
      $response = $this->controller->ingest($request);
      $this->assertSame(401, $response->getStatusCode(), $label);
      $decoded = json_decode($response->getContent(), TRUE);
      $this->assertSame('unauthenticated', $decoded['code'], $label);
      $this->assertFalse($decoded['retryable'], $label);
      $this->assertArrayHasKey('request_id', $decoded, $label);
    }
    // A captured request cannot be replayed even inside the time window.
    $request = $this->signed($body, nonce: 'replay-nonce-0000000001');
    $this->assertSame(200, $this->controller->ingest($request)->getStatusCode());
    $replay = $this->controller->ingest($this->signed($body, nonce: 'replay-nonce-0000000001'));
    $this->assertSame(401, $replay->getStatusCode());
    // Only the signed batch was stored, once.
    $this->assertSame(1, (int) $this->database->select(UsageIngestService::TABLE)->countQuery()->execute()->fetchField());
  }

  public function testControllerRejectsMalformedBatchesWithoutStoringAnything(): void {
    $cases = [
      ['not json', 400, 'invalid_request'],
      [json_encode(['events' => 'x']), 400, 'invalid_request'],
      [json_encode([$this->event()]), 400, 'invalid_request'],
      [json_encode(['events' => array_fill(0, 101, $this->event())]), 400, 'too_many_events'],
      [str_repeat(' ', UsageIngestController::MAX_BODY_BYTES + 1), 413, 'batch_too_large'],
    ];
    foreach ($cases as [$body, $status, $code]) {
      $response = $this->controller->ingest($this->signed($body));
      $this->assertSame($status, $response->getStatusCode(), $code);
      $decoded = json_decode($response->getContent(), TRUE);
      $this->assertSame($code, $decoded['code']);
      $this->assertFalse($decoded['retryable']);
    }
    $this->assertSame(0, (int) $this->database->select(UsageIngestService::TABLE)->countQuery()->execute()->fetchField());
    $response = $this->controller->ingest($this->signed(json_encode(['events' => []])));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame([], json_decode($response->getContent(), TRUE)['results']);
  }

  private static int $nonceCounter = 0;

  private function signed(string $body, string $producer = 'chat-node', string $secret = self::SECRET,
    ?string $timestamp = NULL, ?string $nonce = NULL, string $path = self::PATH, ?string $sentBody = NULL): Request {
    $timestamp ??= (string) $this->now;
    $nonce ??= 'nonce-' . str_pad((string) ++self::$nonceCounter, 16, '0', STR_PAD_LEFT);
    $signature = ProducerIdentity::sign($secret, $producer, $timestamp, $nonce, 'POST', $path, $body);
    return Request::create(self::PATH, 'POST', [], [], [], [
      'HTTP_X_XINSHI_PRODUCER' => $producer,
      'HTTP_X_XINSHI_TIMESTAMP' => $timestamp,
      'HTTP_X_XINSHI_NONCE' => $nonce,
      'HTTP_X_XINSHI_SIGNATURE' => $signature,
      'CONTENT_TYPE' => 'application/json',
    ], $sentBody ?? $body);
  }

  /** The documented `attempt.observed` example, as the Node producer emits it. */
  private function event(): array {
    return [
      'schema_version' => 1,
      'event_id' => 'att-1:observed:1',
      'producer_id' => 'chat-node',
      'site_id' => 'site-a',
      'event_type' => 'attempt.observed',
      'operation_id' => 'op-1',
      'authorization_id' => NULL,
      'attempt_id' => 'att-1',
      'logical_call_id' => 'lc-1',
      'observation_revision' => 1,
      'occurred_at' => '2023-11-14T22:13:24.120Z',
      'context' => ['billing_account_id' => NULL, 'actor_user_id' => '7', 'feature' => 'common',
        'stage' => 'classifier', 'attempt_no' => 1, 'payer' => 'platform',
        'chat_run_id' => 'run-1', 'task_id' => 'task-1', 'chat_id' => 'chat-1'],
      'provider' => ['account_ref' => 'xinshi', 'requested_model' => 'model-a',
        'resolved_model' => 'model-a', 'gateway_request_id' => 'gw-1', 'provider_request_id' => NULL],
      'dispatch_state' => 'sent',
      'outcome' => 'succeeded',
      'error_code' => NULL,
      'usage' => ['normalizer_version' => 'chat-completions-inclusive-v1', 'quality' => 'reported',
        'input_tokens_total' => '120', 'input_tokens_cache_read' => '64', 'input_tokens_cache_write' => NULL,
        'output_tokens_total' => '30', 'output_tokens_reasoning' => NULL, 'provider_total_tokens' => '150',
        'images_generated' => NULL, 'raw_usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30,
          'total_tokens' => 150, 'prompt_tokens_details.cached_tokens' => 64],
        'raw_usage_hash' => str_repeat('a', 64)],
      'timing' => ['started_at' => '2023-11-14T22:13:20.000Z', 'first_token_at' => '2023-11-14T22:13:20.480Z',
        'finished_at' => '2023-11-14T22:13:24.100Z'],
    ];
  }

}

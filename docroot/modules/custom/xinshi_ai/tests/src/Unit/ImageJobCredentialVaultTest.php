<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai\Service\ImageJobCredentialVault;
use PHPUnit\Framework\TestCase;

/**
 * Customer keys of image jobs live only in the encrypted, expiring vault (UB2.7).
 */
final class ImageJobCredentialVaultTest extends TestCase {

  private const JOB = '0f1e2d3c-0000-4000-8000-000000000001';

  /** @var array<string, array{value: string, expire: int}> */
  private array $stored = [];
  private int $now = 1_700_000_000;
  private ImageJobCredentialVault $vault;

  protected function setUp(): void {
    parent::setUp();
    new Settings(['hash_salt' => 'isolated-hash-salt']);
    $this->vault = $this->vaultWith('isolated-site-private-key');
  }

  public function testSecretsArePulledOutOfTheRequestAndNeverStayInParams(): void {
    // Current clients: a top-level credentials object.
    $params = ['n' => 2, 'size' => '1024x1024'];
    $credentials = ImageJobCredentialVault::fromPayload([
      'credentials' => ['endpoint' => ' https://api.example/v1/images/generations ', 'apiKey' => ' sk-live-1 '],
    ], $params);
    $this->assertSame(['endpoint' => 'https://api.example/v1/images/generations', 'api_key' => 'sk-live-1'],
      $credentials);
    $this->assertSame(['n' => 2, 'size' => '1024x1024'], $params);

    // Older clients: connection fields inside params, in either spelling.
    $params = ['n' => 1, 'endpoint' => 'https://api.example/v1/images/generations', 'api_key' => 'sk-old',
      'apiKey' => 'ignored-when-api_key-present', 'baseURL' => 'https://api.example', 'model' => 'dall-e-3'];
    $credentials = ImageJobCredentialVault::fromPayload([], $params);
    $this->assertSame('sk-old', $credentials['api_key']);
    $this->assertSame(['n' => 1, 'model' => 'dall-e-3'], $params, 'model stays, every connection field goes');

    $params = [];
    $this->assertSame(['endpoint' => NULL, 'api_key' => NULL], ImageJobCredentialVault::fromPayload([], $params));
  }

  public function testOnlyTheCustomPlatformNeedsCredentialsAndTheyMustBeUsable(): void {
    $ok = ['endpoint' => 'https://api.example/v1/images/generations', 'api_key' => 'sk-live-1'];
    $this->assertNull(ImageJobCredentialVault::validate($ok, 'custom'));
    $this->assertNull(ImageJobCredentialVault::validate(['endpoint' => NULL, 'api_key' => NULL], 'xinshi'));
    $this->assertStringContainsString('endpoint', (string) ImageJobCredentialVault::validate(
      ['endpoint' => NULL, 'api_key' => 'sk'], 'custom'));
    $this->assertStringContainsString('endpoint', (string) ImageJobCredentialVault::validate(
      ['endpoint' => 'api.example/v1', 'api_key' => 'sk'], 'custom'));
    $this->assertStringContainsString('endpoint', (string) ImageJobCredentialVault::validate(
      ['endpoint' => 'ftp://api.example/v1', 'api_key' => 'sk'], 'custom'));
    $this->assertStringContainsString('apiKey is required', (string) ImageJobCredentialVault::validate(
      ['endpoint' => 'https://api.example/v1', 'api_key' => NULL], 'custom'));
    $this->assertStringContainsString('printable ASCII', (string) ImageJobCredentialVault::validate(
      ['endpoint' => 'https://api.example/v1', 'api_key' => "sk\nline"], 'custom'));
    $this->assertStringContainsString('printable ASCII', (string) ImageJobCredentialVault::validate(
      ['endpoint' => 'https://api.example/v1', 'api_key' => str_repeat('k', 4097)], 'custom'));
  }

  public function testStoredSecretsAreEncryptedBoundToTheJobAndExpire(): void {
    $this->vault->store(self::JOB, 'https://api.example/v1/images/generations', 'sk-live-1');

    $this->assertSame(['endpoint' => 'https://api.example/v1/images/generations', 'api_key' => 'sk-live-1'],
      $this->vault->get(self::JOB));
    $this->assertNull($this->vault->get('other-job'));
    $raw = $this->stored[self::JOB];
    $this->assertStringNotContainsString('sk-live-1', $raw['value']);
    $this->assertStringNotContainsString('api.example', base64_decode($raw['value']));
    $this->assertSame(ImageJobCredentialVault::TTL_SECONDS, $raw['expire']);

    // Bound to the job: the same ciphertext under another job id does not decrypt.
    $this->stored['other-job'] = $raw;
    $this->assertNull($this->vault->get('other-job'));
    // Another site key (rotated private key) cannot read it either.
    $this->assertNull($this->vaultWith('rotated-private-key')->get(self::JOB));

    $this->vault->delete(self::JOB);
    $this->assertNull($this->vault->get(self::JOB));
    $this->assertArrayNotHasKey(self::JOB, $this->stored);
  }

  public function testTheKeyIsRedactedFromTextsButShortValuesAreLeftAlone(): void {
    $this->vault->store(self::JOB, 'https://api.example/v1', 'sk-live-secret-1');
    $this->assertSame('401 Unauthorized: key [redacted] rejected (Bearer [redacted])',
      $this->vault->redact(self::JOB, '401 Unauthorized: key sk-live-secret-1 rejected (Bearer sk-live-secret-1)'));
    $this->assertSame('nothing to do', $this->vault->redact(self::JOB, 'nothing to do'));
    $this->assertSame('', $this->vault->redact(self::JOB, ''));
    $this->assertSame('key sk-live-secret-1', $this->vault->redact('unknown-job', 'key sk-live-secret-1'));

    $this->vault->store('short', 'https://api.example/v1', 'abc');
    $this->assertSame('abc is in every abcdef', $this->vault->redact('short', 'abc is in every abcdef'));
  }

  public function testLegacyCredentialsAreScrubbedFromStoredParamsInBatches(): void {
    Database::addConnectionInfo('credential_scrub_test', 'default', [
      'driver' => 'sqlite', 'database' => ':memory:',
      'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
    ]);
    $database = Database::getConnection('default', 'credential_scrub_test');
    try {
      $this->createParamsTable($database, 'node__field_params');
      $secret = json_encode(['n' => 2, 'endpoint' => 'https://api.example/v1', 'api_key' => 'sk-old',
        'model' => 'dall-e-3']);
      $clean = json_encode(['n' => 4, 'size' => '1024x1024']);
      $this->insertParams($database, 'node__field_params', 1, 10, 'image_job', $secret);
      $this->insertParams($database, 'node__field_params', 2, 20, 'image_job', $clean);
      $this->insertParams($database, 'node__field_params', 3, 30, 'image_job', $secret);
      $this->insertParams($database, 'node__field_params', 4, 40, 'article', $secret);
      $this->insertParams($database, 'node__field_params', 5, 50, 'image_job', 'not json');

      $first = xinshi_ai_scrub_credentials_batch($database, 'node__field_params', 0, 2);
      $this->assertSame(['last_revision' => 20, 'updated' => 1, 'done' => FALSE], $first);
      // Revision 40 belongs to another bundle and is never part of a batch.
      $second = xinshi_ai_scrub_credentials_batch($database, 'node__field_params', 20, 2);
      $this->assertSame(['last_revision' => 50, 'updated' => 1, 'done' => FALSE], $second);
      $third = xinshi_ai_scrub_credentials_batch($database, 'node__field_params', 50, 2);
      $this->assertSame(['last_revision' => 50, 'updated' => 0, 'done' => TRUE], $third);

      $values = $database->select('node__field_params', 'p')
        ->fields('p', ['entity_id', 'field_params_value'])
        ->orderBy('entity_id')
        ->execute()
        ->fetchAllKeyed();
      $this->assertSame('{"n":2,"model":"dall-e-3"}', $values[1]);
      $this->assertSame($clean, $values[2]);
      $this->assertSame('{"n":2,"model":"dall-e-3"}', $values[3]);
      $this->assertSame($secret, $values[4], 'other bundles are not touched');
      $this->assertSame('not json', $values[5]);
      // A second full pass changes nothing.
      $this->assertSame(0, xinshi_ai_scrub_credentials_batch($database, 'node__field_params', 0, 100)['updated']);
    }
    finally {
      Database::removeConnection('credential_scrub_test');
    }
  }

  private function vaultWith(string $privateKey): ImageJobCredentialVault {
    $key = $this->createMock(PrivateKey::class);
    $key->method('get')->willReturn($privateKey);
    $store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $store->method('setWithExpire')->willReturnCallback(function (string $name, mixed $value, int $expire): void {
      $this->stored[$name] = ['value' => $value, 'expire' => $expire];
    });
    $store->method('get')->willReturnCallback(fn(string $name): mixed => $this->stored[$name]['value'] ?? NULL);
    $store->method('delete')->willReturnCallback(function (string $name): void {
      unset($this->stored[$name]);
    });
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->method('get')->with(ImageJobCredentialVault::COLLECTION)->willReturn($store);
    return new ImageJobCredentialVault($factory, $key);
  }

  private function createParamsTable(Connection $database, string $table): void {
    $database->schema()->createTable($table, [
      'fields' => [
        'bundle' => ['type' => 'varchar_ascii', 'length' => 128, 'not null' => TRUE, 'default' => ''],
        'deleted' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
        'entity_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'revision_id' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'langcode' => ['type' => 'varchar_ascii', 'length' => 32, 'not null' => TRUE, 'default' => ''],
        'delta' => ['type' => 'int', 'unsigned' => TRUE, 'not null' => TRUE],
        'field_params_value' => ['type' => 'text', 'size' => 'big', 'not null' => TRUE],
        'field_params_format' => ['type' => 'varchar_ascii', 'length' => 255, 'not null' => FALSE],
      ],
      'primary key' => ['entity_id', 'deleted', 'delta', 'langcode'],
    ]);
  }

  private function insertParams(Connection $database, string $table, int $entityId, int $revisionId,
    string $bundle, string $value): void {
    $database->insert($table)->fields([
      'bundle' => $bundle, 'deleted' => 0, 'entity_id' => $entityId, 'revision_id' => $revisionId,
      'langcode' => 'en', 'delta' => 0, 'field_params_value' => $value, 'field_params_format' => 'plain_text',
    ])->execute();
  }

}

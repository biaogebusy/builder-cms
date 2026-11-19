<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai\Controller\SupplierConfigurationController;
use Drupal\xinshi_ai\Service\ApiKeyVault;
use Drupal\xinshi_ai\Service\SupplierConfiguration;
use Drupal\xinshi_ai_runtime\Service\ServiceIdentity;
use Drupal\xinshi_ai_runtime\Service\ServiceRequestAuthentication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** Real crypto, config exports and signed dispatch with synthetic stores. */
final class SupplierConfigurationTest extends TestCase {

  private ConfigFactory $config;
  private ApiKeyVault $vault;
  private KeyValueMemoryFactory $keys;
  private SupplierConfigurationController $controller;
  private int $now = 1700000000;

  protected function setUp(): void {
    $settings = new Settings(['hash_salt' => 'synthetic-supplier-salt',
      'xinshi_ai_service.secrets' => ['chat-node' => 'synthetic-service-secret']]);
    $storage = new MemoryStorage();
    $storage->write('xinshi_ai.settings', ['gateway' => ['base_url' => 'https://gateway.test'],
      'suppliers' => ['aliyun' => ['base_url' => 'https://aliyun.test/compatible-mode/v1']]]);
    $dispatcher = new EventDispatcher();
    $this->config = new ConfigFactory($storage, $dispatcher, $this->createMock(TypedConfigManagerInterface::class));
    $dispatcher->addSubscriber($this->config);
    $container = new ContainerBuilder();
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    \Drupal::setContainer($container);
    $this->keys = new KeyValueMemoryFactory();
    $private = $this->createMock(PrivateKey::class);
    $private->method('get')->willReturn('synthetic-private-key');
    $this->vault = new ApiKeyVault($this->keys, $private, 'xinshi_ai.suppliers');
    $this->vault->set('synthetic-gateway-key', 'xinshi');
    $this->vault->set('synthetic-aliyun-key', 'aliyun');
    $nonces = [];
    $store = $this->createMock(KeyValueStoreExpirableInterface::class);
    $store->method('setWithExpireIfNotExists')->willReturnCallback(function ($id, $value, $expiry) use (&$nonces): bool {
      $this->assertSame(600, $expiry);
      if (isset($nonces[$id])) return FALSE;
      return $nonces[$id] = TRUE;
    });
    $factory = $this->createMock(KeyValueExpirableFactoryInterface::class);
    $factory->method('get')->with('xinshi_ai_service.nonce')->willReturn($store);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturnCallback(fn() => $this->now);
    $this->controller = new SupplierConfigurationController(new ServiceRequestAuthentication(
      new ServiceIdentity($settings), $factory, $time), new SupplierConfiguration($this->config, $this->vault, $settings));
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    new Settings([]);
  }

  private function request(string $secret = 'synthetic-service-secret', ?string $nonce = NULL): Request {
    $request = Request::create('/api/v3/ai/service/suppliers');
    $timestamp = (string) $this->now;
    $nonce ??= bin2hex(random_bytes(16));
    $request->headers->set(ServiceIdentity::HEADER_PRODUCER, 'chat-node');
    $request->headers->set(ServiceIdentity::HEADER_TIMESTAMP, $timestamp);
    $request->headers->set(ServiceIdentity::HEADER_NONCE, $nonce);
    $request->headers->set(ServiceIdentity::HEADER_SIGNATURE, ServiceIdentity::sign($secret, 'chat-node',
      $timestamp, $nonce, 'GET', '/api/v3/ai/service/suppliers', ''));
    return $request;
  }

  public function testSignedSnapshotIncludesBothPlatformsAndAtomicPayerSelection(): void {
    $response = $this->controller->read($this->request());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('https://gateway.test/v1', $data['platforms']['xinshi']['baseURL']);
    $this->assertSame('synthetic-aliyun-key', $data['platforms']['aliyun']['apiKey']);
    $this->assertSame(['ref' => 'aliyun', 'payer' => 'platform'], $data['platforms']['aliyun']['account']);
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
  }

  public function testAnonymousUserHeadersWrongPathAndReplayCannotReadKeys(): void {
    $requests = [Request::create('/api/v3/ai/service/suppliers'), $this->request('usage-only-secret')];
    $cookie = Request::create('/api/v3/ai/service/suppliers');
    $cookie->headers->set('Cookie', 'session=synthetic');
    $cookie->headers->set('Authorization', 'Bearer synthetic');
    $requests[] = $cookie;
    $foreign = $this->request();
    $foreign->headers->set(ServiceIdentity::HEADER_SIGNATURE, str_repeat('0', 64));
    $requests[] = $foreign;
    foreach ($requests as $request) {
      $reply = $this->controller->read($request);
      $this->assertSame(401, $reply->getStatusCode());
      $this->assertStringNotContainsString('synthetic', $reply->getContent());
      $this->assertTrue($reply->headers->hasCacheControlDirective('no-store'));
    }
    $signed = $this->request();
    $this->assertSame(200, $this->controller->read($signed)->getStatusCode());
    $this->assertSame(401, $this->controller->read($signed)->getStatusCode());
    $expired = $this->request();
    $this->now += 301;
    $this->assertSame(401, $this->controller->read($expired)->getStatusCode());
  }

  public function testUnexpectedQueriesAreRejectedBeforeConsumingTheSignatureNonce(): void {
    $request = $this->request();
    $request->query->set('extra', 'input');
    $this->assertSame(400, $this->controller->read($request)->getStatusCode());
    $request->query->remove('extra');
    $this->assertSame(200, $this->controller->read($request)->getStatusCode());
  }

  public function testCiphertextExportRotationAndRecovery(): void {
    $this->config->getEditable('xinshi_ai.settings')->set('gateway.api_key', 'synthetic-legacy')->save();
    xinshi_ai_migrate_supplier_key($this->config, $this->vault);
    $export = Yaml::dump($this->config->getEditable('xinshi_ai.settings')->getRawData());
    $this->assertStringNotContainsString('api_key', $export);
    $this->assertStringNotContainsString('synthetic', $export);
    $this->assertSame('synthetic-gateway-key', $this->vault->get('xinshi'));
    $this->assertStringNotContainsString('synthetic', json_encode($this->keys->get('xinshi_ai.suppliers')->getAll()));
    $this->keys->get('xinshi_ai.suppliers')->set('xinshi', $this->keys->get('xinshi_ai.suppliers')->get('aliyun'));
    $failed = $this->controller->read($this->request());
    $this->assertSame(503, $failed->getStatusCode());
    $this->assertStringNotContainsString('synthetic', $failed->getContent());
    $this->vault->set('synthetic-recovered', 'xinshi');
    $this->assertSame(200, $this->controller->read($this->request())->getStatusCode());
    $this->vault->delete('aliyun');
    $data = json_decode($this->controller->read($this->request())->getContent(), TRUE);
    $this->assertArrayNotHasKey('aliyun', $data['platforms']);
  }

  public function testUpgradeIsIdempotentAndPreservesLegacySourceOnStorageFailure(): void {
    $this->vault->delete('xinshi');
    $this->config->getEditable('xinshi_ai.settings')->set('gateway.api_key', 'synthetic-legacy')->save();
    xinshi_ai_migrate_supplier_key($this->config, $this->vault);
    xinshi_ai_migrate_supplier_key($this->config, $this->vault);
    $this->assertSame('synthetic-legacy', $this->vault->get('xinshi'));
    $this->keys->get('xinshi_ai.suppliers')->set('xinshi', 'corrupt');
    $this->config->getEditable('xinshi_ai.settings')->set('gateway.api_key', 'synthetic-keep')->save();
    try {
      xinshi_ai_migrate_supplier_key($this->config, $this->vault);
      $this->fail('Expected migration refusal');
    }
    catch (\RuntimeException) {
      $this->assertSame('synthetic-keep', $this->config->getEditable('xinshi_ai.settings')->get('gateway.api_key'));
    }
  }

  public function testDeploymentOverridesDoNotExportAndEmptyOverridesDisableStoredKeys(): void {
    $settings = new Settings(Settings::getAll() + ['xinshi_ai.supplier_keys' => [
      'xinshi' => 'synthetic-deployment', 'aliyun' => '',
    ]]);
    $suppliers = new SupplierConfiguration($this->config, $this->vault, $settings);
    $this->assertSame('synthetic-deployment', $suppliers->apiKey('xinshi'));
    $this->assertFalse($suppliers->hasKey('aliyun'));
    $this->assertSame('synthetic-gateway-key', $this->vault->get('xinshi'));
    $this->assertStringNotContainsString('synthetic-deployment', Yaml::dump($this->config->getEditable('xinshi_ai.settings')->getRawData()));
  }

}

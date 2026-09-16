<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Form\FormState;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\xinshi_ai\Controller\FigmaController;
use Drupal\xinshi_ai\Controller\HarnessSettingsController;
use Drupal\xinshi_ai\Exception\FigmaException;
use Drupal\xinshi_ai\Form\SettingsForm;
use Drupal\xinshi_ai\Service\FigmaConnector;
use Drupal\xinshi_ai\Service\FigmaVault;
use Drupal\xinshi_ai\Service\ProductDocuments;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** Uses real encryption, memory configuration and mocked HTTP, never a site database. */
final class FigmaConnectorTest extends TestCase {

  private const CALLBACK = 'https://builder.example/chat/figma/callback';
  private ConfigFactory $factory;
  private TypedConfigManagerInterface $typed;
  private MemoryStorage $storage;
  private FigmaVault $vault;
  private KeyValueStoreInterface $values;
  private FigmaConnector $connector;
  private MockHandler $http;
  private array $history = [];
  private string $uid = '1';
  private int $now = 1790000000;
  private bool $locked = FALSE;

  protected function setUp(): void {
    new Settings(['hash_salt' => 'isolated-figma-test-salt']);
    $this->storage = new MemoryStorage();
    $this->storage->write('xinshi_ai.settings', ['harness' => ['mcp' => ['figma' => [
      'enabled' => TRUE, 'client_id' => 'test-client', 'redirect_uri' => self::CALLBACK,
    ]]]]);
    $this->typed = $this->createMock(TypedConfigManagerInterface::class);
    $dispatcher = new EventDispatcher();
    $this->factory = new ConfigFactory($this->storage, $dispatcher, $this->typed);
    $dispatcher->addSubscriber($this->factory);
    $keys = new KeyValueMemoryFactory();
    $this->values = $keys->get('xinshi_ai.figma');
    $privateKey = $this->createMock(PrivateKey::class);
    $privateKey->method('get')->willReturn('isolated-site-private-key');
    $this->vault = new FigmaVault($keys, $privateKey);
    $this->vault->set('client_secret', ['value' => 'app-secret']);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturnCallback(fn() => $this->uid);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $this->uid !== '0');
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(function (): bool {
      if ($this->locked) return FALSE;
      return $this->locked = TRUE;
    });
    $lock->method('release')->willReturnCallback(function (): void { $this->locked = FALSE; });
    $this->http = new MockHandler();
    $stack = HandlerStack::create($this->http);
    $stack->push(Middleware::history($this->history));
    $this->connector = new FigmaConnector($this->factory, $account, $this->vault,
      new Client(['handler' => $stack]), $lock, $time);
    $container = new ContainerBuilder();
    $container->set('config.factory', $this->factory);
    $container->set('xinshi_ai.figma_vault', $this->vault);
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $translation = $this->createMock(TranslationInterface::class);
    $translation->method('translateString')->willReturnCallback(
      fn(TranslatableMarkup $markup) => $markup->getUntranslatedString());
    $container->set('string_translation', $translation);
    $documents = $this->createMock(ProductDocuments::class);
    $documents->method('availableContentTypes')->willReturn([]);
    $container->set('xinshi_ai.product_documents', $documents);
    \Drupal::setContainer($container);
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    new Settings([]);
  }

  private function response(array $body, int $status = 200): Response {
    return new Response($status, ['Content-Type' => 'application/json'], json_encode($body));
  }

  private function grant(int $expires = 3600): array {
    $auth = $this->connector->authorize('/builder/chat?type=fullHTML&uuid=chat-1');
    $this->http->append($this->response([
      'access_token' => 'access-' . $this->uid, 'refresh_token' => 'refresh-' . $this->uid,
      'expires_in' => $expires, 'user_id_string' => 'figma-' . $this->uid,
    ]), $this->response(['id' => 'figma-' . $this->uid, 'handle' => 'user-' . $this->uid]));
    $result = $this->connector->complete($auth['state'], 'authorization-code', FALSE);
    $this->assertTrue($result['connected']);
    return $auth;
  }

  public function testPersonalOAuthUsesPkceAndNeverReturnsTokens(): void {
    $auth = $this->grant();
    parse_str(parse_url($auth['url'], PHP_URL_QUERY), $params);
    $this->assertSame('https://www.figma.com/oauth', strtok($auth['url'], '?'));
    $this->assertSame('file_content:read current_user:read', $params['scope']);
    $this->assertSame('S256', $params['code_challenge_method']);
    $exchange = $this->history[0]['request'];
    $this->assertSame('https://api.figma.com/v1/oauth/token', (string) $exchange->getUri());
    $this->assertSame('Basic ' . base64_encode('test-client:app-secret'), $exchange->getHeaderLine('Authorization'));
    parse_str((string) $exchange->getBody(), $form);
    $this->assertSame(self::CALLBACK, $form['redirect_uri']);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $form['code_verifier'], TRUE)), '+/', '-_'), '=');
    $this->assertSame($challenge, $params['code_challenge']);
    $this->assertFalse($this->history[0]['options']['allow_redirects']);
    $this->assertSame(['available' => TRUE, 'connected' => TRUE, 'handle' => 'user-1',
      'redirectUri' => self::CALLBACK], $this->connector->status());
    $raw = json_encode($this->values->getAll());
    foreach (['app-secret', 'access-1', 'refresh-1', $auth['state']] as $secret) {
      $this->assertStringNotContainsString($secret, $raw);
    }
    $this->expectExceptionMessage('figma_authorization_expired');
    $this->connector->complete($auth['state'], 'replay-code', FALSE);
  }

  public function testStateIsBoundToCurrentUserAndExpiresOrIsReplaced(): void {
    $auth = $this->connector->authorize('/builder/chat');
    $this->uid = '2';
    try { $this->connector->complete($auth['state'], 'code', FALSE); $this->fail('Cross-user state accepted'); }
    catch (FigmaException $error) { $this->assertSame('figma_authorization_expired', $error->error); }
    $this->uid = '1';
    $result = $this->connector->complete($auth['state'], NULL, TRUE);
    $this->assertSame('figma_authorization_failed', $result['error']);
    $old = $this->connector->authorize('/builder/chat');
    $current = $this->connector->authorize('/builder/chat');
    try { $this->connector->complete($old['state'], 'code', FALSE); $this->fail('Old state accepted'); }
    catch (FigmaException $error) { $this->assertSame('figma_authorization_expired', $error->error); }
    $this->now += 601;
    $this->expectExceptionMessage('figma_authorization_expired');
    $this->connector->complete($current['state'], 'code', FALSE);
  }

  public function testUsersReadWithTheirOwnGrantAndDisconnectCancelsPendingAuthorization(): void {
    $this->grant();
    $this->uid = '2';
    $this->grant();
    $this->http->append($this->response(['images' => []]));
    $this->connector->read('/v1/files/ABCdef123/images');
    $this->assertSame('Bearer access-2', $this->history[4]['request']->getHeaderLine('Authorization'));
    $pending = $this->connector->authorize('/builder/chat');
    $this->connector->disconnect();
    $this->assertFalse($this->connector->status()['connected']);
    $this->uid = '1';
    $this->assertTrue($this->connector->status()['connected']);
    $this->uid = '2';
    $this->expectExceptionMessage('figma_authorization_expired');
    $this->connector->complete($pending['state'], 'code', FALSE);
  }

  public function testCiphertextCannotBeMovedBetweenAccountsAndAppChangesRequireReconnect(): void {
    $this->grant();
    $this->values->set('connection.2', $this->values->get('connection.1'));
    $this->uid = '2';
    $this->assertFalse($this->connector->status()['connected']);
    $this->uid = '1';
    $pending = $this->connector->authorize('/builder/chat');
    $this->factory->getEditable('xinshi_ai.settings')->set('harness.mcp.figma.client_id', 'replacement-app')->save();
    $this->assertFalse($this->connector->status()['connected']);
    $this->expectExceptionMessage('figma_authorization_expired');
    $this->connector->complete($pending['state'], 'code', FALSE);
  }

  public function testRefreshRetainsRefreshTokenAndRevocationRemovesTheGrant(): void {
    $this->grant(10);
    $this->http->append($this->response(['access_token' => 'fresh', 'expires_in' => 3600]),
      $this->response(['images' => []]));
    $this->connector->read('/v1/files/ABCdef123/images');
    $this->assertSame('https://api.figma.com/v1/oauth/refresh', (string) $this->history[2]['request']->getUri());
    $this->assertSame('refresh_token=refresh-1', (string) $this->history[2]['request']->getBody());
    $this->assertSame('Bearer fresh', $this->history[3]['request']->getHeaderLine('Authorization'));
    $this->assertSame('refresh-1', $this->vault->get('connection.1')['refresh_token']);
    $this->now += 3601;
    $this->http->append($this->response(['error' => 'private provider detail'], 400));
    try { $this->connector->read('/v1/files/ABCdef123/images'); $this->fail('Revoked grant accepted'); }
    catch (FigmaException $error) { $this->assertSame('figma_connection_required', $error->error); }
    $this->assertFalse($this->connector->status()['connected']);
  }

  public function testExpiredTokenRetriesOnceWhileFileAclFailurePreservesTheGrant(): void {
    $this->grant();
    $this->http->append($this->response(['err' => 'Invalid token'], 403),
      $this->response(['access_token' => 'fresh', 'expires_in' => 3600]),
      $this->response(['images' => []]), $this->response(['err' => 'File not allowed'], 403));
    $this->connector->read('/v1/files/ABCdef123/images');
    try { $this->connector->read('/v1/files/ABCdef123/images'); $this->fail('ACL denied file accepted'); }
    catch (FigmaException $error) { $this->assertSame('figma_file_unavailable', $error->error); }
    $this->assertTrue($this->connector->status()['connected']);
    $this->assertCount(6, $this->history);
    $this->http->append($this->response([], 401),
      $this->response(['access_token' => 'fresh-again', 'expires_in' => 3600]), $this->response([], 401));
    try { $this->connector->read('/v1/files/ABCdef123/images'); $this->fail('Repeated invalid token accepted'); }
    catch (FigmaException $error) { $this->assertSame('figma_connection_required', $error->error); }
    $this->assertFalse($this->connector->status()['connected']);
  }

  public function testPerUserLockPreventsConcurrentTokenChanges(): void {
    $this->grant();
    $this->http->append(function () {
      try { $this->connector->disconnect(); $this->fail('Concurrent token mutation accepted'); }
      catch (FigmaException $error) { $this->assertSame('figma_busy', $error->error); }
      return $this->response(['images' => []]);
    });
    $this->connector->read('/v1/files/ABCdef123/images');
    $this->connector->disconnect();
    $this->assertFalse($this->connector->status()['connected']);
    $this->assertFalse($this->locked);
  }

  public function testOnlyBoundedDesignReadEndpointsAreAllowed(): void {
    $this->assertSame('/v1/files/ABCdef123/nodes?ids=1%3A2',
      FigmaConnector::readPath('/v1/files/ABCdef123/nodes?ids=1:2'));
    $this->assertSame('/v1/files/ABC_def-123/nodes?ids=1%3A2',
      FigmaConnector::readPath('/v1/files/ABC_def-123/nodes?ids=1:2'));
    $this->assertStringContainsString('I1%3A2%3B3%3A4', FigmaConnector::readPath(
      '/v1/images/ABCdef123?ids=I1:2;3:4&format=png&scale=2&version=v1'));
    foreach (['https://api.figma.com/v1/me', '//evil.example/v1/files/ABCdef123/images',
      '/v1/me', '/v1/oauth/token', '/v1/files/ABCdef123/nodes?ids[]=1:2',
      '/v1/files/ABCdef123/nodes?ids=1:2&extra=1',
      '/v1/images/ABCdef123?ids=1:2&format=svg&scale=2&version=v1',
      '/v1/images/ABCdef123?ids=' . implode(',', array_fill(0, 33, '1:2')) . '&format=png&scale=2&version=v1',
    ] as $path) {
      try { FigmaConnector::readPath($path); $this->fail('Unsafe path accepted: ' . $path); }
      catch (FigmaException $error) { $this->assertSame('figma_invalid_url', $error->error); }
    }
    $this->assertNull(FigmaConnector::origin('https://user:pass@builder.example/chat/figma/callback'));
    $this->assertNull(FigmaConnector::origin('http://builder.example/chat/figma/callback'));
    $this->assertSame('http://localhost:4200', FigmaConnector::origin('http://localhost:4200/chat/figma/callback'));
  }

  public function testControllerRejectsIdentityOverridesAndSanitizesProviderFailures(): void {
    $controller = new FigmaController($this->connector);
    $request = fn(array $body) => new Request(content: json_encode($body));
    $response = $controller->handle($request(['operation' => 'status', 'uid' => '2']));
    $this->assertSame(400, $response->getStatusCode());
    $this->uid = '0';
    $this->assertSame(401, $controller->handle($request(['operation' => 'status']))->getStatusCode());
    $this->uid = '1';
    $this->grant();
    $this->http->append(new \RuntimeException('provider error with app-secret and refresh-1'));
    $response = $controller->handle($request(['operation' => 'read', 'path' => '/v1/files/ABCdef123/images']));
    $this->assertSame(['code' => 'figma_unavailable'], json_decode($response->getContent(), TRUE));
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    $this->http->append(new Response(200, ['Content-Length' => 5000000], '{}'));
    $this->assertSame(413, $controller->handle($request(['operation' => 'read',
      'path' => '/v1/files/ABCdef123/images']))->getStatusCode());
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai.routing.yml');
    $this->assertSame(['oauth2'], $routes['xinshi_ai.figma']['options']['_auth']);
    $this->assertSame('TRUE', $routes['xinshi_ai.figma']['requirements']['_user_is_logged_in']);
    $this->assertSame(['POST'], $routes['xinshi_ai.figma']['methods']);
    $this->assertSame('administer xinshi_ai', $routes['xinshi_ai.settings']['requirements']['_permission']);
  }

  public function testAdminFormStoresOnlyPublicSettingsAndPreservesOrClearsTheEncryptedSecret(): void {
    $form = new SettingsForm($this->factory, $this->typed);
    $state = (new FormState())->setValues(['harness' => ['mcp' => ['figma' => [
      'enabled' => 1, 'client_id' => 'test-client', 'client_secret' => 'replacement-secret',
      'redirect_uri' => self::CALLBACK,
    ]]]]);
    $elements = $form->buildForm([], $state);
    $secret = $elements['harness']['mcp']['figma']['client_secret'];
    $this->assertSame('password', $secret['#type']);
    $this->assertArrayNotHasKey('#default_value', $secret);
    $form->submitForm($elements, $state);
    $stored = $this->storage->read('xinshi_ai.settings');
    $this->assertSame(['enabled' => TRUE, 'client_id' => 'test-client', 'redirect_uri' => self::CALLBACK],
      $stored['harness']['mcp']['figma']);
    $this->assertStringNotContainsString('replacement-secret', json_encode($stored));
    $this->assertSame('replacement-secret', $this->vault->get('client_secret')['value']);
    $this->assertStringNotContainsString('figma', (new HarnessSettingsController())->read()->getContent());
    $state->setValue(['harness', 'mcp', 'figma', 'client_secret'], '');
    $form->submitForm($elements, $state);
    $this->assertSame('replacement-secret', $this->vault->get('client_secret')['value']);
    $state->setValue(['harness', 'mcp', 'figma', 'clear_secret'], 1);
    $form->validateForm($elements, $state);
    $this->assertArrayHasKey('harness][mcp][figma][client_secret', $state->getErrors());
    $state->setValue(['harness', 'mcp', 'figma', 'enabled'], 0);
    $form->submitForm($elements, $state);
    $this->assertFalse($this->vault->has('client_secret'));
    $this->assertFalse($this->connector->status()['available']);
  }

}

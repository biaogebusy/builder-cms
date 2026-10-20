<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Config\MemoryStorage;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Site\Settings;
use Drupal\xinshi_ai\Controller\WebSearchController;
use Drupal\xinshi_ai\Service\WebSearchClient;
use Drupal\xinshi_ai\Service\WebSearchCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

/** Real encryption and request validation with synthetic storage and provider responses. */
final class WebSearchTest extends TestCase {

  private WebSearchCredentials $credentials;
  private KeyValueMemoryFactory $keys;
  private ConfigFactory $config;
  private MockHandler $http;
  private WebSearchController $controller;
  private array $history = [];
  private bool $authenticated = TRUE;

  protected function setUp(): void {
    new Settings(['hash_salt' => 'web-search-test-salt']);
    $this->keys = new KeyValueMemoryFactory();
    $private = $this->createMock(PrivateKey::class);
    $private->method('get')->willReturn('synthetic-site-private-key');
    $this->credentials = new WebSearchCredentials($this->keys, $private);
    $this->credentials->set('synthetic-deepseek-key');
    $dispatcher = new EventDispatcher();
    $this->config = new ConfigFactory(new MemoryStorage(), $dispatcher,
      $this->createMock(TypedConfigManagerInterface::class));
    $dispatcher->addSubscriber($this->config);
    $container = new ContainerBuilder();
    $container->set('cache_tags.invalidator', $this->createMock(CacheTagsInvalidatorInterface::class));
    \Drupal::setContainer($container);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('isAuthenticated')->willReturnCallback(fn() => $this->authenticated);
    $this->http = new MockHandler();
    $stack = HandlerStack::create($this->http);
    $stack->push(Middleware::history($this->history));
    $this->controller = new WebSearchController(new WebSearchClient(
      new Client(['handler' => $stack]), $this->credentials, $this->config, $account));
  }

  protected function tearDown(): void {
    \Drupal::unsetContainer();
    new Settings([]);
  }

  private function call(string $operation, mixed $input): \Symfony\Component\HttpFoundation\JsonResponse {
    return $this->controller->read(Request::create('/api/v3/ai/web/' . $operation, 'POST',
      [], [], [], [], json_encode($input)), $operation);
  }

  public function testEncryptionRotationDeletionAndTamperRejection(): void {
    $stored = $this->keys->get('xinshi_ai.web_search');
    $this->assertStringNotContainsString('synthetic-deepseek-key', json_encode($stored->getAll()));
    $this->assertSame('synthetic-deepseek-key', $this->credentials->get());
    $this->credentials->set('replacement-key');
    $this->assertSame('replacement-key', $this->credentials->get());
    $stored->set('api_key', 'corrupt');
    $this->assertSame(503, $this->call('search', ['query' => 'news'])->getStatusCode());
    $this->credentials->delete();
    $this->assertFalse($this->credentials->has());
    $this->assertSame(503, $this->call('search', ['query' => 'news'])->getStatusCode());
    $this->assertSame([], $this->history);
  }

  public function testSearchUsesTheOfficialEndpointAndKeepsSourcesWithoutSecrets(): void {
    $this->http->append(new Response(200, [], json_encode(['results' => [[
      'title' => 'News', 'url' => 'https://example.com/article', 'summary' => 'Fact',
      'snippet' => 'Snippet', 'unexpected' => 'synthetic-deepseek-key',
    ]]])));
    $response = $this->call('search', ['query' => ' news ', 'count' => 2]);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('https://api.deepseek.com/v1/tools/search', (string) $this->history[0]['request']->getUri());
    $this->assertSame('Bearer synthetic-deepseek-key', $this->history[0]['request']->getHeaderLine('Authorization'));
    $this->assertSame(['query' => 'news', 'count' => 2, 'need_content' => FALSE],
      json_decode((string) $this->history[0]['request']->getBody(), TRUE));
    $this->assertStringContainsString('example.com', $response->getContent());
    $this->assertStringNotContainsString('synthetic-deepseek-key', $response->getContent());
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
  }

  public function testFetchBoundsContentAndReportsTruncation(): void {
    $this->http->append(new Response(200, [], json_encode(['title' => 'Page', 'content' => str_repeat('文', 24001)])));
    $response = $this->call('fetch', ['url' => 'https://example.com/article']);
    $data = json_decode($response->getContent(), TRUE);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(24000, mb_strlen($data['content']));
    $this->assertTrue($data['truncated']);
    $this->assertSame('https://example.com/article', $data['url']);
    $this->assertSame('https://api.deepseek.com/v1/tools/fetch', (string) $this->history[0]['request']->getUri());
  }

  public function testEachToolCanBeDisabledIndependentlyWithoutLeakingARequest(): void {
    foreach (['search', 'fetch'] as $disabled) {
      $this->config->getEditable('xinshi_ai.settings')
        ->set('harness.tools.disabled', ['web_' . $disabled])->save();
      $input = $disabled === 'search' ? ['query' => 'news'] : ['url' => 'https://example.com'];
      $this->assertSame(403, $this->call($disabled, $input)->getStatusCode());
      $other = $disabled === 'search' ? 'fetch' : 'search';
      $this->http->append(new Response(200, [], json_encode($other === 'search'
        ? ['results' => []] : ['title' => '', 'content' => 'Text'])));
      $this->assertSame(200, $this->call($other, $other === 'search'
        ? ['query' => 'news'] : ['url' => 'https://example.com'])->getStatusCode());
    }
    $this->assertCount(2, $this->history);
    $this->authenticated = FALSE;
    $this->assertSame(403, $this->call('search', ['query' => 'news'])->getStatusCode());
    $this->assertCount(2, $this->history);
  }

  public static function invalidInputs(): array {
    return [
      ['search', ['query' => ' ']], ['search', ['query' => str_repeat('字', 129)]],
      ['search', ['query' => 'news', 'count' => '2']], ['search', ['query' => 'news', 'count' => NULL]],
      ['search', ['query' => 'news', 'count' => 11]], ['search', ['query' => 'news', 'key' => 'forged']],
      ['fetch', ['url' => 'file:///etc/passwd']], ['fetch', ['url' => 'https://user:pass@example.com']],
      ['search', []], ['invalid', ['query' => 'news']],
    ];
  }

  #[DataProvider('invalidInputs')]
  public function testInvalidInputDoesNotReachProvider(string $operation, mixed $input): void {
    $this->assertSame(400, $this->call($operation, $input)->getStatusCode());
    $this->assertSame([], $this->history);
  }

  public function testProviderFailuresAndOversizedOrMalformedResponsesAreSanitized(): void {
    foreach ([new Response(401, [], 'synthetic-deepseek-key'), new Response(200, [], '{invalid'),
      new Response(200, [], str_repeat('x', 1048577)), new Response(200, [], '{}')] as $response) {
      $this->http->append($response);
      $result = $this->call('search', ['query' => 'news']);
      $this->assertSame(502, $result->getStatusCode());
      $this->assertSame(['code' => 'web_search_provider_failed'], json_decode($result->getContent(), TRUE));
    }
    $this->http->append(new Response(429, [], 'private body'));
    $this->assertSame(429, $this->call('search', ['query' => 'news'])->getStatusCode());
  }

  public function testRouteRequiresOAuthAndLogin(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai.routing.yml');
    $route = $routes['xinshi_ai.web_search'];
    $this->assertSame(['oauth2'], $route['options']['_auth']);
    $this->assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
    $this->assertSame('search|fetch', $route['requirements']['operation']);
  }

}

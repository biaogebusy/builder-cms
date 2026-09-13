<?php

declare(strict_types=1);

namespace Drupal\Tests\xinshi_ai\Unit;

use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_ai\Controller\ProductDocumentsMcpController;
use Drupal\xinshi_ai\Service\ProductDocuments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

final class ProductDocumentsMcpTest extends TestCase {

  private ProductDocuments $documents;
  private ProductDocumentsMcpController $controller;

  protected function setUp(): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(TRUE);
    $this->documents = $this->createMock(ProductDocuments::class);
    $this->documents->method('toolNames')->willReturn(['search_product_documents', 'get_product_document']);
    $this->controller = new ProductDocumentsMcpController($this->documents, $account);
  }

  private function request(array $message, array $headers = [], string $method = 'POST'): Response {
    $request = Request::create('/api/v3/ai/mcp/product-documents', $method, [], [], [], [], json_encode($message));
    $request->headers->replace($headers + ['Accept' => 'application/json, text/event-stream', 'Content-Type' => 'application/json']);
    return $this->controller->handle($request);
  }

  public function testInitializationNotificationDiscoveryAndReadOnlyResult(): void {
    $response = $this->request(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
      'params' => ['protocolVersion' => '2025-11-25', 'clientInfo' => ['name' => 'test', 'version' => '1.0.0']]]);
    $init = json_decode($response->getContent(), TRUE);
    $this->assertSame('2025-11-25', $init['result']['protocolVersion']);
    $this->assertSame('xinshi-product-documents', $init['result']['serverInfo']['name']);
    $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    $this->assertFalse($response->headers->has('MCP-Session-Id'));
    $this->assertSame(202, $this->request(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->getStatusCode());
    $list = json_decode($this->request(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])->getContent(), TRUE);
    $this->assertCount(2, $list['result']['tools']);
    $this->assertTrue($list['result']['tools'][0]['annotations']['readOnlyHint']);
    $value = ['documents' => [['title' => '产品资料']], 'nextPage' => NULL];
    $this->documents->expects($this->once())->method('call')
      ->with('search_product_documents', ['query' => '产品', 'language' => 'zh-hans'])->willReturn($value);
    $result = json_decode($this->request(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
      'params' => ['name' => 'search_product_documents', 'arguments' => ['query' => '产品', 'language' => 'zh-hans']]])->getContent(), TRUE)['result'];
    $this->assertFalse($result['isError']);
    $this->assertSame($value, json_decode($result['content'][0]['text'], TRUE));
  }

  public function testTransportRefusesCrossOriginAndUnsupportedRequests(): void {
    $message = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'];
    $this->assertSame(403, $this->request($message, ['Origin' => 'https://other.example.test'])->getStatusCode());
    $this->assertSame(405, $this->request($message, [], 'GET')->getStatusCode());
    $this->assertSame(405, $this->request($message, [], 'DELETE')->getStatusCode());
    $this->assertSame(406, $this->request($message, ['Accept' => 'application/json'])->getStatusCode());
    $this->assertSame(400, $this->request($message, ['MCP-Protocol-Version' => 'unknown'])->getStatusCode());
    $this->assertSame(413, $this->request($message + ['padding' => str_repeat('x', 17000)])->getStatusCode());
  }

  public function testOnlyOAuthAuthenticatedReadToolsAreExposed(): void {
    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/xinshi_ai.routing.yml');
    $route = $routes['xinshi_ai.product_documents.mcp'];
    $this->assertSame(['oauth2'], $route['options']['_auth']);
    $this->assertSame('TRUE', $route['requirements']['_user_is_logged_in']);
    $this->assertTrue($route['options']['no_cache']);
    $this->documents->expects($this->never())->method('call');
    $response = $this->request(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'create_page']]);
    $this->assertSame(-32602, json_decode($response->getContent(), TRUE)['error']['code']);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAuthenticated')->willReturn(FALSE);
    $this->controller = new ProductDocumentsMcpController($this->documents, $account);
    $this->assertSame(401, $this->request(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'])->getStatusCode());
  }

  public static function failures(): array {
    return [[new \DomainException('not_found'), 'not_found'], [new \DomainException('changed'), 'changed'],
      [new \InvalidArgumentException('secret'), 'invalid_input'], [new \RuntimeException('secret'), 'unavailable']];
  }

  #[DataProvider('failures')]
  public function testToolFailuresDoNotExposeInternalErrors(\Throwable $error, string $code): void {
    $this->documents->method('call')->willThrowException($error);
    $response = $this->request(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
      'params' => ['name' => 'get_product_document', 'arguments' => ['id' => 'test']]]);
    $result = json_decode($response->getContent(), TRUE)['result'];
    $this->assertTrue($result['isError']);
    $this->assertSame(['code' => $code], json_decode($result['content'][0]['text'], TRUE));
    $this->assertStringNotContainsString('secret', $response->getContent());
  }

}

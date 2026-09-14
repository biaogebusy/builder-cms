<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\xinshi_ai\Service\ProductDocuments;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** Stateless, authenticated Streamable HTTP endpoint for two read-only tools. */
final class ProductDocumentsMcpController extends ControllerBase {

  private const VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];
  private const HEADERS = ['Cache-Control' => 'private, no-store'];

  public function __construct(
    private readonly ProductDocuments $documents,
    private readonly AccountInterface $account,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai.product_documents'), $container->get('current_user'));
  }

  public function handle(Request $request): Response {
    if (!$this->account->isAuthenticated()) {
      return new Response('', 401, self::HEADERS);
    }
    $origin = $request->headers->get('Origin');
    if ($origin !== NULL && $origin !== $request->getSchemeAndHttpHost()) {
      return new Response('', 403, self::HEADERS);
    }
    // No server-initiated messages or transport sessions are used by this source.
    if (!$request->isMethod('POST')) {
      return new Response('', 405, self::HEADERS + ['Allow' => 'POST']);
    }
    $accept = $request->headers->get('Accept', '');
    if (!str_contains($accept, 'application/json') || !str_contains($accept, 'text/event-stream')) {
      return new Response('', 406, self::HEADERS);
    }
    if (strtolower(explode(';', $request->headers->get('Content-Type', ''))[0]) !== 'application/json') {
      return new Response('', 415, self::HEADERS);
    }
    $version = $request->headers->get('MCP-Protocol-Version');
    if ($version !== NULL && !in_array($version, self::VERSIONS, TRUE)) {
      return $this->error(NULL, -32600, 'Unsupported protocol version', 400);
    }
    if (strlen($request->getContent()) > 16384) {
      return new Response('', 413, self::HEADERS);
    }
    try {
      $message = json_decode($request->getContent(), TRUE, 32, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      return $this->error(NULL, -32700, 'Parse error', 400);
    }
    if (!is_array($message) || ($message['jsonrpc'] ?? NULL) !== '2.0' ||
        !is_string($message['method'] ?? NULL) || strlen($message['method']) > 100 ||
        (array_key_exists('id', $message) && !is_int($message['id']) && !is_string($message['id']))) {
      return $this->error(NULL, -32600, 'Invalid request', 400);
    }
    if (!array_key_exists('id', $message)) {
      return new Response('', 202, self::HEADERS);
    }
    $id = $message['id'];
    $params = $message['params'] ?? [];
    if (!is_array($params) || ($params && array_is_list($params))) {
      return $this->error($id, -32602, 'Invalid params');
    }
    switch ($message['method']) {
      case 'initialize':
        if (!is_string($params['protocolVersion'] ?? NULL) || !is_array($params['clientInfo'] ?? NULL) ||
            !is_string($params['clientInfo']['name'] ?? NULL)) {
          return $this->error($id, -32602, 'Invalid initialize params');
        }
        return $this->result($id, [
          'protocolVersion' => in_array($params['protocolVersion'], self::VERSIONS, TRUE)
            ? $params['protocolVersion'] : self::VERSIONS[0],
          'capabilities' => ['tools' => ['listChanged' => FALSE]],
          'serverInfo' => ['name' => 'xinshi-product-documents', 'version' => '1.0.0'],
        ]);

      case 'ping':
        return $this->result($id, (object) []);

      case 'tools/list':
        try {
          return $this->result($id, ['tools' => array_map($this->definition(...), $this->documents->toolNames())]);
        }
        catch (\Throwable) {
          return $this->error($id, -32603, 'Product documents unavailable');
        }

      case 'tools/call':
        $name = $params['name'] ?? NULL;
        $arguments = $params['arguments'] ?? [];
        if (!in_array($name, ['search_product_documents', 'get_product_document'], TRUE) ||
            !is_array($arguments) || ($arguments && array_is_list($arguments))) {
          return $this->error($id, -32602, 'Invalid tool call');
        }
        $failed = FALSE;
        try {
          $value = $this->documents->call($name, $arguments);
        }
        catch (\InvalidArgumentException) {
          $value = ['code' => 'invalid_input'];
          $failed = TRUE;
        }
        catch (\DomainException $error) {
          $value = ['code' => in_array($error->getMessage(), ['disabled', 'not_found', 'changed', 'processing', 'parse_failed'], TRUE)
            ? $error->getMessage() : 'unavailable'];
          $failed = TRUE;
        }
        catch (\Throwable) {
          $value = ['code' => 'unavailable'];
          $failed = TRUE;
        }
        return $this->result($id, [
          'content' => [['type' => 'text', 'text' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]],
          'isError' => $failed,
        ]);

      default:
        return $this->error($id, -32601, 'Method not found');
    }
  }

  private function definition(string $name): array {
    $search = $name === 'search_product_documents';
    return [
      'name' => $name,
      'description' => $search ? 'Search permitted published product documents in the requested language.'
        : 'Read published source text and parsed attachments. Continue with nextOffset, revisionId and snapshot when provided.',
      'annotations' => ['readOnlyHint' => TRUE, 'destructiveHint' => FALSE, 'openWorldHint' => FALSE],
      'inputSchema' => [
        'type' => 'object', 'additionalProperties' => FALSE,
        'properties' => ($search ? [
          'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200],
          'page' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000],
        ] : [
          'id' => ['type' => 'string', 'format' => 'uuid'],
          'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000000],
          'revision' => ['type' => 'string', 'pattern' => '^\\d{1,32}$'],
          'snapshot' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
        ]) + ['language' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 35]],
        'required' => $search ? ['query', 'language'] : ['id', 'language'],
      ],
    ];
  }

  private function result(string|int $id, array|object $result): JsonResponse {
    return new JsonResponse(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result], 200, self::HEADERS);
  }

  private function error(string|int|null $id, int $code, string $message, int $status = 200): JsonResponse {
    return new JsonResponse(['jsonrpc' => '2.0', 'id' => $id,
      'error' => ['code' => $code, 'message' => $message]], $status, self::HEADERS);
  }

}

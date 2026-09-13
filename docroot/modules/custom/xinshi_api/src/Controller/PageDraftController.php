<?php

namespace Drupal\xinshi_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_api\PageDraftException;
use Drupal\xinshi_api\PageDraftService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Authenticated draft operations and read-only page/receipt lookup. */
final class PageDraftController extends ControllerBase {

  public function __construct(private readonly PageDraftService $drafts) {}

  public static function create(ContainerInterface $container) {
    return new static($container->get('xinshi_api.page_drafts'));
  }

  public function capabilities(): JsonResponse {
    return $this->response([
      'uid' => (string) $this->currentUser()->id(),
      'permissions' => $this->drafts->capabilities(),
    ]);
  }

  public function createDraft(string $execution_id, Request $request): JsonResponse {
    return $this->write($execution_id, $request, 'createDraft');
  }

  public function changeDraft(string $execution_id, Request $request): JsonResponse {
    return $this->write($execution_id, $request, 'changeDraft');
  }

  public function readDraft(string $page_id): JsonResponse {
    try {
      return $this->response($this->drafts->readDraft($page_id));
    }
    catch (PageDraftException $e) {
      return $this->response(['code' => $e->reason], $e->httpStatus);
    }
    catch (\Throwable $e) {
      return $this->response(['code' => 'operation_unavailable'], 503);
    }
  }

  private function write(string $execution_id, Request $request, string $method): JsonResponse {
    try {
      if (strlen($request->getContent()) > 1048576) {
        throw new PageDraftException('invalid_input', 422);
      }
      try {
        $input = json_decode($request->getContent(), FALSE, 64, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException $e) {
        throw new PageDraftException('invalid_input', 422);
      }
      return $this->response($this->drafts->{$method}($execution_id, $input));
    }
    catch (PageDraftException $e) {
      $body = ['executionId' => $execution_id, 'code' => $e->reason];
      if (in_array($e->reason, ['invalid_input', 'permission_denied', 'page_not_found',
        'not_draft', 'version_conflict', 'draft_not_supported'], TRUE)) {
        $body['outcome'] = 'not_written';
      }
      return $this->response($body, $e->httpStatus);
    }
    catch (\Throwable $e) {
      // Exceptions can contain database values. Do not expose them to clients or tools.
      return $this->response(['code' => 'operation_unavailable'], 503);
    }
  }

  public function findDraft(string $execution_id): JsonResponse {
    try {
      $result = $this->drafts->findDraft($execution_id);
      return $result ? $this->response($result) : $this->response(['code' => 'not_found'], 404);
    }
    catch (PageDraftException $e) {
      return $this->response(['code' => $e->reason], $e->httpStatus);
    }
    catch (\Throwable $e) {
      return $this->response(['code' => 'operation_unavailable'], 503);
    }
  }

  private function response(array $data, int $status = 200): JsonResponse {
    return new JsonResponse($data, $status, ['Cache-Control' => 'no-store, private']);
  }

}

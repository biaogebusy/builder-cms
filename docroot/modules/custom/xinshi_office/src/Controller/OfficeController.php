<?php

declare(strict_types=1);

namespace Drupal\xinshi_office\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\xinshi_office\OfficeAccess;
use Drupal\xinshi_office\OfficeDocuments;
use Drupal\xinshi_office\OfficeException;
use Drupal\xinshi_office\OfficeFiles;
use Drupal\xinshi_office\OfficeInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Thin authenticated transport; storage and validation live in Office services. */
final class OfficeController implements ContainerInjectionInterface {

  public function __construct(
    private readonly OfficeAccess $access,
    private readonly OfficeDocuments $documents,
    private readonly OfficeFiles $files,
  ) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_office.access'),
      $container->get('xinshi_office.documents'), $container->get('xinshi_office.files'));
  }

  /** Lists capabilities for the current effective account. */
  public function capabilities(): JsonResponse {
    return new JsonResponse($this->access->capabilities());
  }

  /** Lists a bounded metadata page. */
  public function listing(Request $request): JsonResponse {
    return new JsonResponse($this->documents->listing($request->query->get('after'), $request->query->get('chatId')));
  }

  /** Creates an owner-bound working copy and first revision. */
  public function createDocument(Request $request): JsonResponse {
    return new JsonResponse($this->documents->write(NULL, OfficeInput::read($request)));
  }

  /** Compares the expected revision before atomically saving a replacement snapshot. */
  public function update(string $document_id, Request $request): JsonResponse {
    return new JsonResponse($this->documents->write($document_id, OfficeInput::read($request)));
  }

  /** Reads one current or historical working copy. */
  public function read(string $document_id, Request $request): JsonResponse {
    return new JsonResponse($this->documents->read($document_id, $this->number($request, 'revision')));
  }

  /** Reads version metadata separately from historical bodies. */
  public function revisions(string $document_id, Request $request): JsonResponse {
    return new JsonResponse($this->documents->revisions($document_id, $this->number($request, 'before')));
  }

  /** Queries an existing receipt; absence is not proof that a concurrent request cannot commit. */
  public function find(string $operation_id): JsonResponse {
    $result = $this->documents->find($operation_id);
    return new JsonResponse($result ?? ['code' => 'operation_not_found'], $result ? 200 : 404);
  }

  /** Accepts one multipart upload and a small explicit metadata object. */
  public function upload(Request $request): JsonResponse {
    $file = $request->files->get('file');
    $metadata = $request->request->get('metadata');
    if (count($request->files->all()) !== 1 || count($request->request->all()) !== 1 ||
      !$file instanceof UploadedFile || !$file->isValid() || !is_string($metadata) || strlen($metadata) > 2048) {
      throw new OfficeException('invalid_upload', 400);
    }
    try {
      $input = json_decode($metadata, FALSE, 4, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException) {
      throw new OfficeException('invalid_upload', 400);
    }
    if (!$input instanceof \stdClass) throw new OfficeException('invalid_upload', 400);
    return new JsonResponse($this->files->upload($input, $file->getPathname()));
  }

  /** Recovers upload metadata without exposing the private storage URI or serving unbound bytes. */
  public function metadata(string $file_id): JsonResponse {
    return new JsonResponse($this->files->metadata($file_id));
  }

  /** Downloads immutable bytes only through an authorized document revision. */
  public function download(string $document_id, string $file_id, Request $request): BinaryFileResponse {
    $file = $this->documents->file($document_id, $file_id, $this->number($request, 'revision'));
    $response = new BinaryFileResponse($file['path'], 200, ['Content-Type' => $file['mime'],
      'X-Content-Type-Options' => 'nosniff'], FALSE);
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['name'], 'office-file');
    return $response;
  }

  private function number(Request $request, string $key): ?int {
    $value = $request->query->get($key);
    if ($value === NULL) return NULL;
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,9}$/D', $value) || (int) $value > 2147483647) {
      throw new OfficeException('invalid_input', 400);
    }
    return (int) $value;
  }

}


<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\xinshi_ai_reference\ChatSessionFormat;
use Drupal\xinshi_ai_reference\ChatSessionFormatException;
use Drupal\xinshi_ai_reference\SessionFieldsWriter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/** Accepts a strict operation patch rather than a stale replacement DTO. */
final class SessionFieldsController extends ControllerBase {

  public function __construct(private readonly SessionFieldsWriter $writer) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('xinshi_ai_reference.fields_writer'));
  }

  public function update(string $chat_uuid, string $uuid, Request $request): JsonResponse {
    try {
      $input = ChatSessionFormat::decode($request->getContent());
    }
    catch (ChatSessionFormatException) {
      throw new BadRequestHttpException('Invalid session fields request.');
    }
    if (!$input instanceof \stdClass ||
      count(get_object_vars($input)) !== 2 ||
      !is_int($input->expected_edit_version ?? NULL) || !($input->fields ?? NULL) instanceof \stdClass) {
      throw new BadRequestHttpException('Expected a session version and fields.');
    }
    $result = $this->writer->update($chat_uuid, $uuid, $input->expected_edit_version, $input->fields);
    return new JsonResponse($result, $result['status'] === 'conflict' ? 409 : 200,
      ['Cache-Control' => 'private, no-store']);
  }

}

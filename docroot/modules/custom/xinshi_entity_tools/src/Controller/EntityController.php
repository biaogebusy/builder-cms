<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\xinshi_entity_tools\EntityInput;
use Drupal\xinshi_entity_tools\EntityService;
use Drupal\xinshi_entity_tools\EntityToolException;
use Drupal\xinshi_entity_tools\ResourceCatalog;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Thin authenticated HTTP boundary for the resource and entity services. */
final class EntityController implements ContainerInjectionInterface {

  public function __construct(
    private readonly ResourceCatalog $resources,
    private readonly EntityService $entities,
  ) {}

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_entity_tools.resources'), $container->get('xinshi_entity_tools.entities'));
  }

  /** Returns capabilities for the effective authenticated account. */
  public function capabilities(): JsonResponse {
    return new JsonResponse($this->resources->capabilities());
  }

  /** Lists only explicitly enabled resources. */
  public function resources(): JsonResponse {
    return new JsonResponse($this->resources->discover());
  }

  /** Describes a resource without accepting field mapping overrides. */
  public function describe(string $resource_id): JsonResponse {
    return new JsonResponse($this->resources->describe($resource_id));
  }

  /** Queries a bounded page; this POST never saves content. */
  public function query(Request $request): JsonResponse {
    return new JsonResponse($this->entities->query(EntityInput::read($request)));
  }

  /** Reads only the specified fields under current access checks. */
  public function read(Request $request): JsonResponse {
    return new JsonResponse($this->entities->read(EntityInput::read($request)));
  }

  /** Returns field-level violations without creating a revision. */
  public function validate(Request $request): JsonResponse {
    return new JsonResponse($this->entities->validate(EntityInput::read($request)));
  }

  /** Saves a draft and distinguishes a known refusal from an uncertain write. */
  public function write(string $execution_id, Request $request): JsonResponse {
    try {
      return new JsonResponse($this->entities->write($execution_id, EntityInput::read($request)));
    }
    catch (EntityToolException $error) {
      $body = ['executionId' => $execution_id, 'code' => $error->reason];
      if ($error->notWritten) {
        $body['outcome'] = 'not_written';
      }
      return new JsonResponse($body, $error->status);
    }
  }

  /** Reads the original actor's receipt without replaying the write. */
  public function find(string $execution_id): JsonResponse {
    $receipt = $this->entities->find($execution_id);
    return new JsonResponse($receipt ?? ['code' => 'operation_not_found'], $receipt ? 200 : 404);
  }

}

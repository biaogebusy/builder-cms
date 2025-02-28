<?php

namespace Drupal\jsonapi_extras;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Simplifies the process of generating a JSON:API version of an entity.
 *
 * @api
 */
class EntityToJsonApi {

  /**
   * The HTTP kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The JSON:API Resource Type Repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * A Session object.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $currentRequest;

  /**
   * EntityToJsonApi constructor.
   *
   * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
   *   The HTTP kernel.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session object.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The stack of requests.
   */
  public function __construct(
    HttpKernelInterface $http_kernel,
    ResourceTypeRepositoryInterface $resource_type_repository,
    SessionInterface $session,
    RequestStack $request_stack
  ) {
    $this->httpKernel = $http_kernel;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->session = $this->currentRequest->hasPreviousSession()
      ? $this->currentRequest->getSession()
      : $session;
  }

  /**
   * Return the requested entity as a raw string.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate the JSON from.
   * @param string[] $includes
   *   The list of includes.
   *
   * @return string
   *   The raw JSON string of the requested resource.
   *
   * @throws \Exception
   */
  public function serialize(EntityInterface $entity, array $includes = []) {
    $resource_type = $this->resourceTypeRepository->get($entity->getEntityTypeId(), $entity->bundle());
    $route_name = sprintf('jsonapi.%s.individual', $resource_type->getTypeName());
    $route_options = [];
    if ($resource_type->isVersionable() && $entity instanceof RevisionableInterface && $revision_id = $entity->getRevisionId()) {
      $route_options['query']['resourceVersion'] = 'id:' . $revision_id;
    }
    $jsonapi_url = Url::fromRoute($route_name, ['entity' => $entity->uuid()], $route_options)
      ->toString(TRUE)
      ->getGeneratedUrl();
    $query = [];
    if ($includes) {
      $query = ['include' => implode(',', $includes)];
    }
    $request = Request::create(
      $jsonapi_url,
      'GET',
      $query,
      $this->currentRequest->cookies->all(),
      [],
      $this->currentRequest->server->all()
    );
    if ($this->session) {
      $request->setSession($this->session);
    }
    $response = $this->httpKernel->handle($request, HttpKernelInterface::SUB_REQUEST);
    // Get contents and terminate response before returning results.
    $content = $response->getContent();
    $this->httpKernel->terminate($request, $response);
    return $content;
  }

  /**
   * Return the requested entity as an structured array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate the JSON from.
   * @param string[] $includes
   *   The list of includes.
   *
   * @return array
   *   The JSON structure of the requested resource.
   *
   * @throws \Exception
   */
  public function normalize(EntityInterface $entity, array $includes = []) {
    return Json::decode($this->serialize($entity, $includes));
  }

}

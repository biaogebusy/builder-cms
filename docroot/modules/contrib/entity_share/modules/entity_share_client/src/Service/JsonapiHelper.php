<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Provides methods not present in the JSON:API module.
 *
 * @package Drupal\entity_share_client\Service
 */
class JsonapiHelper implements JsonapiHelperInterface {

  use StringTranslationTrait;

  /**
   * The JsonApiDocumentTopLevelNormalizer normalizer.
   *
   * @var \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer
   */
  protected $jsonapiDocumentTopLevelNormalizer;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * JsonapiHelper constructor.
   *
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   A serializer.
   * @param \Drupal\jsonapi\Normalizer\JsonApiDocumentTopLevelNormalizer $jsonapi_document_top_level_normalizer
   *   The JsonApiDocumentTopLevelNormalizer normalizer.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   */
  public function __construct(
    SerializerInterface $serializer,
    JsonApiDocumentTopLevelNormalizer $jsonapi_document_top_level_normalizer,
    ResourceTypeRepositoryInterface $resource_type_repository
  ) {
    $this->jsonapiDocumentTopLevelNormalizer = $jsonapi_document_top_level_normalizer;
    $this->jsonapiDocumentTopLevelNormalizer->setSerializer($serializer);
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function extractEntity(array $data) {
    // Format JSON as in
    // JsonApiDocumentTopLevelNormalizerTest::testDenormalize().
    $prepared_json = [
      'data' => [
        'type' => $data['type'],
        'attributes' => $data['attributes'],
      ],
    ];
    return $this->jsonapiDocumentTopLevelNormalizer->denormalize($prepared_json, NULL, 'api_json', [
      'resource_type' => $this->resourceTypeRepository->getByTypeName($data['type']),
    ]);
  }

}

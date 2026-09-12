<?php

namespace Drupal\Tests\tour\Functional\Rest;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\rest\Functional\EntityResource\ConfigEntityResourceTestBase;
use Drupal\tour\Entity\Tour;

/**
 * Tests tour integration with restAPI, base.
 */
abstract class TourResourceTestBase extends ConfigEntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tour'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'tour';

  /**
   * Tour entity.
   *
   * @var \Drupal\tour\TourInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    $this->grantPermissionsToTestedRole(['administer tour']);
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity(): EntityInterface {
    $tour = Tour::create([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'tour_llama',
      'label' => 'Llama tour',
      'routes' => [
        [
          'route_name' => '<front>',
        ],
      ],
      'tips' => [
        'tour_llama_1' => [
          'id' => 'tour_llama_1',
          'plugin' => 'text',
          'label' => 'Llama',
          'weight' => 100,
          'selector' => '#tour-llama-1',
          'body' => 'Who handle the awesomeness of llamas?',
        ],
      ],
    ]);
    $tour->save();

    return $tour;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return [
      'dependencies' => [],
      'id' => 'tour_llama',
      'label' => 'Llama tour',
      'langcode' => 'en',
      'routes' => [
        [
          'route_name' => '<front>',
        ],
      ],
      'status' => TRUE,
      'tips' => [
        'tour_llama_1' => [
          'id' => 'tour_llama_1',
          'plugin' => 'text',
          'label' => 'Llama',
          'body' => 'Who handle the awesomeness of llamas?',
          'weight' => 100,
          'selector' => '#tour-llama-1',
        ],
      ],
      'uuid' => $this->entity->uuid(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity(): array {
    // @todo Update in https://www.drupal.org/node/2300677.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedUnauthorizedAccessMessage($method): string {
    return "The following permissions are required: 'access tour' OR 'administer tour'.";
  }

}

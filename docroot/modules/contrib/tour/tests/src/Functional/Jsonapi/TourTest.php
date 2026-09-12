<?php

namespace Drupal\Tests\tour\Functional\Jsonapi;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\Tests\jsonapi\Functional\ConfigEntityResourceTestBase;
use Drupal\tour\Entity\Tour;
use PHPUnit\Framework\Attributes\Group;

/**
 * JSON:API integration test for the "Tour" config entity type.
 */
#[Group('tour')]
class TourTest extends ConfigEntityResourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tour'];

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'tour';

  /**
   * {@inheritdoc}
   */
  protected static $resourceTypeName = 'tour--tour';

  /**
   * {@inheritdoc}
   *
   * @var \Drupal\tour\TourInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUpAuthorization($method): void {
    $this->grantPermissionsToTestedRole(['access tour']);
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
          'body' => 'Who handle the awesomeness of llamas?',
          'weight' => 100,
          'selector' => '#tour-llama-1',
        ],
      ],
    ]);
    $tour->save();

    return $tour;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument(): array {
    $self_url = Url::fromUri('base:/jsonapi/tour/tour/' . $this->entity->uuid())->setAbsolute()->toString(TRUE)->getGeneratedUrl();
    $normalized_version = preg_replace('/x-dev$/', '0', \Drupal::VERSION);
    if (version_compare($normalized_version, '11.2.0', '>=')) {
      $json_href = 'http://jsonapi.org/format/1.1/';
      $json_version = '1.1';
    }
    else {
      $json_href = 'http://jsonapi.org/format/1.0/';
      $json_version = '1.0';
    }
    return [
      'jsonapi' => [
        'meta' => [
          'links' => [
            'self' => ['href' => $json_href],
          ],
        ],
        'version' => $json_version,
      ],
      'links' => [
        'self' => ['href' => $self_url],
      ],
      'data' => [
        'id' => $this->entity->uuid(),
        'type' => 'tour--tour',
        'links' => [
          'self' => ['href' => $self_url],
        ],
        'attributes' => [
          'langcode' => 'en',
          'status' => TRUE,
          'dependencies' => [],
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
              'body' => 'Who handle the awesomeness of llamas?',
              'weight' => 100,
              'selector' => '#tour-llama-1',
            ],
          ],
          'drupal_internal__id' => 'tour_llama',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getPostDocument(): array {
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

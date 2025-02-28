<?php

namespace Drupal\Tests\jsonapi_extras\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\jsonapi\Functional\JsonApiFunctionalTestBase;

/**
 * Provides helper methods for the JSON API Extras module's functional tests.
 *
 * @internal
 */
abstract class JsonApiExtrasFunctionalTestBase extends JsonApiFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Add vocabs field to the tags.
    $this->createEntityReferenceField(
      'taxonomy_term',
      'tags',
      'vocabs',
      'Vocabularies',
      'taxonomy_vocabulary',
      'default',
      [
        'target_bundles' => [
          'tags' => 'taxonomy_vocabulary',
        ],
        'auto_create' => TRUE,
      ],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    FieldStorageConfig::create([
      'field_name' => 'field_timestamp',
      'entity_type' => 'node',
      'type' => 'timestamp',
      'settings' => [],
      'cardinality' => 1,
    ])->save();

    $field_config = FieldConfig::create([
      'field_name' => 'field_timestamp',
      'label' => 'Timestamp',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'settings' => [],
      'description' => '',
    ]);
    $field_config->save();

    $config = \Drupal::configFactory()->getEditable('jsonapi_extras.settings');
    $config->set('path_prefix', 'api');
    $config->set('include_count', TRUE);
    $config->save(TRUE);
    static::overrideResources();
    $this->resetAll();
    $role = $this->user->get('roles')[0]->entity;
    $this->grantPermissions(
        $role,
        ['administer nodes', 'administer site configuration']
    );
  }

  /**
   * {@inheritdoc}
   *
   * Appends the 'application/vnd.api+json' if there's no Accept header.
   */
  protected function drupalGet($path, array $options = [], array $headers = []) {
    if (empty($headers['Accept']) && empty($headers['accept'])) {
      $headers['Accept'] = 'application/vnd.api+json';
    }
    return parent::drupalGet($path, $options, $headers);
  }

  /**
   * Creates the JSON API Resource Config entities to override the resources.
   */
  protected static function overrideResources() {}

}

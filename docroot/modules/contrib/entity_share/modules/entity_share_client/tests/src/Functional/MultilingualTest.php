<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Service\StateInformationInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * General functional test class for multilingual scenarios.
 *
 * @group entity_share
 * @group entity_share_client
 */
class MultilingualTest extends EntityShareClientFunctionalTestBase {

  /**
   * The state information service.
   *
   * @var \Drupal\entity_share_client\Service\StateInformationInterface
   */
  protected $stateInformation;

  /**
   * The resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * {@inheritdoc}
   */
  protected static $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  protected static $entityBundleId = 'es_test';

  /**
   * {@inheritdoc}
   */
  protected static $entityLangcode = 'en';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->stateInformation = $this->container->get('entity_share_client.state_information');
    $this->resourceTypeRepository = $this->container->get('jsonapi.resource_type.repository');

    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    return [
      'node' => [
        'en' => [
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
        'fr' => [
          'es_test' => $this->getCompleteNodeInfos([
            'status' => [
              'value' => NodeInterface::PUBLISHED,
              'checker_callback' => 'getValue',
            ],
          ]),
        ],
      ],
    ];
  }

  /**
   * Test several scenarios of importing the multilingual entities.
   */
  public function testMultilingualImport() {
    // Test that it is possible to pull the same entity in several languages
    // during the same process.
    $this->pullEveryChannels();
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->checkCreatedEntities();
    $this->deleteAllEntities();

    // Test pulling content in its default translation first.
    $this->pullChannel('node_es_test_en');
    $this->pullChannel('node_es_test_fr');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->checkCreatedEntities();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $node_translation = $node->getTranslation('en');
    $this->assertTrue($node_translation->isDefaultTranslation(), 'The node default translation is the same as the initial one as it had been pulled in its default language first.');
    $this->deleteAllEntities();

    // Test pulling content NOT in its default translation first.
    $this->pullChannel('node_es_test_fr');
    $this->pullChannel('node_es_test_en');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->checkCreatedEntities();

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->loadEntity('node', 'es_test');
    $node_translation = $node->getTranslation('fr');
    $this->assertTrue($node_translation->isDefaultTranslation(), 'The node default translation has changed as it had been pulled in another language first.');
    $this->deleteAllEntities();

    // Test state information.
    // 1: No import: en and fr channels data should indicate a new entity.
    $this->expectedState(
      StateInformationInterface::INFO_ID_NEW,
      StateInformationInterface::INFO_ID_NEW
    );

    // 2: Import entity in en: en should indicate synchronized and fr should
    // indicate a new translation.
    $this->pullChannel('node_es_test_en');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->expectedState(
      StateInformationInterface::INFO_ID_SYNCHRONIZED,
      StateInformationInterface::INFO_ID_NEW_TRANSLATION
    );

    // 3: Import entity in fr: en and fr should indicate synchronized.
    $this->pullChannel('node_es_test_fr');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->expectedState(
      StateInformationInterface::INFO_ID_SYNCHRONIZED,
      StateInformationInterface::INFO_ID_SYNCHRONIZED
    );

    // 4: Rig 'changed' JSON data attribute of en translation (this emulates a
    // change on the client website): en should indicate changed and fr should
    // indicate synchronized.
    $this->expectedState(
      StateInformationInterface::INFO_ID_CHANGED,
      StateInformationInterface::INFO_ID_SYNCHRONIZED,
      ['en' => ['fast_forward_changed_time']]
    );

    // 5: Import entity in en: en and fr should indicate synchronized.
    $this->pullChannel('node_es_test_en');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->expectedState(
      StateInformationInterface::INFO_ID_SYNCHRONIZED,
      StateInformationInterface::INFO_ID_SYNCHRONIZED
    );

    // 6: Rig 'changed' JSON data attribute of fr translation (this emulates a
    // change on the client website): en should indicate synchronized and fr
    // should indicate changed.
    $this->expectedState(
      StateInformationInterface::INFO_ID_SYNCHRONIZED,
      StateInformationInterface::INFO_ID_CHANGED,
      ['fr' => ['fast_forward_changed_time']]
    );

    // 7: Import entity in fr: en and fr should indicate synchronized.
    $this->pullChannel('node_es_test_fr');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $this->expectedState(
      StateInformationInterface::INFO_ID_SYNCHRONIZED,
      StateInformationInterface::INFO_ID_SYNCHRONIZED
    );
  }

  /**
   * Helper function to delete all (prepared or imported) content.
   *
   * This function doesn't assert the deletion of entities.
   */
  protected function deleteAllEntities() {
    $entity_storage = $this->entityTypeManager->getStorage('node');
    $entities = $entity_storage->loadByProperties();
    if ($entities) {
      foreach ($entities as $entity) {
        $entity->delete();
      }
    }
    $this->entities = [];
  }

  /**
   * {@inheritdoc}
   */
  protected function createChannel(UserInterface $user) {
    parent::createChannel($user);

    // Add a channel for the node in French.
    $channel_storage = $this->entityTypeManager->getStorage('channel');
    $channel = $channel_storage->create([
      'id' => 'node_es_test_fr',
      'label' => $this->randomString(),
      'channel_maxsize' => 50,
      'channel_entity_type' => 'node',
      'channel_bundle' => 'es_test',
      'channel_langcode' => 'fr',
      'access_by_permission' => FALSE,
      'authorized_roles' => [],
      'authorized_users' => [
        $user->uuid(),
      ],
    ]);
    $channel->save();
    $this->channels[$channel->id()] = $channel;
  }

  /**
   * Helper function.
   *
   * @param string $en_expected_state
   *   The expected state for the en translation.
   * @param string $fr_expected_state
   *   The expected state for the fr translation.
   * @param array $overrides
   *   Manipulations to do on JSON:API data attributes, per language.
   */
  protected function expectedState($en_expected_state, $fr_expected_state, array $overrides = []) {
    $json_data = $this->getEntityJsonData('node_es_test_en', 'es_test');
    if (!empty($overrides['en'])) {
      $json_data['attributes'] = $this->overrideJsonDataAttributes($overrides['en'], $json_data['attributes']);
    }
    $status = $this->stateInformation->getStatusInfo($json_data);
    $this->assertEquals($en_expected_state, $status['info_id']);

    $json_data = $this->getEntityJsonData('node_es_test_fr', 'es_test');
    if (!empty($overrides['fr'])) {
      $json_data['attributes'] = $this->overrideJsonDataAttributes($overrides['fr'], $json_data['attributes']);
    }
    $status = $this->stateInformation->getStatusInfo($json_data);
    $this->assertEquals($fr_expected_state, $status['info_id']);
  }

  /**
   * Override attributes of entity's JSON:API data.
   *
   * @param array $overrides
   *   Manipulations to do on attributes.
   * @param array $attributes
   *   Original attributes of JSON:API data of an entity.
   *
   * @return array
   *   Altered attributes of JSON:API data of an entity.
   */
  protected function overrideJsonDataAttributes(array $overrides, array $attributes) {
    foreach ($overrides as $override_type) {
      switch ($override_type) {
        case 'fast_forward_changed_time':
          // Sets the 'changed' time of JSON data to one hour in the future.
          $resource_type = $this->resourceTypeRepository->get(
            static::$entityTypeId,
            static::$entityBundleId
          );
          // Determine public name of 'changed'.
          $changed_public_name = FALSE;
          if ($resource_type->hasField('changed')) {
            $changed_public_name = $resource_type->getPublicName('changed');
          }
          $changed = $attributes[$changed_public_name] ?? FALSE;
          if ($changed !== FALSE) {
            $changed_timestamp = EntityShareUtility::convertChangedTime($changed);
            $attributes['changed'] = (string) ($changed_timestamp + 3600);
          }
          break;
      }
    }
    return $attributes;
  }

}

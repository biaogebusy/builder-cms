<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Test the pull form.
 *
 * @group entity_share
 * @group entity_share_client
 */
class PullFormTest extends EntityShareClientFunctionalTestBase {

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
    $this->prepareContent();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    $permissions = parent::getAdministratorPermissions();
    $permissions[] = 'entity_share_client_pull_content';
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntitiesDataArray() {
    $en_nodes = [];
    for ($i = 1; $i <= 60; $i++) {
      $en_nodes["es_test_$i"] = $this->getCompleteNodeInfos([
        'status' => [
          'value' => NodeInterface::PUBLISHED,
          'checker_callback' => 'getValue',
        ],
      ]);
    }

    return [
      'node' => [
        'en' => $en_nodes,
      ],
    ];
  }

  /**
   * Test max size behavior between channel and import config.
   */
  public function testImportMaxSize() {
    // Uninstall test module because requests needs to be done several times and
    // no import is needed.
    $this->container->get('module_installer')->uninstall([
      'entity_share_client_remote_manager_test',
    ]);

    // Test default behavior.
    $this->checkNumberOfEntitiesOnPullForm(50);

    // Test channel max size.
    $this->setMaxSize(40, 50);
    $this->checkNumberOfEntitiesOnPullForm(40);

    // Test import max size.
    $this->setMaxSize(50, 40);
    $this->checkNumberOfEntitiesOnPullForm(40);

    // Test min of both.
    $this->setMaxSize(30, 40);
    $this->checkNumberOfEntitiesOnPullForm(30);
    $this->setMaxSize(40, 30);
    $this->checkNumberOfEntitiesOnPullForm(30);
  }

  /**
   * Check the number of rows in entities table.
   *
   * @param int $expected_number
   *   The expected number of rows.
   */
  protected function checkNumberOfEntitiesOnPullForm(int $expected_number) {
    $this->drupalLogin($this->adminUser);
    $pull_form_url = Url::fromRoute('entity_share_client.admin_content_pull_form', [], [
      'query' => [
        'remote' => $this->remote->id(),
        'channel' => static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode,
        'import_config' => $this->importConfig->id(),
      ],
    ]);
    $this->drupalGet($pull_form_url);

    $this->assertSession()->elementsCount('css', 'table#edit-entities tbody tr', $expected_number);
  }

  /**
   * Set max sizes.
   *
   * @param int $channel_maxsize
   *   The channel max size.
   * @param int $import_maxsize
   *   The import config max size.
   */
  protected function setMaxSize(int $channel_maxsize, int $import_maxsize) {
    $channel = $this->channels[static::$entityTypeId . '_' . static::$entityBundleId . '_' . static::$entityLangcode];
    $channel->set('channel_maxsize', $channel_maxsize);
    $channel->save();

    $this->importConfig->set('import_maxsize', $import_maxsize);
    $this->importConfig->save();
  }

}

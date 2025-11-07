<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Functional test class to test import plugin "Language fallback".
 *
 * @group entity_share
 * @group entity_share_client
 */
class LanguageFallbackTest extends EntityShareClientFunctionalTestBase {

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
    $this->postSetupFixture();
  }

  /**
   * {@inheritdoc}
   */
  protected function getImportConfigProcessorSettings() {
    $processors = parent::getImportConfigProcessorSettings();
    $processors['language_fallback'] = [
      'fallback_language' => LanguageInterface::LANGCODE_SITE_DEFAULT,
      'force_language' => FALSE,
      'weights' => [
        'prepare_entity_data' => 0,
      ],
    ];
    return $processors;
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
   * Test several scenarios of with the language fallback import plugin.
   */
  public function testLanguageFallbackPlugin() {
    // Test behavior with the plugin enabled.
    $this->checkImportedLanguage('fr');

    // Test force update before removing the French language after in tests.
    // Set new import config.
    $new_plugin_configurations = [
      'language_fallback' => [
        'force_language' => TRUE,
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    // Test that the language is English even if the French language is present.
    $this->checkImportedLanguage('en');

    // Remove French language.
    $french_language = ConfigurableLanguage::load('fr');
    $french_language->delete();

    // Do not force language.
    $new_plugin_configurations = [
      'language_fallback' => [
        'force_language' => FALSE,
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);

    // Test site default language.
    $this->checkImportedLanguage('en');

    // Test with import language as English.
    $new_plugin_configurations = [
      'language_fallback' => [
        'fallback_language' => 'en',
      ],
    ];
    $this->mergePluginsToImportConfig($new_plugin_configurations);
    $this->checkImportedLanguage('en');
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
   * Import French channel and check the imported langcode.
   *
   * @param string $expected_langcode
   *   The expected langcode.
   */
  protected function checkImportedLanguage($expected_langcode) {
    $this->pullChannel('node_es_test_fr');
    $this->importService->getRuntimeImportContext()->clearImportedEntities();
    $node = $this->loadEntity('node', 'es_test');
    $this->assertEquals($expected_langcode, $node->language()->getId());
    $node->delete();
  }

}

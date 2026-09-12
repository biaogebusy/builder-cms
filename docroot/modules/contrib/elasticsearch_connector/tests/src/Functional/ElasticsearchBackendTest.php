<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

// cspell:ignore biscit seabiscuit

/**
 * Test the ElasticsearchBackend plugin for Search API.
 *
 * @group elasticsearch_connector
 */
class ElasticsearchBackendTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector',
    'elasticsearch_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the Elasticsearch Backend configuration form for synonyms.
   */
  public function testConfigurationFormSynonyms(): void {
    // Setup: Create a user who can access the configuration form.
    $this->drupalLogin($this->createUser([
      'administer search_api',
    ]));

    // Setup: Navigate to the configuration form.
    $this->drupalGet(Url::fromRoute('entity.search_api_server.edit_form', [
      'search_api_server' => 'elasticsearch_server',
    ]));

    // SUT: Add two synonyms.
    $this->submitForm([
      'backend_config[advanced][synonyms]' => "computer, pc, laptop\nsea biscuit, sea biscit => seabiscuit",
    ], 'Save');

    // Assert: Ensure the two synonyms have been saved.
    $this->assertEquals([
      'computer, pc, laptop',
      'sea biscuit, sea biscit => seabiscuit',
    ], $this->getServerBackendConfig('elasticsearch_server')['advanced']['synonyms']);

    // Setup: Navigate to the configuration form.
    $this->drupalGet(Url::fromRoute('entity.search_api_server.edit_form', [
      'search_api_server' => 'elasticsearch_server',
    ]));

    // SUT: Remove all synonyms.
    $this->submitForm([
      'backend_config[advanced][synonyms]' => '',
    ], 'Save');

    // Assert: Ensure the synonyms have been cleared.
    $this->assertEquals([], $this->getServerBackendConfig('elasticsearch_server')['advanced']['synonyms']);
  }

  /**
   * Get a Search API Server's backend configuration.
   *
   * @param string $machineName
   *   The machine name of the Search API Server to get information for.
   *
   * @return array|null
   *   An array of backend configuration, or NULL if the server cannot be found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Throws an InvalidPluginDefinitionException if the 'search_api_server'
   *   storage handler couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Throws a PluginNotFoundException if the 'search_api_server' entity type
   *   doesn't exist.
   */
  public function getServerBackendConfig(string $machineName): ?array {
    return $this->container->get('entity_type.manager')
      ->getStorage('search_api_server')
      ->load($machineName)
      ?->getBackendConfig();
  }

}

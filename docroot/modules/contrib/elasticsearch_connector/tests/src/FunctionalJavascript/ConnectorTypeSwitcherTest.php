<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Test the Connector-type plugin switcher on the Search API Server form.
 *
 * @group elasticsearch_connector
 */
class ConnectorTypeSwitcherTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the Connector-type plugin switcher on the Search API Server form.
   */
  public function testConnectorTypeSwitcher(): void {
    // Setup: Create a user with permission to add Search API Servers.
    $this->drupalLogin($this->createUser([
      'administer search_api',
    ]));

    // Setup: Go to the Add search server form.
    $this->drupalGet(Url::fromRoute('entity.search_api_server.add_form'));

    // Setup: Select the "ElasticSearch" backend.
    $this->click('input[name="backend"][value="elasticsearch"]');

    // Assert: Only the "Standard" config form should be visible at first.
    $this->assertSeeConnectorConfiguration('Configure Standard ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud ID ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud Endpoint ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure HTTP Basic Authentication ElasticSearch connector');

    // SUT: Click the "Elastic Cloud ID" ElasticSearch Connector.
    $this->click('input[name="backend_config[connector]"][value="elastic_cloud_id"]');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert: Only the "Elastic Cloud ID" config form should be visible now.
    $this->assertNotSeeConnectorConfiguration('Configure Standard ElasticSearch connector');
    $this->assertSeeConnectorConfiguration('Configure Elastic Cloud ID ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud Endpoint ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure HTTP Basic Authentication ElasticSearch connector');

    // SUT: Click the "Elastic Cloud Endpoint" ElasticSearch Connector.
    $this->click('input[name="backend_config[connector]"][value="elastic_cloud_endpoint"]');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert: Only the "Elastic Cloud Endpoint" config form should be visible
    // now.
    $this->assertNotSeeConnectorConfiguration('Configure Standard ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud ID ElasticSearch connector');
    $this->assertSeeConnectorConfiguration('Configure Elastic Cloud Endpoint ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure HTTP Basic Authentication ElasticSearch connector');

    // SUT: Click the "HTTP Basic Authentication" ElasticSearch Connector.
    $this->click('input[name="backend_config[connector]"][value="basicauth"]');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert: Only the "HTTP Basic Authentication" config form should be
    // visible now.
    $this->assertNotSeeConnectorConfiguration('Configure Standard ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud ID ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud Endpoint ElasticSearch connector');
    $this->assertSeeConnectorConfiguration('Configure HTTP Basic Authentication ElasticSearch connector');

    // SUT: Click the "Standard" ElasticSearch Connector.
    $this->click('input[name="backend_config[connector]"][value="standard"]');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Assert: Only the "Standard" config form should be visible now.
    $this->assertSeeConnectorConfiguration('Configure Standard ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud ID ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure Elastic Cloud Endpoint ElasticSearch connector');
    $this->assertNotSeeConnectorConfiguration('Configure HTTP Basic Authentication ElasticSearch connector');
  }

  /**
   * Assert that we cannot see a ElasticSearch Connector configuration form.
   *
   * @param string $detailsSummaryText
   *   The text in the summary of the details element containing the given
   *   ElasticSearch Connector plugin's configuration form.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Throws an ElementNotFoundException if an ElasticSearch Connector plugin
   *   configuration form cannot be found.
   * @throws \PHPUnit\Framework\ExpectationFailedException
   *   Throws a ExpectationFailedException if an ElasticSearch Connector plugin
   *   configuration form can be found, but the text in its summary is not what
   *   was expected.
   */
  protected function assertNotSeeConnectorConfiguration(string $detailsSummaryText): void {
    $configDetailsSummary = $this->assertSession()->elementExists('css', 'details#elasticsearch-connector-config-form summary');
    $this->assertNotEquals($detailsSummaryText, $configDetailsSummary->getText());
  }

  /**
   * Assert that we can see a given ElasticSearch Connector configuration form.
   *
   * @param string $detailsSummaryText
   *   The text in the summary of the details element containing the given
   *   ElasticSearch Connector plugin's configuration form.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Throws an ElementNotFoundException if an ElasticSearch Connector plugin
   *   configuration form cannot be found.
   * @throws \PHPUnit\Framework\ExpectationFailedException
   *   Throws a ExpectationFailedException if an ElasticSearch Connector plugin
   *   configuration form can be found, but the text in its summary is not what
   *   was expected.
   */
  protected function assertSeeConnectorConfiguration(string $detailsSummaryText): void {
    $configDetailsSummary = $this->assertSession()->elementExists('css', 'details#elasticsearch-connector-config-form summary');
    $this->assertEquals($detailsSummaryText, $configDetailsSummary->getText());
  }

}

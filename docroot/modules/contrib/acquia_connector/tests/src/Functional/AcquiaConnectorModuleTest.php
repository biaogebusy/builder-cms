<?php

namespace Drupal\Tests\acquia_connector\Functional;

use Drupal\acquia_connector\Controller\StatusController;
use Drupal\acquia_connector\Helper\Storage;
use Drupal\acquia_connector\Subscription;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the functionality of the Acquia Connector module.
 *
 * @group acquia_connector
 */
class AcquiaConnectorModuleTest extends BrowserTestBase {

  /**
   * Drupal 8.8 requires default theme to be specified.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Test user e-mail.
   *
   * @var string
   */
  protected $acqtestEmail = 'TEST_networkuser@example.com';

  /**
   * Test user password.
   *
   * @var string
   */
  protected $acqtestPass = 'TEST_password';

  /**
   * Test user ID.
   *
   * @var string
   */
  protected $acqtestId = 'TEST_AcquiaConnectorTestID';

  /**
   * Test Acquia Connector key.
   *
   * @var string
   */
  protected $acqtestKey = 'TEST_AcquiaConnectorTestKey';

  /**
   * Test site name.
   *
   * @var string
   */
  protected $acqtestName = 'test name';

  /**
   * Test machine name.
   *
   * @var string
   */
  protected $acqtestMachineName = 'test_name';

  /**
   * Test Acquia Connector expired ID.
   *
   * @var string
   */
  protected $acqtestExpiredId = 'TEST_AcquiaConnectorTestIDExp';

  /**
   * Test Acquia Connector expired Key.
   *
   * @var string
   */
  protected $acqtestExpiredKey = 'TEST_AcquiaConnectorTestKeyExp';

  /**
   * Test Acquia Connector 503 ID.
   *
   * @var string
   */
  protected $acqtest503Id = 'TEST_AcquiaConnectorTestID503';

  /**
   * Test Acquia Connector 503 Key.
   *
   * @var string
   */
  protected $acqtest503Key = 'TEST_AcquiaConnectorTestKey503';

  /**
   * Test Acquia Connector ID with error.
   *
   * @var string
   */
  protected $acqtestErrorId = 'TEST_AcquiaConnectorTestIDErr';

  /**
   * Test Acquia Connector key with error.
   *
   * @var string
   */
  protected $acqtestErrorKey = 'TEST_AcquiaConnectorTestKeyErr';

  /**
   * Test privileged user.
   *
   * @var object
   */
  protected $privilegedUser;

  /**
   * Test user with subscription.
   *
   * @var object
   */
  protected $networkUser;

  /**
   * URL to get Acquia Cloud Free.
   *
   * @var string
   */
  protected $cloudFreeUrl;

  /**
   * Module setup path.
   *
   * @var string
   */
  protected $setupPath;

  /**
   * Module credentials path.
   *
   * @var string
   */
  protected $credentialsPath;

  /**
   * Module settings path.
   *
   * @var string
   */
  protected $settingsPath;

  /**
   * Module environment change path.
   *
   * @var string
   */
  protected $environmentChangePath;

  /**
   * Drupal status report path.
   *
   * @var string
   */
  protected $statusReportUrl;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'acquia_connector',
    'toolbar',
    'acquia_connector_test',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create and log in our privileged user.
    $this->privilegedUser = $this->drupalCreateUser([
      'administer site configuration',
      'access administration pages',
      'access toolbar',
    ]);
    $this->drupalLogin($this->privilegedUser);

    // Create a user that has a Network subscription.
    $this->networkUser = $this->drupalCreateUser();
    $this->networkUser->mail = $this->acqtestEmail;
    $this->networkUser->pass = $this->acqtestPass;
    $this->networkUser->save();
    // $this->drupalLogin($this->network_user);
    // Setup variables.
    $this->cloudFreeUrl = 'https://www.acquia.com/acquia-cloud-free';
    $this->setupPath = 'admin/config/system/acquia-connector/setup';
    $this->credentialsPath = 'admin/config/system/acquia-connector/credentials';
    $this->settingsPath = 'admin/config/system/acquia-connector';
    $this->environmentChangePath = '/admin/config/system/acquia-connector/environment-change';
    $this->statusReportUrl = 'admin/reports/status';

    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.server', 'http://mock-spi-server')->save();
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.ssl_verify', FALSE)->save();
    \Drupal::configFactory()->getEditable('acquia_connector.settings')->set('spi.ssl_override', TRUE)->save();

    // Create a node, since some SPI data is only gathered if nodes exist.
    $this->createContentType([
      'type' => 'test_content_type',
      'name' => 'Test content type',
    ]);
    $this->createNode([
      'type' => 'test_content_type',
      'title' => 'Dummy node',
      'body' => [
        [
          'value' => 'Dummy node body',
        ],
      ],
    ]);

  }

  /**
   * Helper function for storing UI strings.
   */
  private function acquiaConnectorStrings($id) {
    switch ($id) {
      case 'free':
        return 'Sign up for Acquia Cloud Free, a free Drupal sandbox to experiment with new features, test your code quality, and apply continuous integration best practices.';

      case 'get-connected':
        return 'If you have an Acquia Subscription, connect now. Otherwise, you can turn this message off by disabling the Acquia Connector modules.';

      case 'enter-email':
        return 'Enter the email address you use to login to the Acquia Subscription';

      case 'enter-password':
        return 'Enter your Acquia Subscription password';

      case 'account-not-found':
        return 'Account not found';

      case 'id-key':
        return 'Enter your product keys from your application overview or log in to connect your site to Acquia Insight.';

      case 'enter-key':
        return 'Network key';

      case 'subscription-not-found':
        return 'Error: Subscription not found (1000)';

      case 'saved':
        return 'The configuration options have been saved.';

      case 'subscription':
        // Assumes subscription name is same as id.
        return 'Subscription: ' . $this->acqtestId;

      case 'menu-active':
        return 'Subscription active (expires 2023/10/8)';

      case 'menu-inactive':
        return 'Subscription not active';

      case 'site-name-required':
        return 'Name field is required.';

      case 'site-machine-name-required':
        return 'Machine name field is required.';

      case 'first-connection':
        return 'This is the first connection from this site, it may take awhile for it to appear.';
    }
  }

  /**
   * Test get connected.
   */
  public function testAcquiaConnectorGetConnectedTests() {
    // Check for call to get connected.
    $this->drupalGet('admin');
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('free'));
    $this->assertSession()->linkByHrefExists($this->cloudFreeUrl, 0, 'Link to Acquia.com Cloud Services exists');
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('get-connected'));
    $this->assertSession()->linkExists('connect now', 0, 'The "connect now" link exists');
    // Check connection setup page.
    $this->drupalGet($this->setupPath);
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('enter-email'));
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('enter-password'));
    $this->assertSession()->linkByHrefExists($this->cloudFreeUrl, 0, 'Link to Acquia.com free signup exists');
    // Check errors on automatic setup page.
    $edit_fields = [
      'email' => $this->randomString(),
      'pass' => $this->randomString(),
    ];
    $submit_button = 'Next';
    $this->drupalGet($this->setupPath);
    $this->submitForm($edit_fields, $submit_button);
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('account-not-found'));
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('menu-inactive'));
    // Check manual connection.
    $this->drupalGet($this->credentialsPath);
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('id-key'));
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('enter-key'));
    $this->assertSession()->linkByHrefExists($this->cloudFreeUrl, 0, 'Link to Acquia.com free signup exists');
    // Check errors on connection page.
    $edit_fields = [
      'acquia_identifier' => $this->randomString(),
      'acquia_key' => $this->randomString(),
    ];
    $submit_button = 'Connect';
    $this->drupalGet($this->credentialsPath);
    $this->submitForm($edit_fields, $submit_button);
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('subscription-not-found'));
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('menu-inactive'));
    // Connect site on key and id.
    $edit_fields = [
      'acquia_identifier' => $this->acqtestId,
      'acquia_key' => $this->acqtestKey,
    ];
    $submit_button = 'Connect';
    $this->drupalGet($this->credentialsPath);
    $this->submitForm($edit_fields, $submit_button);
    $this->drupalGet($this->settingsPath);
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('subscription'));
    $this->assertSession()->linkByHrefExists($this->setupPath, 0, 'Link to change subscription exists');

    $this->disconnectSite();

    // Connect via automatic setup.
    $edit_fields = [
      'email' => $this->acqtestEmail,
      'pass' => $this->acqtestPass,
    ];
    $submit_button = 'Next';
    $this->drupalGet($this->setupPath);
    $this->submitForm($edit_fields, $submit_button);
    $this->drupalGet($this->setupPath);
    $this->drupalGet($this->settingsPath);
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('subscription'));

    // Confirm menu reports active subscription.
    $this->drupalGet('admin');
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('menu-active'));
    // Check errors if name or machine name empty.
    $submit_button = 'Save configuration';
    $this->drupalGet($this->settingsPath);
    $this->submitForm([], $submit_button);
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('site-name-required'));
    $this->assertSession()->pageTextContains($this->acquiaConnectorStrings('site-machine-name-required'));

    // Acquia hosted sites.
    $edit_fields = [
      'acquia_dynamic_banner' => TRUE,
      'name' => 'test_name',
      'machine_name' => 'test_name',
    ];
    $submit_button = 'Save configuration';
    $this->drupalGet($this->settingsPath);
    $this->submitForm($edit_fields, $submit_button);
    $this->assertSession()->checkboxChecked('edit-acquia-dynamic-banner');
    // Test acquia hosted site.
    $settings['_SERVER']['AH_SITE_NAME'] = (object) [
      'value' => 'acqtest_drupal',
      'required' => TRUE,
    ];
    $settings['_SERVER']['AH_SITE_ENVIRONMENT'] = (object) [
      'value' => 'dev',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    sleep(10);
    $this->drupalGet($this->settingsPath);
    $elements = $this->xpath('//input[@name=:name]', [':name' => 'machine_name']);
    foreach ($elements as $element) {
      $this->assertSame('disabled', $element->getAttribute('disabled'), 'Machine name field is disabled.');
    }

    $this->disconnectSite();

  }

  /**
   * Test Connector subscription methods.
   */
  public function testAcquiaConnectorSubscriptionTests() {
    $subscription = new Subscription();
    // Starts as inactive.
    $is_active = $subscription->isActive();
    $this->assertFalse($is_active, 'Subscription is not currently active.');
    // Confirm HTTP request count is 0 because without credentials no request
    // should have been made.
    $this->assertSame(0, \Drupal::state()->get('acquia_connector_test_request_count', 0));
    $check_subscription = $subscription->update();
    \Drupal::state()->resetCache();
    $this->assertFalse($check_subscription, 'Subscription is currently false.');
    // Confirm HTTP request count is still 0.
    $this->assertSame(0, \Drupal::state()->get('acquia_connector_test_request_count', 0));
    // Fail a connection.
    $random_id = $this->randomString();
    $edit_fields = [
      'acquia_identifier' => $random_id,
      'acquia_key' => $this->randomString(),
    ];
    $submit_button = 'Connect';
    $this->drupalGet($this->credentialsPath);
    $this->submitForm($edit_fields, $submit_button);
    // Confirm HTTP request count is 1.
    $this->assertSame(1, \Drupal::state()->get('acquia_connector_test_request_count', 0), 'Made 1 HTTP request in attempt to connect subscription.');
    $is_active = $subscription->isActive();
    $this->assertFalse($is_active, 'Subscription is not active after failed attempt to connect.');
    $this->assertSame(1, \Drupal::state()->get('acquia_connector_test_request_count', 0), 'Still have made only 1 HTTP request');
    $check_subscription = $subscription->update();
    \Drupal::state()->resetCache();
    $this->assertFalse($check_subscription, 'Subscription is false after failed attempt to connect.');
    $this->assertSame(1, \Drupal::state()->get('acquia_connector_test_request_count', 0), 'Still have made only 1 HTTP request');
    // Test default from acquia_agent_settings().
    $stored = \Drupal::config('acquia_connector.settings');
    $current_subscription = \Drupal::state()->get('acquia_subscription_data');
    // Not identical since acquia_agent_has_credentials() causes stored to be
    // deleted.
    $this->assertNotSame($current_subscription, $check_subscription, 'Stored subscription data not same before connected subscription.');
    $this->assertTrue($current_subscription['active'] === FALSE, 'Default is inactive.');
    // Reset HTTP request counter;.
    \Drupal::state()->set('acquia_connector_test_request_count', 0);
    // Connect.
    $edit_fields = [
      'acquia_identifier' => $this->acqtestId,
      'acquia_key' => $this->acqtestKey,
    ];
    $this->drupalGet($this->credentialsPath);
    $this->submitForm($edit_fields, $submit_button);
    // HTTP requests should now be 3 (acquia.agent.subscription.name and
    // acquia.agent.subscription and acquia.agent.validate.
    $this->assertSame(3, \Drupal::state()->get('acquia_connector_test_request_count', 0), '3 HTTP requests were made during first connection.');
    $is_active = $subscription->isActive();
    $this->assertTrue($is_active, 'Subscription is active after successful connection.');
    $check_subscription = $subscription->update();
    \Drupal::state()->resetCache();
    $this->assertIsArray($check_subscription, 'Subscription is array after successful connection.');
    // Now stored subscription data should match.
    $stored = \Drupal::config('acquia_connector.settings');
    $this->assertSame(4, \Drupal::state()->get('acquia_connector_test_request_count', 0), '1 additional HTTP request made via acquia_agent_check_subscription().');
    $this->drupalGet($this->baseUrl);
    $this->drupalGet('admin');
    $this->assertSame(4, \Drupal::state()->get('acquia_connector_test_request_count', 0), 'No extra requests made during visits to other pages.');
    // Reset HTTP request counter;.
    \Drupal::state()->set('acquia_connector_test_request_count', 0);
    // Connect on expired subscription.
    $edit_fields = [
      'acquia_identifier' => $this->acqtestExpiredId,
      'acquia_key' => $this->acqtestExpiredKey,
    ];
    $this->drupalGet($this->credentialsPath);
    $this->submitForm($edit_fields, $submit_button);
    $this->assertSame(3, \Drupal::state()->get('acquia_connector_test_request_count', 0), '3 HTTP requests were made during expired connection attempt.');
    $is_active = $subscription->isActive();
    $this->assertFalse($is_active, 'Subscription is not active after connection with expired subscription.');
    $this->assertSame(3, \Drupal::state()->get('acquia_connector_test_request_count', 0), 'No additional HTTP requests made via acquia_agent_subscription_is_active().');
    $this->drupalGet($this->baseUrl);
    $this->drupalGet('admin');
    $this->assertSame(3, \Drupal::state()->get('acquia_connector_test_request_count', 0), 'No HTTP requests made during visits to other pages.');
    // Stored subscription data will now be the expired integer.
    $check_subscription = $subscription->update();
    \Drupal::state()->resetCache();
    $this->assertSame(1200, $check_subscription, 'Subscription is expired after connection with expired subscription.');
    $this->assertSame(4, \Drupal::state()->get('acquia_connector_test_request_count', 0), '1 additional request made via acquia_agent_check_subscription().');
    $stored = \Drupal::config('acquia_connector.settings');
    $current_subscription = \Drupal::state()->get('acquia_subscription_data');
    $this->assertSame($current_subscription, $check_subscription, 'Stored expected subscription data.');
    // Reset HTTP request counter;.
    \Drupal::state()->set('acquia_connector_test_request_count', 0);
    // Connect on subscription that will trigger a 503 response..
    $edit_fields = [
      'acquia_identifier' => $this->acqtest503Id,
      'acquia_key' => $this->acqtest503Key,
    ];
    $this->drupalGet($this->credentialsPath);
    $this->submitForm($edit_fields, $submit_button);
    $is_active = $subscription->isActive();
    $this->assertTrue($is_active, 'Subscription is active after successful connection.');
    // Make another request which will trigger 503 server error.
    $check_subscription = $subscription->update();
    \Drupal::state()->resetCache();
    // Hold onto subcription data for comparison.
    $stored = \Drupal::config('acquia_connector.settings');
    $this->assertNotSame('503', $check_subscription, 'Subscription is not storing 503.');
    $this->assertIsArray($check_subscription, 'Storing subscription array data.');
    $this->assertSame(4, \Drupal::state()->get('acquia_connector_test_request_count', 0), 'Have made 4 HTTP requests so far.');
  }

  /**
   * Tests the site status callback.
   */
  public function testAcquiaConnectorSiteStatusTests() {
    $uuid = '0dee0d07-4032-44ea-a2f2-84182dc10d54';
    $test_url = "https://insight.acquia.com/node/uuid/{$uuid}/dashboard";
    $test_data = [
      'active' => 1,
      'href' => $test_url,
    ];
    // Set some sample test data.
    \Drupal::state()->set('acquia_subscription_data', $test_data);
    // Test StatusControllerTest::getIdFromSub.
    $getIdFromSub = new StatusController();
    $key = $getIdFromSub->getIdFromSub($test_data);
    $this->assertSame($uuid, $key);
    // Add a 'uuid' key to the data and make sure that is returned.
    $test_data['uuid'] = $uuid;
    $test_data['href'] = 'http://example.com';
    $key = $getIdFromSub->getIdFromSub($test_data);
    $this->assertSame($uuid, $key);
    $query = [
      'key' => hash('sha1', "{$key}:test"),
      'nonce' => 'test',
    ];
    $json = json_decode($this->drupalGet('system/acquia-connector-status', ['query' => $query]), TRUE);
    // Test the version.
    $this->assertSame('1.0', $json['version'], 'Correct API version found.');
    // Test invalid query string parameters for access.
    // A random key value should fail.
    $query['key'] = $this->randomString(16);
    $this->drupalGet('system/acquia-connector-status', ['query' => $query]);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Clear the connection data thus simulating a disconnected site.
   */
  protected function disconnectSite() {
    $config = \Drupal::configFactory()->getEditable('acquia_connector.settings');
    \Drupal::state()->delete('acquia_subscription_data');
    \Drupal::state()->set('acquia_subscription_data', ['active' => FALSE]);
    $config->save();

    $storage = new Storage();
    $storage->setKey('');
    $storage->setIdentifier('');

    \Drupal::state()->set('acquia_connector_test_request_count', 0);
    \Drupal::state()->delete('spi.site_name');
    \Drupal::state()->delete('spi.site_machine_name');
    \Drupal::state()->resetCache();
  }

}

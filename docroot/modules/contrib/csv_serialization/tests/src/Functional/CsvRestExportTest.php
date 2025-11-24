<?php

namespace Drupal\Tests\csv_serialization\Functional;

use Drupal\Tests\views\Functional\ViewTestBase;

/**
 * Tests the CSV format for the Rest Export.
 *
 * @group csv_serialization
 */
class CsvRestExportTest extends ViewTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'csv_serialization',
    'views_ui',
    'entity_test',
    'rest_test_views',
    'node',
    'text',
    'field',
    'language',
    'basic_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_serializer_node_display_field'];

  /**
   * A user with administrative privileges to configure views.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['rest_test_views']): void {
    parent::setUp($import_test_views, $modules);

    $this->adminUser = $this->drupalCreateUser(['administer views']);

    $this->enableViewsTestModule();
  }

  /**
   * Checks that the auth options restricts access to a REST views display.
   */
  public function testRestViewsAuthentication() {
    // Assume the view is hidden behind a permission.
    $this->drupalGet('test/serialize/auth_with_perm', ['query' => ['_format' => 'csv']]);
    $this->assertSession()->statusCodeEquals(401);

    // Not even logging in would make it possible to see the view, because then
    // we are denied based on authentication method (cookie).
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('test/serialize/auth_with_perm', ['query' => ['_format' => 'csv']]);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();

    // But if we use the basic auth authentication strategy, we should be able
    // to see the page.
    $url = $this->buildUrl('test/serialize/auth_with_perm');
    \Drupal::httpClient()->get($url, [
      'auth' => [$this->adminUser->getAccountName(), $this->adminUser->pass_raw],
      'query' => [
        '_format' => 'csv',
      ],
    ]);

    $this->assertSession()->statusCodeEquals(200);
  }

}

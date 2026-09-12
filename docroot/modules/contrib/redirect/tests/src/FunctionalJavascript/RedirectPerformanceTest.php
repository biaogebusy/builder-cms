<?php

declare(strict_types=1);

namespace Drupal\Tests\redirect\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\PerformanceTestBase;
use Drupal\user\UserInterface;

/**
 * Redirect path prefix performance test.
 *
 * @group redirect
 */
class RedirectPerformanceTest extends PerformanceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect',
    'node',
    'path',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user.
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    $this->adminUser = $this->drupalCreateUser([
      'administer redirects',
      'administer redirect settings',
      'access content',
      'bypass node access',
      'create url aliases',
      'administer url aliases',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests performance and the path prefix behavior.
   */
  public function testRedirectPerformance() {

    // Create example nodes.
    $values = [
      'title' => 'First Page',
      'path' => [
        'alias' => '/prefix/first-page',
      ],
    ];
    $this->drupalCreateNode($values);
    $values = [
      'title' => 'Second Page',
      'path' => [
        'alias' => '/different/second-page',
      ],
    ];
    $this->drupalCreateNode($values);

    // Warm some global caches.
    $this->drupalGet('');

    // Access the first node, redirect query is executed and the prefix list
    // is updated.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('prefix/first-page');
    });

    $redirect_queries = array_values(array_filter($performance_data->getQueries(), fn ($query) => str_contains($query, 'redirect')));
    $expected_queries = [
      'SELECT COUNT(*) AS "expression" FROM (SELECT 1 AS "expression" FROM "redirect" "base_table" INNER JOIN "redirect" "redirect" ON "redirect"."rid" = "base_table"."rid" WHERE ("redirect"."redirect_source__path" LIKE "node/%" ESCAPE \'\\\\\') AND ("redirect"."enabled" = 1) LIMIT 1 OFFSET 0) "subquery"',
    ];
    $this->assertSame($expected_queries, $redirect_queries);

    // Access the second node, no redirect query should run since the prefix
    // list operates on the system path and node paths are already known to have
    // no redirects.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('different/second-page');
    });

    $redirect_queries = array_values(array_filter($performance_data->getQueries(), fn ($query) => str_contains($query, 'redirect')));
    $expected_queries = [];
    $this->assertSame($expected_queries, $redirect_queries);

    // Access a non-existing path, the prefix list will get updated again.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('different/non-existing');
    });

    $redirect_queries = array_values(array_filter($performance_data->getQueries(), fn ($query) => str_contains($query, 'redirect')));
    $expected_queries = [
      'SELECT COUNT(*) AS "expression" FROM (SELECT 1 AS "expression" FROM "redirect" "base_table" INNER JOIN "redirect" "redirect" ON "redirect"."rid" = "base_table"."rid" WHERE ("redirect"."redirect_source__path" LIKE "different/%" ESCAPE \'\\\\\') AND ("redirect"."enabled" = 1) LIMIT 1 OFFSET 0) "subquery"',
    ];
    $this->assertSame($expected_queries, $redirect_queries);

    // Access another non-existing path with a different case, should
    // share the same cache.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('diFFerent/non-existing');
    });

    $redirect_queries = array_values(array_filter($performance_data->getQueries(), fn ($query) => str_contains($query, 'redirect')));
    $expected_queries = [];
    $this->assertSame($expected_queries, $redirect_queries);

    // Access a path with a single component, the redirect query runs
    // but the prefix lookup is skipped.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('non-existing');
    });
    $redirect_queries = array_values(array_filter($performance_data->getQueries(), fn ($query) => str_contains($query, 'redirect')));
    $expected_queries = [
      'SELECT rid FROM "redirect" WHERE hash IN ("3S_DCeX6y8-fi0CU10OlcTxsBMnDFSwJT6ljAKY3fR4", "8bqYHlGJGVMzaiFAnRwubHO0U_qBc8j_22tRoWlScMk") AND enabled = 1 ORDER BY LENGTH(redirect_source__query) DESC',
    ];
    $this->assertSame($expected_queries, $redirect_queries);

    // Create a redirect on a node system path, this will update the prefix list
    // and the next request to any node page will execute the redirect lookup
    // query without any prefix checks.
    $this->drupalGet('admin/config/search/redirect/add');
    $page = $this->getSession()->getPage();
    $page->fillField('redirect_source[0][path]', 'node/2');
    $page->fillField('redirect_redirect[0][uri]', '/node/1');
    $page->pressButton('Save');
    $this->assertSession()->waitForText('The source path node/2 appears to be a valid path.');
    $this->assertSession()->pageTextContains('The source path node/2 appears to be a valid path.');
    $page->pressButton('Save');
    $this->assertSession()->waitForText('The redirect has been saved.');
    $this->htmlOutput($this->getSession()->getPage()->getHtml());
    $this->assertSession()->pageTextContains('The redirect has been saved.');

    // Access the node path being redirected, this will find the redirect,
    // load it, and redirect to it, which triggers another request. This should
    // result in 3 redirect lookups queries and no path prefix updates.
    $performance_data = $this->collectPerformanceData(function () {
      $this->drupalGet('different/second-page');
    });
    $this->assertSession()->addressEquals('/prefix/first-page');

    $redirect_queries = array_values(array_filter($performance_data->getQueries(), fn ($query) => str_contains($query, 'redirect')));
    $expected_queries = [
      // Initial request and redirect loop check.
      'SELECT rid FROM "redirect" WHERE hash IN ("NKzL8tFQHWuVsiKsKSy9LeHXQXJXBi02otuiixBL8TE", "hef6TjxChWEKH2Wao9m0dVOigdwgf67UkEGlfXcimoA") AND enabled = 1 ORDER BY LENGTH(redirect_source__query) DESC',
      'SELECT rid FROM "redirect" WHERE hash IN ("VHxU0vPCJr-V1OSsizmK80UDhnQONLl7Tz053h3jS7o", "bV7cMvyZ89yvlNLYL938O97yBXE9D8hRNF7i2MHxWVg") AND enabled = 1 ORDER BY LENGTH(redirect_source__query) DESC',
      // Second, redirected node page.
      'SELECT rid FROM "redirect" WHERE hash IN ("VHxU0vPCJr-V1OSsizmK80UDhnQONLl7Tz053h3jS7o", "bV7cMvyZ89yvlNLYL938O97yBXE9D8hRNF7i2MHxWVg") AND enabled = 1 ORDER BY LENGTH(redirect_source__query) DESC',
    ];
    $this->assertSame($expected_queries, $redirect_queries);
  }

}

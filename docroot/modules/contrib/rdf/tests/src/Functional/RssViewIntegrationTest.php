<?php

namespace Drupal\Tests\rdf\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test RSS View integration.
 *
 * @group rdf
 */
class RssViewIntegrationTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'rdf_test_namespaces',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Data provider for ::testRdfNamespacesAreAddedToRssViews().
   *
   * @return array[]
   *   The test cases.
   */
  public function providerRdfNamespacesAreAddedToRssViews(): array {
    return [
      'content with default settings' => [
        'rdf-rss-test-node',
      ],
      'content as fields' => [
        'rdf-rss-test-node-fields',
      ],
      'users as fields' => [
        'rdf-rss-test-user-fields',
      ],
    ];
  }

  /**
   * Tests that RSS views have RDF's XML namespaces defined.
   *
   * @param string $path
   *   The path of the RSS feed to visit.
   *
   * @dataProvider providerRdfNamespacesAreAddedToRssViews
   */
  public function testRdfNamespacesAreAddedToRssViews(string $path): void {
    $this->drupalGet($path);
    $this->assertSession()->statusCodeEquals(200);

    // Mink insists on treating the page as an HTML document, so we have to use
    // PHP's built-in DOM extension to examine the RSS feed.
    $xml = $this->getSession()->getPage()->getContent();
    $document = new \DOMDocument();
    $this->assertTrue($document->loadXML($xml));

    // Ensure that RDF's namespaces are defined on the root <rss> element.
    $namespaces = rdf_get_namespaces();
    // Views' RSS feed plugins unconditionally override the `dc` namespace.
    // @see \Drupal\views\Plugin\views\row\RssFields::render()
    // @see \Drupal\views\Plugin\views\style\Rss::render()
    $namespaces['dc'] = 'http://purl.org/dc/elements/1.1/';
    foreach ($namespaces as $prefix => $uri) {
      $this->assertSame($uri, $document->lookupNamespaceURI($prefix));
    }
  }

}

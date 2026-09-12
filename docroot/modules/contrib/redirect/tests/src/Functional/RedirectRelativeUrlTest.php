<?php

declare(strict_types=1);

namespace Drupal\Tests\redirect\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests that internal redirects use relative URLs.
 *
 * @group redirect
 */
class RedirectRelativeUrlTest extends BrowserTestBase {

  use AssertRedirectTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'redirect',
    'node',
    'path',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The redirect storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlContentEntityStorage
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->storage = \Drupal::entityTypeManager()->getStorage('redirect');
  }

  /**
   * Tests that internal redirects return relative URLs.
   */
  public function testInternalRedirectUsesRelativeUrl(): void {
    $node = $this->drupalCreateNode(['type' => 'article']);

    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('test-internal-redirect');
    $redirect->setRedirect('node/' . $node->id());
    $redirect->setStatusCode(301);
    $redirect->save();

    $response = $this->assertRedirect('test-internal-redirect', 'node/' . $node->id());
    $location = $response->getHeader('location')[0];

    // Verify the Location header is a relative URL (no scheme).
    $this->assertStringStartsWith('/', $location, 'Internal redirect should use a relative URL.');
    $this->assertEmpty(parse_url($location, PHP_URL_SCHEME), 'Internal redirect Location header must not contain a scheme.');
  }

  /**
   * Tests that redirects to the front page return relative URLs.
   */
  public function testFrontPageRedirectUsesRelativeUrl(): void {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('test-front-redirect');
    $redirect->setRedirect('<front>');
    $redirect->setStatusCode(301);
    $redirect->save();

    $response = $this->assertRedirect('test-front-redirect', '<front>');
    $location = $response->getHeader('location')[0];

    // Verify the Location header is a relative URL (no scheme).
    $this->assertEmpty(parse_url($location, PHP_URL_SCHEME), 'Front page redirect Location header must not contain a scheme.');
  }

  /**
   * Tests that internal redirects with query string passthrough stay relative.
   */
  public function testInternalRedirectWithQueryStringPassthrough(): void {
    $node = $this->drupalCreateNode(['type' => 'article']);

    // Enable query string passthrough.
    \Drupal::configFactory()->getEditable('redirect.settings')
      ->set('passthrough_querystring', TRUE)
      ->save();

    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('test-query-redirect');
    $redirect->setRedirect('node/' . $node->id());
    $redirect->setStatusCode(301);
    $redirect->save();

    // Request with an extra query parameter.
    $client = $this->getHttpClient();
    $url = $this->getAbsoluteUrl('test-query-redirect') . '?foo=bar';
    $response = $client->request('GET', $url, ['allow_redirects' => FALSE]);

    $this->assertEquals(301, $response->getStatusCode());
    $location = $response->getHeader('location')[0];

    // Verify the Location header is still a relative URL with query string.
    $this->assertEmpty(parse_url($location, PHP_URL_SCHEME), 'Internal redirect with query passthrough must not contain a scheme.');
    $this->assertStringContainsString('foo=bar', $location, 'Query string should be passed through.');
  }

  /**
   * Tests that external redirects still return absolute URLs.
   */
  public function testExternalRedirectUsesAbsoluteUrl(): void {
    /** @var \Drupal\redirect\Entity\Redirect $redirect */
    $redirect = $this->storage->create();
    $redirect->setSource('test-external-redirect');
    $redirect->redirect_redirect->set(0, ['uri' => 'https://www.example.org']);
    $redirect->setStatusCode(301);
    $redirect->save();

    $response = $this->assertRedirect('test-external-redirect', 'https://www.example.org');
    $location = $response->getHeader('location')[0];

    // Verify the Location header is an absolute URL with scheme.
    $this->assertEquals('https', parse_url($location, PHP_URL_SCHEME), 'External redirect Location header must contain an absolute URL.');
  }

}

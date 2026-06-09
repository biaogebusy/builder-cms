<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\Service\HostnameFilter;

/**
 * @coversDefaultClass \Drupal\ai\Service\HostnameFilter
 * @group ai
 */
class HostnameFilterTest extends UnitTestCase {

  /**
   * The hostname filter service.
   *
   * @var \Drupal\ai\Service\HostnameFilter
   */
  protected HostnameFilter $hostnameFilter;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerChannel;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock logger.
    $this->loggerChannel = $this->createMock(LoggerChannelInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->willReturn($this->loggerChannel);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn(
      new class {

        /**
         * Spoof of the get method.
         */
        public function get($name) {
          $settings = [
            'allowed_hosts' => [],
            'allowed_hosts_rewrite_links' => FALSE,
          ];
          return $settings[$name] ?? NULL;
        }

      }
    );

    $this->hostnameFilter = new HostnameFilter($loggerFactory, $configFactory);
  }

  /**
   * @covers ::setAllowedDomains
   * @covers ::getAllowedDomains
   */
  public function testSetAndGetAllowedDomains(): void {
    $domains = ['example.com', 'test.org'];
    $this->hostnameFilter->setAllowedDomains($domains);
    $this->assertEquals($domains, $this->hostnameFilter->getAllowedDomains());
  }

  /**
   * Test that relative URLs in HTML are always allowed.
   */
  public function testRelativeUrlsInHtmlAreAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="/path/to/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="/path/to/page"', $result);
  }

  /**
   * Test that absolute paths in HTML are always allowed.
   */
  public function testAbsolutePathsInHtmlAreAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="/absolute/path">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="/absolute/path"', $result);
  }

  /**
   * Test that allowed domain in HTML link is kept.
   */
  public function testAllowedDomainInHtmlLinkIsKept(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://example.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/page"', $result);
  }

  /**
   * Test that disallowed domain in HTML link is removed.
   */
  public function testDisallowedDomainInHtmlLinkIsRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<a href="https://evil.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('href="https://evil.com/page"', $result);
    $this->assertStringContainsString('Link', $result);
  }

  /**
   * Test that allowed domain in HTML image is kept.
   */
  public function testAllowedDomainInHtmlImageIsKept(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<img src="https://cdn.example.com/image.jpg" alt="Test">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="https://cdn.example.com/image.jpg"', $result);
  }

  /**
   * Test that disallowed domain in HTML image is removed entirely.
   */
  public function testDisallowedDomainInHtmlImageIsRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<img src="https://evil.com/image.jpg" alt="Test">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="https://evil.com/image.jpg"', $result);
    $this->assertStringNotContainsString('https://evil.com/image.jpg', $result);
  }

  /**
   * Test relative image URLs in HTML are allowed.
   */
  public function testRelativeImageUrlsInHtmlAreAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<img src="/images/photo.jpg" alt="Photo">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="/images/photo.jpg"', $result);
  }

  /**
   * Test wildcard subdomain matching.
   */
  public function testWildcardSubdomainMatching(): void {
    $this->hostnameFilter->setAllowedDomains(['*.example.com']);
    $html = '<a href="https://test.example.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://test.example.com/page"', $result);
  }

  /**
   * Test wildcard with multiple subdomain levels.
   */
  public function testWildcardMultipleSubdomainLevels(): void {
    $this->hostnameFilter->setAllowedDomains(['*.example.com']);
    $html = '<a href="https://stage.test.example.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://stage.test.example.com/page"', $result);
  }

  /**
   * Test wildcard does not match parent domain.
   */
  public function testWildcardDoesNotMatchParentDomain(): void {
    $this->hostnameFilter->setAllowedDomains(['*.example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<a href="https://example.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    // The parent domain should not match *.example.com pattern.
    $this->assertStringNotContainsString('href="https://example.com/page"', $result);
  }

  /**
   * Test Markdown link with allowed domain.
   */
  public function testMarkdownLinkWithAllowedDomain(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $markdown = '[Click here](https://example.com/page)';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals($markdown, $result);
  }

  /**
   * Test Markdown link with disallowed domain is converted to text.
   */
  public function testMarkdownLinkWithDisallowedDomainIsConvertedToText(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $markdown = '[Click here](https://evil.com/page)';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals('Click here', $result);
  }

  /**
   * Test Markdown image with allowed domain.
   */
  public function testMarkdownImageWithAllowedDomain(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $markdown = '![Alt text](https://cdn.example.com/image.jpg)';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals($markdown, $result);
  }

  /**
   * Test Markdown image with disallowed domain is removed.
   */
  public function testMarkdownImageWithDisallowedDomainIsRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $markdown = '![Alt text](https://evil.com/image.jpg)';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals('', $result);
  }

  /**
   * Test relative Markdown links are allowed.
   */
  public function testRelativeMarkdownLinksAreAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $markdown = '[Link](/path/to/page)';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals($markdown, $result);
  }

  /**
   * Test relative Markdown images are allowed.
   */
  public function testRelativeMarkdownImagesAreAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $markdown = '![Image](/images/photo.jpg)';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals($markdown, $result);
  }

  /**
   * Test Markdown link with title attribute and allowed domain.
   */
  public function testMarkdownLinkWithTitleAndAllowedDomain(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $markdown = '[Link](https://example.com/page "Title")';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals($markdown, $result);
  }

  /**
   * Test Markdown link with title and disallowed domain.
   */
  public function testMarkdownLinkWithTitleAndDisallowedDomain(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $markdown = '[Link](https://evil.com/page "Title")';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertEquals('Link', $result);
  }

  /**
   * Test multiple domains in allowed list.
   */
  public function testMultipleAllowedDomains(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com', 'test.org', 'cdn.net']);
    $html = '<a href="https://test.org/page">Test</a> <a href="https://cdn.net/file">CDN</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://test.org/page"', $result);
    $this->assertStringContainsString('href="https://cdn.net/file"', $result);
  }

  /**
   * Test mixed content with HTML and Markdown.
   */
  public function testMixedHtmlAndMarkdownContent(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->exactly(2))->method('warning');
    $text = '<a href="https://evil.com/page">HTML Link</a> and [Markdown](https://bad.com/link)';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://evil.com', $result);
    $this->assertStringNotContainsString('https://bad.com', $result);
    $this->assertStringContainsString('HTML Link', $result);
    $this->assertStringContainsString('Markdown', $result);
  }

  /**
   * Test empty allowed domains list blocks all external URLs.
   */
  public function testEmptyAllowedDomainsBlocksExternalUrls(): void {
    $this->hostnameFilter->setAllowedDomains([]);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<a href="https://example.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('href="https://example.com/page"', $result);
  }

  /**
   * Test empty allowed domains still allows relative URLs.
   */
  public function testEmptyAllowedDomainsStillAllowsRelativeUrls(): void {
    $this->hostnameFilter->setAllowedDomains([]);
    $html = '<a href="/local/path">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="/local/path"', $result);
  }

  /**
   * Test case-insensitive domain matching.
   */
  public function testCaseInsensitiveDomainMatching(): void {
    $this->hostnameFilter->setAllowedDomains(['Example.COM']);
    $html = '<a href="https://example.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/page"', $result);
  }

  /**
   * Test protocol-agnostic URLs (// syntax).
   */
  public function testProtocolAgnosticUrls(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="//example.com/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="//example.com/page"', $result);
  }

  /**
   * Test protocol-relative URL with disallowed domain is filtered.
   */
  public function testProtocolRelativeUrlDisallowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="//evil.com/steal">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('evil.com', $result);
  }

  /**
   * Test protocol-relative URL in image tag.
   */
  public function testProtocolRelativeUrlInImage(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<img src="//evil.com/track.gif" />';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('evil.com', $result);
  }

  /**
   * Test protocol-relative URL with allowed subdomain via wildcard.
   */
  public function testProtocolRelativeUrlWithWildcard(): void {
    $this->hostnameFilter->setAllowedDomains(['*.example.com']);
    $html = '<a href="//cdn.example.com/file.js">CDN</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="//cdn.example.com/file.js"', $result);
  }

  /**
   * Test URL with port number.
   */
  public function testUrlWithPortNumber(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://example.com:8080/page">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com:8080/page"', $result);
  }

  /**
   * Test URL with query parameters.
   */
  public function testUrlWithQueryParameters(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://example.com/page?foo=bar&baz=qux">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    // DOMDocument automatically encodes & as &amp; in HTML.
    $this->assertStringContainsString('href="https://example.com/page?foo=bar&amp;baz=qux"', $result);
  }

  /**
   * Test URL with hash fragment.
   */
  public function testUrlWithHashFragment(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://example.com/page#section">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/page#section"', $result);
  }

  /**
   * Test mailto links are not allowed.
   */
  public function testMailtoLinksAreNotAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="mailto:test@example.com">Email</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('mailto:test@example.com', $result);
  }

  /**
   * Test anchor-only links are allowed.
   */
  public function testAnchorOnlyLinksAreAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="#section">Jump to section</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="#section"', $result);
  }

  /**
   * Test complex HTML with multiple elements.
   */
  public function testComplexHtmlWithMultipleElements(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com', 'cdn.example.com']);
    $this->loggerChannel->expects($this->exactly(2))->method('warning');
    $html = <<<HTML
<div>
  <a href="https://example.com/page">Allowed Link</a>
  <a href="https://evil.com/page">Bad Link</a>
  <img src="https://cdn.example.com/image.jpg" alt="Good Image">
  <img src="https://bad.com/image.jpg" alt="Bad Image">
  <a href="/local">Local Link</a>
</div>
HTML;
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/page"', $result);
    $this->assertStringNotContainsString('https://evil.com', $result);
    $this->assertStringContainsString('src="https://cdn.example.com/image.jpg"', $result);
    $this->assertStringNotContainsString('https://bad.com', $result);
    $this->assertStringContainsString('href="/local"', $result);
  }

  /**
   * Test HTML link rewriting when rewrite mode is enabled.
   */
  public function testHtmlLinkRewritingWhenEnabled(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<a href="https://evil.com/page?data=exfiltration">Click here</a>';
    $result = $this->hostnameFilter->filterText($html);
    // The link text should be replaced with the URL.
    $this->assertStringContainsString('https://evil.com/page?data=exfiltration', $result);
    $this->assertStringNotContainsString('Click here', $result);
  }

  /**
   * Test HTML link removal when rewrite mode is disabled (default).
   */
  public function testHtmlLinkRemovalWhenRewriteDisabled(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->hostnameFilter->setRewriteLinks(FALSE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<a href="https://evil.com/page">Click here</a>';
    $result = $this->hostnameFilter->filterText($html);
    // The link should be removed but text kept.
    $this->assertStringNotContainsString('href="https://evil.com/page"', $result);
    $this->assertStringContainsString('Click here', $result);
  }

  /**
   * Test Markdown link rewriting when rewrite mode is enabled.
   */
  public function testMarkdownLinkRewritingWhenEnabled(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $markdown = '[Click here](https://evil.com/page?data=exfiltration)';
    $result = $this->hostnameFilter->filterText($markdown);
    // Should be rewritten to show URL as both text and link.
    $expected = '[https://evil.com/page?data=exfiltration](https://evil.com/page?data=exfiltration)';
    $this->assertEquals($expected, $result);
  }

  /**
   * Test Markdown link removal when rewrite mode is disabled (default).
   */
  public function testMarkdownLinkRemovalWhenRewriteDisabled(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->hostnameFilter->setRewriteLinks(FALSE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $markdown = '[Click here](https://evil.com/page)';
    $result = $this->hostnameFilter->filterText($markdown);
    // Should return just the text.
    $this->assertEquals('Click here', $result);
  }

  /**
   * Test that rewrite mode does not affect allowed links.
   */
  public function testRewriteModeDoesNotAffectAllowedLinks(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $html = '<a href="https://example.com/page">Click here</a>';
    $result = $this->hostnameFilter->filterText($html);
    // Allowed links should remain unchanged.
    $this->assertStringContainsString('href="https://example.com/page"', $result);
    $this->assertStringContainsString('Click here', $result);
  }

  /**
   * Test applying settings from DTO.
   */
  public function testApplySettingsFromDto(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com', '*.cdn.example.com'],
      rewriteLinks: TRUE,
      fullTrust: FALSE,
    );
    $this->hostnameFilter->applySettings($dto);

    // Test that allowed domains were set.
    $this->assertEquals(['example.com', '*.cdn.example.com'], $this->hostnameFilter->getAllowedDomains());

    // Test that rewrite mode was enabled.
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<a href="https://evil.com/page">Click</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('https://evil.com/page', $result);
  }

  /**
   * Test applying partial settings from DTO.
   */
  public function testApplyPartialSettingsFromDto(): void {
    // Set initial values.
    $this->hostnameFilter->setAllowedDomains(['initial.com']);
    $this->hostnameFilter->setRewriteLinks(FALSE);

    // Apply DTO with only rewriteLinks set.
    $dto = new HostnameFilterDto(
      rewriteLinks: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);

    // Allowed domains should remain unchanged.
    $this->assertEquals(['initial.com'], $this->hostnameFilter->getAllowedDomains());

    // But rewrite mode should be enabled.
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<a href="https://evil.com/page">Click</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('https://evil.com/page', $result);
  }

  /**
   * Test full trust mode via DTO.
   */
  public function testFullTrustModeViaDto(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      fullTrust: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);

    // With full trust, disallowed links should NOT be filtered.
    $html = '<a href="https://evil.com/page">Click</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://evil.com/page"', $result);
    $this->assertStringContainsString('Click', $result);
  }

  /**
   * Test that disallowed domain in HTML image is removed entirely for partial.
   */
  public function testDisallowedDomainInHtmlImageWithoutProtocol(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<img src="//t.co/image.jpg" alt="Test">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="//t.co/image.jpg"', $result);
  }

  /**
   * Test that broken HTML doesn't slip through.
   */
  public function testBrokenLinkDoesNotSlipThrough(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '< a href="https://evil.com/page">Click here</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $result);
    $this->assertStringNotContainsString('< a href="https://evil.com/page"', $result);
  }

  /**
   * Test that broken HTML doesn't slip through.
   */
  public function testBrokenLongLinkDoesNotSlipThrough(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="     https://evil.com/page">Click here</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $result);
    $this->assertStringNotContainsString('< a href="https://evil.com/page"', $result);
  }

  /**
   * Tests that unclosed HTML doesn't slip through.
   */
  public function testUnclosedHtmlDoesNotSlipThrough(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://evil.com/page">Click here';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $result);
    $this->assertStringContainsString('Click here', $result);
  }

  /**
   * Test DTO create method and toArray.
   */
  public function testDtoCreateAndToArray(): void {
    $data = [
      'allowedDomainNames' => ['example.com', 'test.org'],
      'rewriteLinks' => TRUE,
      'fullTrust' => FALSE,
      'plainTextMode' => FALSE,
    ];

    $dto = HostnameFilterDto::create($data);
    $this->assertEquals(['example.com', 'test.org'], $dto->allowedDomainNames);
    $this->assertTrue($dto->rewriteLinks);
    $this->assertFalse($dto->fullTrust);
    $this->assertFalse($dto->plainTextMode);

    $array = $dto->toArray();
    $this->assertEquals($data, $array);
  }

  /**
   * Test area tag with allowed href.
   */
  public function testAreaTagWithAllowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<map><area shape="rect" coords="0,0,100,100" href="https://example.com/page" alt="Area"></map>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/page"', $result);
  }

  /**
   * Test area tag with disallowed href is removed.
   */
  public function testAreaTagWithDisallowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<map><area shape="rect" coords="0,0,100,100" href="https://evil.com/page" alt="Area"></map>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('href="https://evil.com/page"', $result);
  }

  /**
   * Test img tag with allowed src.
   */
  public function testImgTagWithAllowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<img src="https://cdn.example.com/image.jpg" alt="Image">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="https://cdn.example.com/image.jpg"', $result);
  }

  /**
   * Test img tag with disallowed src is removed.
   */
  public function testImgTagWithDisallowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<img src="https://evil.com/image.jpg" alt="Image">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="https://evil.com/image.jpg"', $result);
  }

  /**
   * Test img tag with allowed srcset.
   */
  public function testImgTagWithAllowedSrcset(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<img srcset="https://cdn.example.com/image-1x.jpg 1x, https://cdn.example.com/image-2x.jpg 2x" alt="Image">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('srcset="https://cdn.example.com/image-1x.jpg 1x, https://cdn.example.com/image-2x.jpg 2x"', $result);
  }

  /**
   * Test img tag with disallowed srcset is removed.
   */
  public function testImgTagWithDisallowedSrcset(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<img srcset="https://evil.com/image-1x.jpg 1x" alt="Image">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('srcset="https://evil.com/image-1x.jpg 1x"', $result);
  }

  /**
   * Test source tag with allowed src.
   */
  public function testSourceTagWithAllowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<picture><source src="https://cdn.example.com/image.webp" type="image/webp"></picture>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="https://cdn.example.com/image.webp"', $result);
  }

  /**
   * Test source tag with disallowed src is removed.
   */
  public function testSourceTagWithDisallowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<picture><source src="https://evil.com/image.webp" type="image/webp"></picture>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="https://evil.com/image.webp"', $result);
  }

  /**
   * Test source tag with allowed srcset.
   */
  public function testSourceTagWithAllowedSrcset(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<picture><source srcset="https://cdn.example.com/image.webp" type="image/webp"></picture>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('srcset="https://cdn.example.com/image.webp"', $result);
  }

  /**
   * Test source tag with disallowed srcset is removed.
   */
  public function testSourceTagWithDisallowedSrcset(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<picture><source srcset="https://evil.com/image.webp" type="image/webp"></picture>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('srcset="https://evil.com/image.webp"', $result);
  }

  /**
   * Test video tag with allowed src.
   */
  public function testVideoTagWithAllowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<video src="https://cdn.example.com/video.mp4" controls></video>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="https://cdn.example.com/video.mp4"', $result);
  }

  /**
   * Test video tag with disallowed src is removed.
   */
  public function testVideoTagWithDisallowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<video src="https://evil.com/video.mp4" controls></video>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="https://evil.com/video.mp4"', $result);
  }

  /**
   * Test audio tag with allowed src.
   */
  public function testAudioTagWithAllowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<audio src="https://cdn.example.com/audio.mp3" controls></audio>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="https://cdn.example.com/audio.mp3"', $result);
  }

  /**
   * Test audio tag with disallowed src is removed.
   */
  public function testAudioTagWithDisallowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<audio src="https://evil.com/audio.mp3" controls></audio>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="https://evil.com/audio.mp3"', $result);
  }

  /**
   * Test track tag with allowed src.
   */
  public function testTrackTagWithAllowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<video controls><track src="https://cdn.example.com/captions.vtt" kind="captions"></video>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="https://cdn.example.com/captions.vtt"', $result);
  }

  /**
   * Test track tag with disallowed src is removed.
   */
  public function testTrackTagWithDisallowedSrc(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<video controls><track src="https://evil.com/captions.vtt" kind="captions"></video>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="https://evil.com/captions.vtt"', $result);
  }

  /**
   * Test iframe tag is completely removed (security risk).
   */
  public function testIframeTagIsCompletelyRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<div><iframe src="https://example.com/embed" width="500" height="300"></iframe></div>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<iframe', $result);
    $this->assertStringNotContainsString('https://example.com/embed', $result);
  }

  /**
   * Test iframe tag with disallowed domain is completely removed.
   */
  public function testIframeTagWithDisallowedSrcIsRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<div><iframe src="https://evil.com/embed" width="500" height="300"></iframe></div>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<iframe', $result);
    $this->assertStringNotContainsString('https://evil.com/embed', $result);
  }

  /**
   * Test embed tag is completely removed (security risk).
   */
  public function testEmbedTagIsCompletelyRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<embed src="https://example.com/plugin.swf" type="application/x-shockwave-flash">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<embed', $result);
    $this->assertStringNotContainsString('https://example.com/plugin.swf', $result);
  }

  /**
   * Test object tag is completely removed (security risk).
   */
  public function testObjectTagIsCompletelyRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<object data="https://example.com/object.pdf" type="application/pdf"></object>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<object', $result);
    $this->assertStringNotContainsString('https://example.com/object.pdf', $result);
  }

  /**
   * Test link tag with allowed href.
   */
  public function testLinkTagWithAllowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<link rel="stylesheet" href="https://cdn.example.com/style.css">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://cdn.example.com/style.css"', $result);
  }

  /**
   * Test link tag with disallowed href is removed.
   */
  public function testLinkTagWithDisallowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<link rel="stylesheet" href="https://evil.com/style.css">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('href="https://evil.com/style.css"', $result);
  }

  /**
   * Test script tag is completely removed (security risk).
   */
  public function testScriptTagIsCompletelyRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<script src="https://cdn.example.com/script.js"></script>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<script', $result);
    $this->assertStringNotContainsString('https://cdn.example.com/script.js', $result);
  }

  /**
   * Test script tag with inline code is removed.
   */
  public function testScriptTagWithInlineCodeIsRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<script>alert("XSS");</script>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<script', $result);
    $this->assertStringNotContainsString('alert', $result);
  }

  /**
   * Test form tag with allowed action.
   */
  public function testFormTagWithAllowedAction(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<form action="https://example.com/submit" method="post"><input type="text"></form>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('action="https://example.com/submit"', $result);
  }

  /**
   * Test form tag with disallowed action is removed.
   */
  public function testFormTagWithDisallowedAction(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<form action="https://evil.com/exfiltrate" method="post"><input type="text"></form>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('action="https://evil.com/exfiltrate"', $result);
  }

  /**
   * Test button tag with allowed formaction.
   */
  public function testButtonTagWithAllowedFormaction(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<button type="submit" formaction="https://example.com/submit">Submit</button>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('formaction="https://example.com/submit"', $result);
  }

  /**
   * Test button tag with disallowed formaction is removed.
   */
  public function testButtonTagWithDisallowedFormaction(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<button type="submit" formaction="https://evil.com/exfiltrate">Submit</button>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('formaction="https://evil.com/exfiltrate"', $result);
  }

  /**
   * Test use tag with allowed href (SVG).
   */
  public function testUseTagWithAllowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<svg><use href="https://cdn.example.com/icons.svg#icon"></use></svg>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://cdn.example.com/icons.svg#icon"', $result);
  }

  /**
   * Test use tag with disallowed href is removed.
   */
  public function testUseTagWithDisallowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<svg><use href="https://evil.com/icons.svg#icon"></use></svg>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('href="https://evil.com/icons.svg#icon"', $result);
  }

  /**
   * Test use tag with allowed xlink:href (SVG legacy).
   */
  public function testUseTagWithAllowedXlinkHref(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $html = '<svg><use xlink:href="https://cdn.example.com/icons.svg#icon"></use></svg>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('xlink:href="https://cdn.example.com/icons.svg#icon"', $result);
  }

  /**
   * Test use tag with disallowed xlink:href is removed.
   */
  public function testUseTagWithDisallowedXlinkHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<svg><use xlink:href="https://evil.com/icons.svg#icon"></use></svg>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('xlink:href="https://evil.com/icons.svg#icon"', $result);
  }

  /**
   * Test base tag with allowed href.
   */
  public function testBaseTagWithAllowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<base href="https://example.com/">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/"', $result);
  }

  /**
   * Test base tag with allowed href.
   */
  public function testLinkWithBrokenupHost(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://example.com/"><a href="https://www.evil.com/
    continue/url"';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/"', $result);
    $this->assertStringNotContainsString('href="https://www.evil.com/continue/url"', $result);
  }

  /**
   * Test base tag with disallowed href is removed.
   */
  public function testBaseTagWithDisallowedHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<base href="https://evil.com/">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('href="https://evil.com/"', $result);
  }

  /**
   * Test meta tag with allowed content URL.
   */
  public function testMetaTagWithAllowedContentUrl(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<meta property="og:image" content="https://example.com/image.jpg">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('content="https://example.com/image.jpg"', $result);
  }

  /**
   * Test meta tag with disallowed content URL is removed.
   */
  public function testMetaTagWithDisallowedContentUrl(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->loggerChannel->expects($this->once())->method('warning');
    $html = '<meta property="og:image" content="https://evil.com/image.jpg">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('content="https://evil.com/image.jpg"', $result);
  }

  /**
   * Test inline style with url() is removed.
   */
  public function testInlineStyleWithUrlIsRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<div style="background: url(https://evil.com/bg.jpg);">Content</div>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('style=', $result);
    $this->assertStringNotContainsString('https://evil.com/bg.jpg', $result);
    $this->assertStringContainsString('Content', $result);
  }

  /**
   * Test multiple dangerous tags in one HTML fragment.
   */
  public function testMultipleDangerousTagsAreAllRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = <<<HTML
<div>
  <script src="https://evil.com/xss.js"></script>
  <iframe src="https://evil.com/phishing"></iframe>
  <embed src="https://evil.com/malware.swf">
  <object data="https://evil.com/exploit.pdf"></object>
  <p>Safe content</p>
</div>
HTML;
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<script', $result);
    $this->assertStringNotContainsString('<iframe', $result);
    $this->assertStringNotContainsString('<embed', $result);
    $this->assertStringNotContainsString('<object', $result);
    $this->assertStringNotContainsString('https://evil.com', $result);
    $this->assertStringContainsString('Safe content', $result);
  }

  /**
   * Test mixed allowed and disallowed multimedia tags.
   */
  public function testMixedAllowedAndDisallowedMultimediaTags(): void {
    $this->hostnameFilter->setAllowedDomains(['cdn.example.com']);
    $this->loggerChannel->expects($this->exactly(2))->method('warning');
    $html = <<<HTML
<video src="https://cdn.example.com/video.mp4" controls></video>
<audio src="https://evil.com/audio.mp3" controls></audio>
<img src="https://cdn.example.com/image.jpg" alt="Good">
<img src="https://evil.com/track.gif" alt="Bad">
HTML;
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('src="https://cdn.example.com/video.mp4"', $result);
    $this->assertStringContainsString('src="https://cdn.example.com/image.jpg"', $result);
    $this->assertStringNotContainsString('https://evil.com/audio.mp3', $result);
    $this->assertStringNotContainsString('https://evil.com/track.gif', $result);
  }

  /**
   * Test URL that is the prefix, so its not based on substr but parsing.
   */
  public function testUrlThatIsPrefix(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    // Try first with the actual domain.
    $html = '<a href="https://example.com/page?foo=bar&baz=qux">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://example.com/page?foo=bar&amp;baz=qux"', $result);
    // The try to suffix it with spoof.
    $html = '<a href="https://example.com.evil.com/page?foo=bar&baz=qux">Link</a>';
    $this->loggerChannel->expects($this->once())->method('warning');
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('href="https://example.com.evil.com/page?foo=bar&amp;baz=qux"', $result);
  }

  /**
   * Test plainTextMode removes all none white listed URLs.
   */
  public function testplainTextModeRemovesAllExternalUrls(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com', 'trusted.com']);
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://example.com and https://evil.com for more info';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringContainsString('https://example.com', $result);
    $this->assertStringNotContainsString('https://evil.com', $result);
    $this->assertStringContainsString('Visit', $result);
    $this->assertStringContainsString('for more info', $result);
  }

  /**
   * Test plainTextMode should not remove allowed domain links.
   */
  public function testplainTextModeRemovesAllowedDomainLinksFromText(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Check out https://example.com/page for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringContainsString('https://example.com/page', $result);
    $this->assertStringContainsString('Check out', $result);
    $this->assertStringContainsString('for details', $result);
  }

  /**
   * Test plainTextMode removes www URLs.
   */
  public function testplainTextModeAllowsWwwUrls(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit www.example.com for more';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringContainsString('www.example.com', $result);
    $this->assertStringContainsString('Visit', $result);
    $this->assertStringContainsString('for more', $result);
  }

  /**
   * Test plainTextMode removes bare domain URLs.
   */
  public function testplainTextModeAllowsBareDomainUrls(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit example.com/path for more';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringContainsString('example.com/path', $result);
    $this->assertStringContainsString('Visit', $result);
    $this->assertStringContainsString('for more', $result);
  }

  /**
   * Test plainTextMode removes multiple URLs from text.
   */
  public function testplainTextModeRemovesMultipleUrls(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://example.com, www.test.org, and evil.com/bad for info';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com', $result);
    $this->assertStringContainsString('www.test.org', $result);
    $this->assertStringContainsString('evil.com/bad', $result);
    $this->assertStringContainsString('Visit', $result);
    $this->assertStringContainsString('for info', $result);
  }

  /**
   * Test plainTextMode with http URLs.
   */
  public function testplainTextModeRemovesHttpUrls(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit http://example.com for more';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('http://example.com', $result);
    $this->assertStringContainsString('Visit', $result);
  }

  /**
   * Test plainTextMode preserves relative paths.
   */
  public function testplainTextModePreservesRelativePaths(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit /local/path and /another/page for more';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringContainsString('/local/path', $result);
    $this->assertStringContainsString('/another/page', $result);
  }

  /**
   * Test plainTextMode preserves anchor links.
   */
  public function testplainTextModePreservesAnchorLinks(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Jump to #section or #another-section';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringContainsString('#section', $result);
    $this->assertStringContainsString('#another-section', $result);
  }

  /**
   * Test plainTextMode with URLs containing query parameters.
   */
  public function testplainTextModeRemovesUrlsWithQueryParams(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://example.com/page?foo=bar&baz=qux for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com/page?foo=bar&baz=qux', $result);
    $this->assertStringContainsString('Visit', $result);
    $this->assertStringContainsString('for details', $result);
  }

  /**
   * Test plainTextMode with URLs containing hash fragments.
   */
  public function testplainTextModeRemovesUrlsWithHashFragments(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://example.com/page#section for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com/page#section', $result);
  }

  /**
   * Test plainTextMode with subdomain URLs.
   */
  public function testplainTextModeRemovesSubdomainUrls(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://subdomain.example.com for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://subdomain.example.com', $result);
  }

  /**
   * Test plainTextMode with port numbers in URLs.
   */
  public function testplainTextModeRemovesUrlsWithPorts(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://example.com:8080/page for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com:8080/page', $result);
  }

  /**
   * Test plainTextMode does not affect fullTrust mode.
   */
  public function testplainTextModeDoesNotAffectFullTrust(): void {
    $this->hostnameFilter->setFullTrust(TRUE);
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit https://example.com and https://evil.com';
    $result = $this->hostnameFilter->filterText($text);
    // Full trust should bypass all filtering, including plainTextMode.
    $this->assertStringContainsString('https://example.com', $result);
    $this->assertStringContainsString('https://evil.com', $result);
  }

  /**
   * Test applying plainTextMode from DTO.
   */
  public function testApplyplainTextModeFromDto(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      plainTextMode: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);

    $text = 'Visit https://example.com for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringContainsString('https://example.com', $result);
  }

  /**
   * Test DTO with plainTextMode set to false.
   */
  public function testDtoWithplainTextModeFalse(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      plainTextMode: FALSE,
    );
    $this->hostnameFilter->applySettings($dto);

    $text = 'Visit https://example.com for details';
    $result = $this->hostnameFilter->filterText($text);
    // Should apply normal filtering - allowed domains are kept.
    $this->assertStringContainsString('https://example.com', $result);
  }

  /**
   * Test plainTextMode with mixed content.
   */
  public function testplainTextModeWithMixedContent(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = <<<TEXT
Here is some text with URLs:
- https://example.com/page
- www.test.org
- Check /local/path
- evil.com/bad
- And #anchor links
TEXT;
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com/page', $result);
    $this->assertStringContainsString('www.test.org', $result);
    $this->assertStringContainsString('evil.com/bad', $result);
    $this->assertStringContainsString('/local/path', $result);
    $this->assertStringContainsString('#anchor', $result);
    $this->assertStringContainsString('Here is some text', $result);
  }

  /**
   * Test plainTextMode with URLs at end of sentences.
   */
  public function testplainTextModeWithUrlsAtEndOfSentences(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://example.com. Also check www.test.org!';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com', $result);
    $this->assertStringContainsString('www.test.org', $result);
    $this->assertStringContainsString('Visit', $result);
    $this->assertStringContainsString('Also check', $result);
  }

  /**
   * Test plainTextMode with URLs in parentheses.
   */
  public function testplainTextModeWithUrlsInParentheses(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'See documentation (https://example.com/docs) for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com/docs', $result);
    $this->assertStringContainsString('See documentation', $result);
    $this->assertStringContainsString('for details', $result);
  }

  /**
   * Test plainTextMode preserves mailto links.
   */
  public function testplainTextModePreservesMailtoLinks(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Contact us at mailto:info@example.com';
    $result = $this->hostnameFilter->filterText($text);
    // Bare domains are no longer filtered, so mailto stays intact.
    $this->assertStringContainsString('mailto:info@example.com', $result);
  }

  /**
   * Test plainTextMode with international domain names.
   */
  public function testplainTextModeWithInternationalDomains(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit https://例え.jp for details';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://例え.jp', $result);
  }

  /**
   * Test plainTextMode with very long URLs.
   */
  public function testplainTextModeWithLongUrls(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $longPath = str_repeat('/path', 50);
    $text = "Visit https://example.com{$longPath} for details";
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('https://example.com' . $longPath, $result);
    $this->assertStringContainsString('Visit', $result);
  }

  /**
   * Test plainTextMode with IP addresses.
   */
  public function testplainTextModeWithIpAddresses(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit http://192.168.1.1 for router config';
    $result = $this->hostnameFilter->filterText($text);
    // IP addresses might not be caught by domain regex.
    // This tests current behavior.
    // If IPs should be removed, update the implementation accordingly.
    $this->assertStringContainsString('Visit', $result);
  }

  /**
   * Test plainTextMode with localhost URLs.
   */
  public function testplainTextModeWithLocalhostUrls(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $this->loggerChannel->expects($this->once())->method('warning');
    $text = 'Visit http://localhost:3000 for dev server';
    $result = $this->hostnameFilter->filterText($text);
    $this->assertStringNotContainsString('http://localhost:3000', $result);
  }

  /**
   * Test a streamed partial URL that is broken across lines.
   */
  public function testStreamInitialPartial(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('<a></a>', $result);
  }

  /**
   * Test a streamed partial URL that is broken across lines.
   */
  public function testStreamPartial(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = 'testbroken.com">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('testbroken.com', $result);
  }

  /**
   * Test that javascript: URIs are blocked.
   */
  public function testJavascriptUriBlocked(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="javascript:alert(1)">Click</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('javascript:', $result);
    $this->assertStringContainsString('Click', $result);
  }

  /**
   * Test that data: URIs are blocked.
   */
  public function testDataUriBlocked(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<img src="data:image/svg+xml,<svg onload=alert(1)>" alt="xss">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('data:', $result);
  }

  /**
   * Test that vbscript: URIs are blocked.
   */
  public function testVbscriptUriBlocked(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="vbscript:MsgBox(1)">Click</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('vbscript:', $result);
  }

  /**
   * Test that ftp:// URIs are blocked.
   */
  public function testFtpUriBlocked(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="ftp://example.com/file">Download</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('ftp://', $result);
  }

  /**
   * Test that public:// scheme is allowed.
   */
  public function testPublicSchemeAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<img src="public://images/photo.jpg" alt="Photo">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('public://images/photo.jpg', $result);
  }

  /**
   * Test that srcset with mixed domains is handled correctly.
   */
  public function testSrcsetMixedDomains(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<img src="https://example.com/img.jpg" srcset="https://example.com/img-2x.jpg 2x, https://evil.com/img-3x.jpg 3x">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('srcset', $result);
    $this->assertStringContainsString('src="https://example.com/img.jpg"', $result);
  }

  /**
   * Test that srcset with all allowed domains is kept.
   */
  public function testSrcsetAllAllowed(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com', 'cdn.example.com']);
    $html = '<img src="https://example.com/img.jpg" srcset="https://example.com/img-2x.jpg 2x, https://cdn.example.com/img-3x.jpg 3x">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('srcset', $result);
  }

  /**
   * Test that onerror event handler is stripped.
   */
  public function testOnerrorEventHandlerStripped(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<img src="https://example.com/img.jpg" onerror="alert(1)">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('onerror', $result);
    $this->assertStringContainsString('src="https://example.com/img.jpg"', $result);
  }

  /**
   * Test that onload event handler is stripped.
   */
  public function testOnloadEventHandlerStripped(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<body onload="alert(1)"><p>text</p></body>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('onload', $result);
    $this->assertStringContainsString('text', $result);
  }

  /**
   * Test that onclick event handler is stripped.
   */
  public function testOnclickEventHandlerStripped(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://example.com" onclick="steal()">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('onclick', $result);
    $this->assertStringContainsString('href="https://example.com"', $result);
  }

  /**
   * Test that onmouseover event handler is stripped.
   */
  public function testOnmouseoverEventHandlerStripped(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<div onmouseover="alert(1)">Hover me</div>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('onmouseover', $result);
    $this->assertStringContainsString('Hover me', $result);
  }

  /**
   * Test that style tags are removed entirely.
   */
  public function testStyleTagRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<style>body { background: url("https://evil.com/track.gif"); }</style><p>Content</p>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('<style>', $result);
    $this->assertStringNotContainsString('evil.com', $result);
    $this->assertStringContainsString('Content', $result);
  }

  /**
   * Test that ping attribute with disallowed URL is removed.
   */
  public function testPingAttributeRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="https://example.com" ping="https://tracker.evil.com/log">Link</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('ping', $result);
    $this->assertStringContainsString('href="https://example.com"', $result);
  }

  /**
   * Test that video poster attribute with disallowed URL is removed.
   */
  public function testVideoPosterRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<video src="https://example.com/vid.mp4" poster="https://evil.com/poster.jpg"></video>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('poster', $result);
    $this->assertStringContainsString('src="https://example.com/vid.mp4"', $result);
  }

  /**
   * Test that input image src with disallowed URL is removed.
   */
  public function testInputImageSrcRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<input type="image" src="https://evil.com/button.png">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('src="https://evil.com', $result);
  }

  /**
   * Test that background attribute with disallowed URL is removed.
   */
  public function testBackgroundAttributeRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<td background="https://evil.com/bg.jpg">Cell</td>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('background', $result);
    $this->assertStringContainsString('Cell', $result);
  }

  /**
   * Test that input formaction with disallowed URL is removed.
   */
  public function testInputFormactionRemoved(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<input type="submit" formaction="https://evil.com/steal">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('formaction', $result);
  }

  /**
   * Test combined attack: javascript URI + event handler + style tag.
   */
  public function testCombinedAttack(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $html = '<a href="javascript:void(0)" onclick="steal()">Click</a><style>.x{background:url(https://evil.com/t)}</style><img src="https://evil.com/img.jpg" onerror="alert(1)">';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringNotContainsString('javascript:', $result);
    $this->assertStringNotContainsString('onclick', $result);
    $this->assertStringNotContainsString('<style>', $result);
    $this->assertStringNotContainsString('onerror', $result);
    $this->assertStringNotContainsString('evil.com', $result);
  }

  /**
   * Test rewrite mode keeps href attribute on disallowed links.
   */
  public function testRewriteModeKeepsHref(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $html = '<a href="https://evil.com/page">Click here</a>';
    $result = $this->hostnameFilter->filterText($html);
    $this->assertStringContainsString('href="https://evil.com/page"', $result);
    $this->assertStringContainsString('https://evil.com/page</a>', $result);
  }

  /**
   * Test that javascript: URI in Markdown link is blocked.
   */
  public function testJavascriptUriInMarkdownBlocked(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $markdown = '[Click](javascript:alert(1))';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertStringNotContainsString('javascript:', $result);
    $this->assertStringContainsString('Click', $result);
  }

  /**
   * Test that data: URI in Markdown image is blocked.
   */
  public function testDataUriInMarkdownImageBlocked(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    $markdown = '![xss](data:text/html,<script>alert(1)</script>)';
    $result = $this->hostnameFilter->filterText($markdown);
    $this->assertStringNotContainsString('data:', $result);
  }

  /**
   * Test that tool calls also are filtered for URLs.
   */
  public function testToolCallsAreFiltered(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    // YAML output.
    $yaml = <<<YAML
    operations:
      - target: content
        placement: inside
        components:
          - sdc.mercury.text:
              props:
                text: "Phishing is a common type of cyberattack in which attackers trick users into clicking a malicious link, such as <a href=\"https://phishing.com\">Click here for free gift</a>. This link is a demo. The goal is usually to steal sensitive information or install malware."
                text_size: normal
                text_color: default
    YAML;
    $result = $this->hostnameFilter->filterText($yaml);
    $this->assertStringNotContainsString('href="https://phishing.com"', $result);
  }

  /**
   * Test that tool calls also passed through.
   */
  public function testToolCallsArePassedThrough(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com']);
    // YAML output.
    $yaml = <<<YAML
    operations:
      - target: content
        placement: inside
        components:
          - sdc.mercury.text:
              props:
                text: "Visit our site at https://example.com for more info."
                text_size: normal
                text_color: default
    YAML;
    $result = $this->hostnameFilter->filterText($yaml);
    $this->assertStringContainsString('https://example.com', $result);
  }

}

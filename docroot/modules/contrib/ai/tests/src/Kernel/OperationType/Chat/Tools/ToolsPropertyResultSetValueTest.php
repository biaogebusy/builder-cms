<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\Chat\Tools;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult;

/**
 * Tests ToolsPropertyResult::setValue() hostname filtering for various types.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult
 * @group ai
 */
class ToolsPropertyResultSetValueTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * The hostname filter service.
   *
   * @var \Drupal\ai\Service\HostnameFilter
   */
  protected $hostnameFilter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['ai']);

    // Configure allowed domains for filtering.
    $this->hostnameFilter = \Drupal::service('ai.hostname_filter_service');
    $this->hostnameFilter->setAllowedDomains(['example.com', '*.example.com']);
  }

  /**
   * Helper to create a ToolsPropertyResult and set a value.
   *
   * @param mixed $value
   *   The value to set.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResult
   *   The result object.
   */
  protected function createResultWithValue($value): ToolsPropertyResult {
    $result = new ToolsPropertyResult();
    $result->setName('test_property');
    $result->setValue($value);
    return $result;
  }

  /**
   * Test that integer values pass through unchanged.
   */
  public function testIntegerValueUnchanged(): void {
    $result = $this->createResultWithValue(42);
    $this->assertSame(42, $result->getValue());
  }

  /**
   * Test that zero integer passes through unchanged.
   */
  public function testIntegerZeroUnchanged(): void {
    $result = $this->createResultWithValue(0);
    $this->assertSame(0, $result->getValue());
  }

  /**
   * Test that negative integer passes through unchanged.
   */
  public function testIntegerNegativeUnchanged(): void {
    $result = $this->createResultWithValue(-100);
    $this->assertSame(-100, $result->getValue());
  }

  /**
   * Test that boolean true passes through unchanged.
   */
  public function testBooleanTrueUnchanged(): void {
    $result = $this->createResultWithValue(TRUE);
    $this->assertTrue($result->getValue());
  }

  /**
   * Test that boolean false passes through unchanged.
   */
  public function testBooleanFalseUnchanged(): void {
    $result = $this->createResultWithValue(FALSE);
    $this->assertFalse($result->getValue());
  }

  /**
   * Test that float value passes through unchanged.
   */
  public function testFloatValueUnchanged(): void {
    $result = $this->createResultWithValue(3.14);
    $this->assertSame(3.14, $result->getValue());
  }

  /**
   * Test that float zero passes through unchanged.
   */
  public function testFloatZeroUnchanged(): void {
    $result = $this->createResultWithValue(0.0);
    $this->assertSame(0.0, $result->getValue());
  }

  /**
   * Test that negative float passes through unchanged.
   */
  public function testFloatNegativeUnchanged(): void {
    $result = $this->createResultWithValue(-2.5);
    $this->assertSame(-2.5, $result->getValue());
  }

  /**
   * Test that object passes through unchanged.
   */
  public function testObjectUnchanged(): void {
    $obj = new \stdClass();
    $obj->name = 'test';
    $obj->url = 'https://evil.com/should-not-be-filtered';
    $result = $this->createResultWithValue($obj);
    $value = $result->getValue();
    $this->assertInstanceOf(\stdClass::class, $value);
    $this->assertSame('test', $value->name);
    // Objects are not filtered by setValue.
    $this->assertSame('https://evil.com/should-not-be-filtered', $value->url);
  }

  /**
   * Test that NULL passes through unchanged.
   */
  public function testNullUnchanged(): void {
    $result = $this->createResultWithValue(NULL);
    $this->assertNull($result->getValue());
  }

  /**
   * Test that a string with an allowed URL is kept.
   */
  public function testStringWithAllowedUrlIsKept(): void {
    $text = '<a href="https://example.com/page">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://example.com/page"', $result->getValue());
  }

  /**
   * Test that a string with a disallowed URL is filtered.
   */
  public function testStringWithDisallowedUrlIsFiltered(): void {
    $text = '<a href="https://evil.com/page">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('href="https://evil.com/page"', $result->getValue());
    $this->assertStringContainsString('Link', $result->getValue());
  }

  /**
   * Test that a plain string without URLs is unchanged.
   */
  public function testPlainStringUnchanged(): void {
    $text = 'Hello, this is a plain text without any URLs.';
    $result = $this->createResultWithValue($text);
    $this->assertSame($text, $result->getValue());
  }

  /**
   * Test empty string passes through unchanged.
   */
  public function testEmptyStringUnchanged(): void {
    $result = $this->createResultWithValue('');
    $this->assertSame('', $result->getValue());
  }

  /**
   * Test that relative URLs in string are always allowed.
   */
  public function testStringRelativeUrlAllowed(): void {
    $text = '<a href="/path/to/page">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="/path/to/page"', $result->getValue());
  }

  /**
   * Test that anchor-only links in string are always allowed.
   */
  public function testStringAnchorLinkAllowed(): void {
    $text = '<a href="#section">Jump</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="#section"', $result->getValue());
  }

  /**
   * Test string with allowed protocol-relative URL.
   */
  public function testStringProtocolRelativeAllowed(): void {
    $text = '<a href="//example.com/page">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="//example.com/page"', $result->getValue());
  }

  /**
   * Test string with disallowed protocol-relative URL.
   */
  public function testStringProtocolRelativeDisallowed(): void {
    $text = '<a href="//evil.com/steal">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('evil.com', $result->getValue());
  }

  /**
   * Test string with URL containing a port number.
   */
  public function testStringUrlWithPort(): void {
    $text = '<a href="https://example.com:8080/page">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://example.com:8080/page"', $result->getValue());
  }

  /**
   * Test string with URL containing query parameters.
   */
  public function testStringUrlWithQueryParams(): void {
    $text = '<a href="https://example.com/page?foo=bar&baz=qux">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://example.com/page?foo=bar', $result->getValue());
  }

  /**
   * Test string with URL containing a hash fragment.
   */
  public function testStringUrlWithHashFragment(): void {
    $text = '<a href="https://example.com/page#section">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://example.com/page#section"', $result->getValue());
  }

  /**
   * Test string with wildcard subdomain allowed URL.
   */
  public function testStringWildcardSubdomainAllowed(): void {
    $text = '<a href="https://cdn.example.com/file">CDN Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://cdn.example.com/file"', $result->getValue());
  }

  /**
   * Test string with multi-level wildcard subdomain.
   */
  public function testStringWildcardMultiLevelSubdomain(): void {
    $text = '<a href="https://stage.cdn.example.com/file">Deep CDN</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://stage.cdn.example.com/file"', $result->getValue());
  }

  /**
   * Test case-insensitive domain matching in string.
   */
  public function testStringCaseInsensitiveDomain(): void {
    $this->hostnameFilter->setAllowedDomains(['Example.COM']);
    $text = '<a href="https://example.com/page">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://example.com/page"', $result->getValue());
  }

  /**
   * Test spoofed domain prefix is blocked.
   */
  public function testStringSpoofedDomainPrefix(): void {
    $text = '<a href="https://example.com.evil.com/page">Spoof</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('href="https://example.com.evil.com', $result->getValue());
  }

  /**
   * Test string with multiple allowed domains.
   */
  public function testStringMultipleAllowedDomains(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com', 'test.org', 'cdn.net']);
    $text = '<a href="https://test.org/page">Test</a> <a href="https://cdn.net/file">CDN</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://test.org/page"', $result->getValue());
    $this->assertStringContainsString('href="https://cdn.net/file"', $result->getValue());
  }

  /**
   * Test string with empty allowed domains blocks all external URLs.
   */
  public function testStringEmptyAllowedDomainsBlocksExternal(): void {
    $this->hostnameFilter->setAllowedDomains([]);
    $text = '<a href="https://example.com/page">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('href="https://example.com/page"', $result->getValue());
  }

  /**
   * Test string with empty allowed domains still allows relative URLs.
   */
  public function testStringEmptyAllowedDomainsAllowsRelative(): void {
    $this->hostnameFilter->setAllowedDomains([]);
    $text = '<a href="/local/path">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="/local/path"', $result->getValue());
  }

  /**
   * Test string with javascript: URI is blocked.
   */
  public function testStringJavascriptUriBlocked(): void {
    $text = '<a href="javascript:alert(1)">XSS</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('javascript:', $result->getValue());
    $this->assertStringContainsString('XSS', $result->getValue());
  }

  /**
   * Test string with data: URI is blocked.
   */
  public function testStringDataUriBlocked(): void {
    $text = '<img src="data:image/svg+xml,<svg onload=alert(1)>" alt="xss">';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('data:', $result->getValue());
  }

  /**
   * Test string with vbscript: URI is blocked.
   */
  public function testStringVbscriptUriBlocked(): void {
    $text = '<a href="vbscript:MsgBox(1)">Click</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('vbscript:', $result->getValue());
  }

  /**
   * Test string with ftp: URI is blocked.
   */
  public function testStringFtpUriBlocked(): void {
    $text = '<a href="ftp://example.com/file">Download</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('ftp://', $result->getValue());
  }

  /**
   * Test string with mailto: URI is blocked.
   */
  public function testStringMailtoUriBlocked(): void {
    $text = '<a href="mailto:test@example.com">Email</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('mailto:', $result->getValue());
  }

  /**
   * Test string with public:// scheme is allowed.
   */
  public function testStringPublicSchemeAllowed(): void {
    $text = '<img src="public://images/photo.jpg" alt="Photo">';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('public://images/photo.jpg', $result->getValue());
  }

  /**
   * Test that a string with script tag is removed.
   */
  public function testStringScriptTagRemoved(): void {
    $text = '<script src="https://example.com/script.js"></script><p>Safe</p>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<script', $result->getValue());
    $this->assertStringContainsString('Safe', $result->getValue());
  }

  /**
   * Test that inline script tag is removed.
   */
  public function testStringInlineScriptRemoved(): void {
    $text = '<script>alert("XSS");</script><p>Content</p>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<script', $result->getValue());
    $this->assertStringNotContainsString('alert', $result->getValue());
    $this->assertStringContainsString('Content', $result->getValue());
  }

  /**
   * Test that iframe tag is removed entirely.
   */
  public function testStringIframeRemoved(): void {
    $text = '<div><iframe src="https://example.com/embed"></iframe></div>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<iframe', $result->getValue());
  }

  /**
   * Test that embed tag is removed entirely.
   */
  public function testStringEmbedRemoved(): void {
    $text = '<embed src="https://example.com/plugin.swf" type="application/x-shockwave-flash">';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<embed', $result->getValue());
  }

  /**
   * Test that object tag is removed entirely.
   */
  public function testStringObjectTagRemoved(): void {
    $text = '<object data="https://example.com/object.pdf" type="application/pdf"></object>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<object', $result->getValue());
  }

  /**
   * Test that style tag is removed entirely.
   */
  public function testStringStyleTagRemoved(): void {
    $text = '<style>body { background: url("https://evil.com/track.gif"); }</style><p>Content</p>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<style>', $result->getValue());
    $this->assertStringNotContainsString('evil.com', $result->getValue());
    $this->assertStringContainsString('Content', $result->getValue());
  }

  /**
   * Test that onerror event handler is stripped.
   */
  public function testStringOnerrorStripped(): void {
    $text = '<img src="https://example.com/img.jpg" onerror="alert(1)">';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('onerror', $result->getValue());
    $this->assertStringContainsString('src="https://example.com/img.jpg"', $result->getValue());
  }

  /**
   * Test that onclick event handler is stripped.
   */
  public function testStringOnclickStripped(): void {
    $text = '<a href="https://example.com" onclick="steal()">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('onclick', $result->getValue());
    $this->assertStringContainsString('href="https://example.com"', $result->getValue());
  }

  /**
   * Test that onload event handler is stripped.
   */
  public function testStringOnloadStripped(): void {
    $text = '<body onload="alert(1)"><p>text</p></body>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('onload', $result->getValue());
    $this->assertStringContainsString('text', $result->getValue());
  }

  /**
   * Test that onmouseover event handler is stripped.
   */
  public function testStringOnmouseoverStripped(): void {
    $text = '<div onmouseover="alert(1)">Hover me</div>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('onmouseover', $result->getValue());
    $this->assertStringContainsString('Hover me', $result->getValue());
  }

  /**
   * Test that inline style with url() is removed.
   */
  public function testStringInlineStyleUrlRemoved(): void {
    $text = '<div style="background: url(https://evil.com/bg.jpg);">Content</div>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('style=', $result->getValue());
    $this->assertStringContainsString('Content', $result->getValue());
  }

  /**
   * Test string with allowed srcset.
   */
  public function testStringSrcsetAllowed(): void {
    $text = '<img srcset="https://cdn.example.com/img-1x.jpg 1x, https://cdn.example.com/img-2x.jpg 2x" alt="Image">';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('srcset=', $result->getValue());
  }

  /**
   * Test string with mixed srcset domains removes srcset.
   */
  public function testStringSrcsetMixedDomainsRemoved(): void {
    $text = '<img src="https://example.com/img.jpg" srcset="https://example.com/img-2x.jpg 2x, https://evil.com/img-3x.jpg 3x">';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('srcset', $result->getValue());
    $this->assertStringContainsString('src="https://example.com/img.jpg"', $result->getValue());
  }

  /**
   * Test string with disallowed image is removed entirely.
   */
  public function testStringDisallowedImageFiltered(): void {
    $text = '<img src="https://evil.com/image.jpg" alt="Bad">';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('https://evil.com/image.jpg', $result->getValue());
  }

  /**
   * Test string with disallowed video src is filtered.
   */
  public function testStringDisallowedVideoSrcFiltered(): void {
    $text = '<video src="https://evil.com/video.mp4" controls></video>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('src="https://evil.com/video.mp4"', $result->getValue());
  }

  /**
   * Test string with allowed video src is kept.
   */
  public function testStringAllowedVideoSrcKept(): void {
    $text = '<video src="https://cdn.example.com/video.mp4" controls></video>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('src="https://cdn.example.com/video.mp4"', $result->getValue());
  }

  /**
   * Test string with disallowed audio src is filtered.
   */
  public function testStringDisallowedAudioSrcFiltered(): void {
    $text = '<audio src="https://evil.com/audio.mp3" controls></audio>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('src="https://evil.com/audio.mp3"', $result->getValue());
  }

  /**
   * Test string with disallowed form action is filtered.
   */
  public function testStringDisallowedFormActionFiltered(): void {
    $text = '<form action="https://evil.com/exfiltrate" method="post"><input type="text"></form>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('action="https://evil.com/exfiltrate"', $result->getValue());
  }

  /**
   * Test string with disallowed button formaction is filtered.
   */
  public function testStringDisallowedButtonFormactionFiltered(): void {
    $text = '<button type="submit" formaction="https://evil.com/exfiltrate">Submit</button>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('formaction="https://evil.com/exfiltrate"', $result->getValue());
  }

  /**
   * Test string with disallowed video poster is filtered.
   */
  public function testStringDisallowedVideoPosterFiltered(): void {
    $text = '<video src="https://example.com/vid.mp4" poster="https://evil.com/poster.jpg"></video>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('poster', $result->getValue());
    $this->assertStringContainsString('src="https://example.com/vid.mp4"', $result->getValue());
  }

  /**
   * Test string with disallowed ping attribute is filtered.
   */
  public function testStringDisallowedPingFiltered(): void {
    $text = '<a href="https://example.com" ping="https://tracker.evil.com/log">Link</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('ping', $result->getValue());
    $this->assertStringContainsString('href="https://example.com"', $result->getValue());
  }

  /**
   * Test string with disallowed background attribute is filtered.
   */
  public function testStringDisallowedBackgroundFiltered(): void {
    $text = '<td background="https://evil.com/bg.jpg">Cell</td>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('background', $result->getValue());
    $this->assertStringContainsString('Cell', $result->getValue());
  }

  /**
   * Test string with disallowed input image src is filtered.
   */
  public function testStringDisallowedInputImageSrcFiltered(): void {
    $text = '<input type="image" src="https://evil.com/button.png">';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('src="https://evil.com', $result->getValue());
  }

  /**
   * Test that a string with Markdown disallowed link is filtered.
   */
  public function testStringMarkdownDisallowedLinkFiltered(): void {
    $text = '[Click here](https://evil.com/page)';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('https://evil.com', $result->getValue());
    $this->assertStringContainsString('Click here', $result->getValue());
  }

  /**
   * Test that a string with Markdown allowed link is kept.
   */
  public function testStringMarkdownAllowedLinkKept(): void {
    $text = '[Click here](https://example.com/page)';
    $result = $this->createResultWithValue($text);
    $this->assertEquals($text, $result->getValue());
  }

  /**
   * Test Markdown image with allowed domain is kept.
   */
  public function testStringMarkdownImageAllowedKept(): void {
    $text = '![Alt text](https://cdn.example.com/image.jpg)';
    $result = $this->createResultWithValue($text);
    $this->assertEquals($text, $result->getValue());
  }

  /**
   * Test Markdown image with disallowed domain is removed.
   */
  public function testStringMarkdownImageDisallowedRemoved(): void {
    $text = '![Alt text](https://evil.com/image.jpg)';
    $result = $this->createResultWithValue($text);
    $this->assertEquals('', $result->getValue());
  }

  /**
   * Test Markdown link with title attribute and allowed domain.
   */
  public function testStringMarkdownLinkWithTitleAllowed(): void {
    $text = '[Link](https://example.com/page "Title")';
    $result = $this->createResultWithValue($text);
    $this->assertEquals($text, $result->getValue());
  }

  /**
   * Test Markdown link with title and disallowed domain.
   */
  public function testStringMarkdownLinkWithTitleDisallowed(): void {
    $text = '[Link](https://evil.com/page "Title")';
    $result = $this->createResultWithValue($text);
    $this->assertEquals('Link', $result->getValue());
  }

  /**
   * Test relative Markdown link is allowed.
   */
  public function testStringMarkdownRelativeLinkAllowed(): void {
    $text = '[Link](/path/to/page)';
    $result = $this->createResultWithValue($text);
    $this->assertEquals($text, $result->getValue());
  }

  /**
   * Test relative Markdown image is allowed.
   */
  public function testStringMarkdownRelativeImageAllowed(): void {
    $text = '![Image](/images/photo.jpg)';
    $result = $this->createResultWithValue($text);
    $this->assertEquals($text, $result->getValue());
  }

  /**
   * Test javascript: URI in Markdown link is blocked.
   */
  public function testStringMarkdownJavascriptUriBlocked(): void {
    $text = '[Click](javascript:alert(1))';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('javascript:', $result->getValue());
    $this->assertStringContainsString('Click', $result->getValue());
  }

  /**
   * Test data: URI in Markdown image is blocked.
   */
  public function testStringMarkdownDataUriBlocked(): void {
    $text = '![xss](data:text/html,<script>alert(1)</script>)';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('data:', $result->getValue());
  }

  /**
   * Test mixed HTML and Markdown content.
   */
  public function testStringMixedHtmlAndMarkdown(): void {
    $text = '<a href="https://evil.com/page">HTML Link</a> and [Markdown](https://bad.com/link)';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('https://evil.com', $result->getValue());
    $this->assertStringNotContainsString('https://bad.com', $result->getValue());
    $this->assertStringContainsString('HTML Link', $result->getValue());
    $this->assertStringContainsString('Markdown', $result->getValue());
  }

  /**
   * Test broken HTML does not slip through.
   */
  public function testStringBrokenHtml(): void {
    $text = '< a href="https://evil.com/page">Click here</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $result->getValue());
  }

  /**
   * Test unclosed HTML does not slip through.
   */
  public function testStringUnclosedHtml(): void {
    $text = '<a href="https://evil.com/page">Click here';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $result->getValue());
    $this->assertStringContainsString('Click here', $result->getValue());
  }

  /**
   * Test HTML with leading whitespace in href.
   */
  public function testStringHrefWithLeadingWhitespace(): void {
    $text = '<a href="     https://evil.com/page">Click here</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $result->getValue());
  }

  /**
   * Test combined attack: javascript URI + event handler + style tag.
   */
  public function testStringCombinedAttack(): void {
    $text = '<a href="javascript:void(0)" onclick="steal()">Click</a><style>.x{background:url(https://evil.com/t)}</style><img src="https://evil.com/img.jpg" onerror="alert(1)">';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('javascript:', $result->getValue());
    $this->assertStringNotContainsString('onclick', $result->getValue());
    $this->assertStringNotContainsString('<style>', $result->getValue());
    $this->assertStringNotContainsString('onerror', $result->getValue());
    $this->assertStringNotContainsString('evil.com', $result->getValue());
  }

  /**
   * Test multiple dangerous tags in one string.
   */
  public function testStringMultipleDangerousTags(): void {
    $text = '<script src="https://evil.com/xss.js"></script><iframe src="https://evil.com/phishing"></iframe><embed src="https://evil.com/malware.swf"><object data="https://evil.com/exploit.pdf"></object><p>Safe content</p>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('<script', $result->getValue());
    $this->assertStringNotContainsString('<iframe', $result->getValue());
    $this->assertStringNotContainsString('<embed', $result->getValue());
    $this->assertStringNotContainsString('<object', $result->getValue());
    $this->assertStringNotContainsString('evil.com', $result->getValue());
    $this->assertStringContainsString('Safe content', $result->getValue());
  }

  /**
   * Test complex HTML with mixed allowed and disallowed elements.
   */
  public function testStringComplexHtmlMixedElements(): void {
    $text = <<<HTML
<div>
  <a href="https://example.com/page">Allowed Link</a>
  <a href="https://evil.com/page">Bad Link</a>
  <img src="https://cdn.example.com/image.jpg" alt="Good Image">
  <img src="https://bad.com/image.jpg" alt="Bad Image">
  <a href="/local">Local Link</a>
</div>
HTML;
    $result = $this->createResultWithValue($text);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://example.com/page"', $value);
    $this->assertStringNotContainsString('https://evil.com', $value);
    $this->assertStringContainsString('src="https://cdn.example.com/image.jpg"', $value);
    $this->assertStringNotContainsString('https://bad.com', $value);
    $this->assertStringContainsString('href="/local"', $value);
  }

  /**
   * Test that tool call YAML with disallowed HTML link is filtered.
   */
  public function testStringToolCallYamlDisallowedLinkFiltered(): void {
    $yaml = <<<YAML
    operations:
      - target: content
        placement: inside
        components:
          - sdc.mercury.text:
              props:
                text: "Click <a href=\"https://phishing.com\">here for free gift</a>."
                text_size: normal
    YAML;
    $result = $this->createResultWithValue($yaml);
    $this->assertStringNotContainsString('href="https://phishing.com"', $result->getValue());
  }

  /**
   * Test that tool call YAML with allowed link is passed through.
   */
  public function testStringToolCallYamlAllowedLinkKept(): void {
    $yaml = <<<YAML
    operations:
      - target: content
        placement: inside
        components:
          - sdc.mercury.text:
              props:
                text: "Visit our site at https://example.com for more info."
                text_size: normal
    YAML;
    $result = $this->createResultWithValue($yaml);
    $this->assertStringContainsString('https://example.com', $result->getValue());
  }

  /**
   * Test string rewrite mode replaces link text with URL.
   */
  public function testStringRewriteLinksHtml(): void {
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $text = '<a href="https://evil.com/page?data=exfiltration">Click here</a>';
    $result = $this->createResultWithValue($text);
    $value = $result->getValue();
    $this->assertStringContainsString('https://evil.com/page?data=exfiltration', $value);
    $this->assertStringNotContainsString('Click here', $value);
  }

  /**
   * Test string rewrite mode for Markdown links.
   */
  public function testStringRewriteLinksMarkdown(): void {
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $text = '[Click here](https://evil.com/page?data=exfiltration)';
    $result = $this->createResultWithValue($text);
    $expected = '[https://evil.com/page?data=exfiltration](https://evil.com/page?data=exfiltration)';
    $this->assertEquals($expected, $result->getValue());
  }

  /**
   * Test rewrite mode does not affect allowed links.
   */
  public function testStringRewriteLinksDoesNotAffectAllowed(): void {
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $text = '<a href="https://example.com/page">Click here</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://example.com/page"', $result->getValue());
    $this->assertStringContainsString('Click here', $result->getValue());
  }

  /**
   * Test removal mode (rewrite disabled) keeps text but removes href.
   */
  public function testStringRemovalModeKeepsText(): void {
    $this->hostnameFilter->setRewriteLinks(FALSE);
    $text = '<a href="https://evil.com/page">Click here</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringNotContainsString('href="https://evil.com/page"', $result->getValue());
    $this->assertStringContainsString('Click here', $result->getValue());
  }

  /**
   * Test full trust mode bypasses all filtering on strings.
   */
  public function testStringFullTrustBypasses(): void {
    $this->hostnameFilter->setFullTrust(TRUE);
    $text = '<a href="https://evil.com/page">Click</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://evil.com/page"', $result->getValue());
    $this->assertStringContainsString('Click', $result->getValue());
  }

  /**
   * Test full trust mode via DTO bypasses filtering.
   */
  public function testStringFullTrustViaDto(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      fullTrust: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);
    $text = '<a href="https://evil.com/page">Click</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('href="https://evil.com/page"', $result->getValue());
  }

  /**
   * Test plain text mode filters disallowed URLs from string.
   */
  public function testStringPlainTextModeFiltersDisallowed(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit https://example.com and https://evil.com for more info';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('https://example.com', $result->getValue());
    $this->assertStringNotContainsString('https://evil.com', $result->getValue());
    $this->assertStringContainsString('Visit', $result->getValue());
  }

  /**
   * Test plain text mode keeps allowed domain links.
   */
  public function testStringPlainTextModeKeepsAllowed(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Check out https://example.com/page for details';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('https://example.com/page', $result->getValue());
  }

  /**
   * Test plain text mode allows www URLs (bare domains are not filtered).
   */
  public function testStringPlainTextModeAllowsWwwUrls(): void {
    $this->hostnameFilter->setAllowedDomains([]);
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit www.example.com for more';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('www.example.com', $result->getValue());
    $this->assertStringContainsString('Visit', $result->getValue());
  }

  /**
   * Test plain text mode allows bare domain URLs (not filtered).
   */
  public function testStringPlainTextModeAllowsBareDomain(): void {
    $this->hostnameFilter->setAllowedDomains([]);
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit example.com/path for more';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('example.com/path', $result->getValue());
  }

  /**
   * Test plain text mode does not override full trust.
   */
  public function testStringPlainTextModeDoesNotOverrideFullTrust(): void {
    $this->hostnameFilter->setFullTrust(TRUE);
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $text = 'Visit https://example.com and https://evil.com';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('https://example.com', $result->getValue());
    $this->assertStringContainsString('https://evil.com', $result->getValue());
  }

  /**
   * Test applying settings from DTO with allowed domains and rewrite.
   */
  public function testStringDtoWithDomainsAndRewrite(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com', '*.cdn.example.com'],
      rewriteLinks: TRUE,
      fullTrust: FALSE,
    );
    $this->hostnameFilter->applySettings($dto);
    $text = '<a href="https://evil.com/page">Click</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('https://evil.com/page', $result->getValue());
    $this->assertStringNotContainsString('Click', $result->getValue());
  }

  /**
   * Test partial DTO only changes specified settings.
   */
  public function testStringDtoPartialSettings(): void {
    $this->hostnameFilter->setAllowedDomains(['initial.com']);
    $this->hostnameFilter->setRewriteLinks(FALSE);

    $dto = new HostnameFilterDto(
      rewriteLinks: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);

    // Allowed domains should remain unchanged.
    $this->assertEquals(['initial.com'], $this->hostnameFilter->getAllowedDomains());

    // But rewrite mode should be enabled.
    $text = '<a href="https://evil.com/page">Click</a>';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('https://evil.com/page', $result->getValue());
  }

  /**
   * Test DTO with plain text mode.
   */
  public function testStringDtoWithPlainTextMode(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      plainTextMode: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);

    $text = 'Visit https://example.com for details';
    $result = $this->createResultWithValue($text);
    $this->assertStringContainsString('https://example.com', $result->getValue());
  }

  /**
   * Test array with string values that have disallowed links are filtered.
   */
  public function testArrayStringValuesFiltered(): void {
    $array = [
      'allowed' => '<a href="https://example.com/page">Good</a>',
      'disallowed' => '<a href="https://evil.com/page">Bad</a>',
      'plain' => 'No links here',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertIsArray($value);
    $this->assertStringContainsString('href="https://example.com/page"', $value['allowed']);
    $this->assertStringNotContainsString('href="https://evil.com/page"', $value['disallowed']);
    $this->assertStringContainsString('Bad', $value['disallowed']);
    $this->assertSame('No links here', $value['plain']);
  }

  /**
   * Test array with non-string values are not altered.
   */
  public function testArrayNonStringValuesUnchanged(): void {
    $array = [
      'int' => 42,
      'bool' => TRUE,
      'float' => 3.14,
      'null' => NULL,
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertSame(42, $value['int']);
    $this->assertTrue($value['bool']);
    $this->assertSame(3.14, $value['float']);
    $this->assertNull($value['null']);
  }

  /**
   * Test array with mixed string and non-string values.
   */
  public function testArrayMixedValues(): void {
    $array = [
      'text' => '<a href="https://evil.com">Evil</a>',
      'number' => 123,
      'flag' => FALSE,
      'safe_text' => '<a href="https://example.com">Safe</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('href="https://evil.com"', $value['text']);
    $this->assertSame(123, $value['number']);
    $this->assertFalse($value['flag']);
    $this->assertStringContainsString('href="https://example.com"', $value['safe_text']);
  }

  /**
   * Test empty array passes through unchanged.
   */
  public function testEmptyArrayUnchanged(): void {
    $result = $this->createResultWithValue([]);
    $this->assertSame([], $result->getValue());
  }

  /**
   * Test that numeric-keyed arrays with strings are filtered.
   */
  public function testNumericKeyedArrayStringsFiltered(): void {
    $array = [
      '<a href="https://example.com">Allowed</a>',
      '<a href="https://evil.com">Blocked</a>',
      'Plain text',
      42,
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://example.com"', $value[0]);
    $this->assertStringNotContainsString('href="https://evil.com"', $value[1]);
    $this->assertSame('Plain text', $value[2]);
    $this->assertSame(42, $value[3]);
  }

  /**
   * Test that nested arrays only filter top-level string values.
   *
   * The current implementation only iterates top-level array values.
   * Nested strings inside sub-arrays are NOT filtered.
   */
  public function testNestedArrayOnlyFiltersTopLevel(): void {
    $array = [
      'top_level_bad' => '<a href="https://evil.com/top">Top</a>',
      'nested' => [
        'level2' => '<a href="https://evil.com/nested">Nested</a>',
        'deeper' => [
          'level3' => '<a href="https://evil.com/deep">Deep</a>',
        ],
      ],
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    // Top-level string IS filtered.
    $this->assertStringNotContainsString('href="https://evil.com/top"', $value['top_level_bad']);
    // Nested strings are NOT filtered (current behavior).
    $this->assertStringContainsString('href="https://evil.com/nested"', $value['nested']['level2']);
    $this->assertStringContainsString('href="https://evil.com/deep"', $value['nested']['deeper']['level3']);
  }

  /**
   * Test array with Markdown links in string values.
   */
  public function testArrayMarkdownLinksInStrings(): void {
    $array = [
      'good_link' => '[Click](https://example.com/page)',
      'bad_link' => '[Click](https://evil.com/page)',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertEquals('[Click](https://example.com/page)', $value['good_link']);
    $this->assertStringNotContainsString('https://evil.com', $value['bad_link']);
    $this->assertStringContainsString('Click', $value['bad_link']);
  }

  /**
   * Test array with Markdown images in string values.
   */
  public function testArrayMarkdownImagesInStrings(): void {
    $array = [
      'good_img' => '![Alt](https://cdn.example.com/image.jpg)',
      'bad_img' => '![Alt](https://evil.com/image.jpg)',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertEquals('![Alt](https://cdn.example.com/image.jpg)', $value['good_img']);
    $this->assertEquals('', $value['bad_img']);
  }

  /**
   * Test complex array with objects as values are unchanged.
   */
  public function testArrayWithObjectValuesUnchanged(): void {
    $obj = new \stdClass();
    $obj->url = 'https://evil.com/should-not-be-filtered';
    $array = [
      'object_val' => $obj,
      'string_val' => '<a href="https://evil.com">Bad</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    // Object inside array is not a string, so it's not filtered.
    $this->assertSame('https://evil.com/should-not-be-filtered', $value['object_val']->url);
    // String inside array IS filtered.
    $this->assertStringNotContainsString('href="https://evil.com"', $value['string_val']);
  }

  /**
   * Test array with javascript: URI in string values.
   */
  public function testArrayJavascriptUriBlocked(): void {
    $array = [
      'xss' => '<a href="javascript:alert(1)">XSS</a>',
      'safe' => '<a href="https://example.com">Safe</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('javascript:', $value['xss']);
    $this->assertStringContainsString('href="https://example.com"', $value['safe']);
  }

  /**
   * Test array with data: URI in string values.
   */
  public function testArrayDataUriBlocked(): void {
    $array = [
      'xss' => '<img src="data:image/svg+xml,<svg onload=alert(1)>" alt="xss">',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('data:', $value['xss']);
  }

  /**
   * Test array with public:// scheme in string values.
   */
  public function testArrayPublicSchemeAllowed(): void {
    $array = [
      'image' => '<img src="public://images/photo.jpg" alt="Photo">',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('public://images/photo.jpg', $value['image']);
  }

  /**
   * Test array with script tags in string values are removed.
   */
  public function testArrayScriptTagsRemoved(): void {
    $array = [
      'content' => '<script>alert("XSS");</script><p>Safe</p>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('<script', $value['content']);
    $this->assertStringContainsString('Safe', $value['content']);
  }

  /**
   * Test array with iframe tags in string values are removed.
   */
  public function testArrayIframeTagsRemoved(): void {
    $array = [
      'content' => '<div><iframe src="https://evil.com/embed"></iframe>Text</div>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('<iframe', $value['content']);
    $this->assertStringContainsString('Text', $value['content']);
  }

  /**
   * Test array with event handlers in string values are stripped.
   */
  public function testArrayEventHandlersStripped(): void {
    $array = [
      'onerror' => '<img src="https://example.com/img.jpg" onerror="alert(1)">',
      'onclick' => '<a href="https://example.com" onclick="steal()">Link</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('onerror', $value['onerror']);
    $this->assertStringContainsString('src="https://example.com/img.jpg"', $value['onerror']);
    $this->assertStringNotContainsString('onclick', $value['onclick']);
    $this->assertStringContainsString('href="https://example.com"', $value['onclick']);
  }

  /**
   * Test array with inline style url() in string values is removed.
   */
  public function testArrayInlineStyleUrlRemoved(): void {
    $array = [
      'content' => '<div style="background: url(https://evil.com/bg.jpg);">Content</div>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('style=', $value['content']);
    $this->assertStringContainsString('Content', $value['content']);
  }

  /**
   * Test array with mixed srcset domains in string values.
   */
  public function testArraySrcsetMixedDomainsRemoved(): void {
    $array = [
      'image' => '<img src="https://example.com/img.jpg" srcset="https://example.com/img-2x.jpg 2x, https://evil.com/img-3x.jpg 3x">',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('srcset', $value['image']);
    $this->assertStringContainsString('src="https://example.com/img.jpg"', $value['image']);
  }

  /**
   * Test array with all-allowed srcset in string values.
   */
  public function testArraySrcsetAllAllowed(): void {
    $array = [
      'image' => '<img srcset="https://example.com/img-1x.jpg 1x, https://cdn.example.com/img-2x.jpg 2x" alt="Image">',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('srcset=', $value['image']);
  }

  /**
   * Test array with protocol-relative URLs in string values.
   */
  public function testArrayProtocolRelativeUrls(): void {
    $array = [
      'allowed' => '<a href="//example.com/page">Link</a>',
      'disallowed' => '<img src="//evil.com/track.gif">',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="//example.com/page"', $value['allowed']);
    $this->assertStringNotContainsString('evil.com', $value['disallowed']);
  }

  /**
   * Test array with relative and anchor URLs always allowed.
   */
  public function testArrayRelativeAndAnchorUrlsAllowed(): void {
    $array = [
      'relative' => '<a href="/path/to/page">Link</a>',
      'anchor' => '<a href="#section">Jump</a>',
      'image' => '<img src="/images/photo.jpg" alt="Photo">',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="/path/to/page"', $value['relative']);
    $this->assertStringContainsString('href="#section"', $value['anchor']);
    $this->assertStringContainsString('src="/images/photo.jpg"', $value['image']);
  }

  /**
   * Test array with wildcard subdomain in string values.
   */
  public function testArrayWildcardSubdomainAllowed(): void {
    $array = [
      'cdn' => '<a href="https://cdn.example.com/file">CDN</a>',
      'deep' => '<a href="https://stage.cdn.example.com/file">Deep CDN</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://cdn.example.com/file"', $value['cdn']);
    $this->assertStringContainsString('href="https://stage.cdn.example.com/file"', $value['deep']);
  }

  /**
   * Test array with case-insensitive domain matching.
   */
  public function testArrayCaseInsensitiveDomain(): void {
    $this->hostnameFilter->setAllowedDomains(['Example.COM']);
    $array = [
      'link' => '<a href="https://example.com/page">Link</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://example.com/page"', $value['link']);
  }

  /**
   * Test array with spoofed domain prefix in string values.
   */
  public function testArraySpoofedDomainPrefix(): void {
    $array = [
      'spoof' => '<a href="https://example.com.evil.com/page">Spoof</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('href="https://example.com.evil.com', $value['spoof']);
  }

  /**
   * Test array with empty allowed domains blocks all external.
   */
  public function testArrayEmptyAllowedDomainsBlocksExternal(): void {
    $this->hostnameFilter->setAllowedDomains([]);
    $array = [
      'link' => '<a href="https://example.com/page">Link</a>',
      'relative' => '<a href="/local">Local</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('href="https://example.com/page"', $value['link']);
    $this->assertStringContainsString('href="/local"', $value['relative']);
  }

  /**
   * Test array with multiple allowed domains.
   */
  public function testArrayMultipleAllowedDomains(): void {
    $this->hostnameFilter->setAllowedDomains(['example.com', 'test.org', 'cdn.net']);
    $array = [
      'example' => '<a href="https://example.com/page">Example</a>',
      'test' => '<a href="https://test.org/page">Test</a>',
      'cdn' => '<a href="https://cdn.net/file">CDN</a>',
      'evil' => '<a href="https://evil.com/page">Evil</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://example.com/page"', $value['example']);
    $this->assertStringContainsString('href="https://test.org/page"', $value['test']);
    $this->assertStringContainsString('href="https://cdn.net/file"', $value['cdn']);
    $this->assertStringNotContainsString('href="https://evil.com/page"', $value['evil']);
  }

  /**
   * Test array with rewrite links mode for HTML.
   */
  public function testArrayRewriteLinksHtml(): void {
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $array = [
      'link' => '<a href="https://evil.com/page?data=exfiltration">Click here</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('https://evil.com/page?data=exfiltration', $value['link']);
    $this->assertStringNotContainsString('Click here', $value['link']);
  }

  /**
   * Test array with rewrite links mode for Markdown.
   */
  public function testArrayRewriteLinksMarkdown(): void {
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $array = [
      'link' => '[Click here](https://evil.com/page)',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $expected = '[https://evil.com/page](https://evil.com/page)';
    $this->assertEquals($expected, $value['link']);
  }

  /**
   * Test array rewrite mode does not affect allowed links.
   */
  public function testArrayRewriteDoesNotAffectAllowed(): void {
    $this->hostnameFilter->setRewriteLinks(TRUE);
    $array = [
      'link' => '<a href="https://example.com/page">Click here</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://example.com/page"', $value['link']);
    $this->assertStringContainsString('Click here', $value['link']);
  }

  /**
   * Test array removal mode (rewrite disabled) keeps text.
   */
  public function testArrayRemovalModeKeepsText(): void {
    $this->hostnameFilter->setRewriteLinks(FALSE);
    $array = [
      'link' => '<a href="https://evil.com/page">Click here</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('href="https://evil.com/page"', $value['link']);
    $this->assertStringContainsString('Click here', $value['link']);
  }

  /**
   * Test array with full trust mode bypasses all filtering.
   */
  public function testArrayFullTrustBypasses(): void {
    $this->hostnameFilter->setFullTrust(TRUE);
    $array = [
      'evil' => '<a href="https://evil.com/page">Click</a>',
      'script' => '<script>alert(1);</script><p>Safe</p>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://evil.com/page"', $value['evil']);
    // Full trust returns text as-is, no filtering at all.
    $this->assertEquals('<script>alert(1);</script><p>Safe</p>', $value['script']);
  }

  /**
   * Test array with full trust mode via DTO.
   */
  public function testArrayFullTrustViaDto(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      fullTrust: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);
    $array = [
      'evil' => '<a href="https://evil.com/page">Click</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://evil.com/page"', $value['evil']);
  }

  /**
   * Test array with plain text mode filters disallowed URLs.
   */
  public function testArrayPlainTextModeFiltersDisallowed(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $array = [
      'text' => 'Visit https://example.com and https://evil.com for more info',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('https://example.com', $value['text']);
    $this->assertStringNotContainsString('https://evil.com', $value['text']);
  }

  /**
   * Test array with plain text mode keeps allowed.
   */
  public function testArrayPlainTextModeKeepsAllowed(): void {
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $array = [
      'text' => 'Check out https://example.com/page for details',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('https://example.com/page', $value['text']);
  }

  /**
   * Test array plain text mode does not override full trust.
   */
  public function testArrayPlainTextModeDoesNotOverrideFullTrust(): void {
    $this->hostnameFilter->setFullTrust(TRUE);
    $this->hostnameFilter->setPlainTextMode(TRUE);
    $array = [
      'text' => 'Visit https://evil.com for info',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('https://evil.com', $value['text']);
  }

  /**
   * Test array with DTO domains and rewrite.
   */
  public function testArrayDtoWithDomainsAndRewrite(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      rewriteLinks: TRUE,
      fullTrust: FALSE,
    );
    $this->hostnameFilter->applySettings($dto);
    $array = [
      'link' => '<a href="https://evil.com/page">Click</a>',
      'safe' => '<a href="https://example.com/page">Safe</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('https://evil.com/page', $value['link']);
    $this->assertStringNotContainsString('Click', $value['link']);
    $this->assertStringContainsString('href="https://example.com/page"', $value['safe']);
  }

  /**
   * Test array with DTO plain text mode.
   */
  public function testArrayDtoWithPlainTextMode(): void {
    $dto = new HostnameFilterDto(
      allowedDomainNames: ['example.com'],
      plainTextMode: TRUE,
    );
    $this->hostnameFilter->applySettings($dto);
    $array = [
      'text' => 'Visit https://example.com for details',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('https://example.com', $value['text']);
  }

  /**
   * Test array with combined attacks in string values.
   */
  public function testArrayCombinedAttack(): void {
    $array = [
      'xss' => '<a href="javascript:void(0)" onclick="steal()">Click</a>',
      'tracking' => '<style>.x{background:url(https://evil.com/t)}</style><p>Content</p>',
      'injection' => '<img src="https://evil.com/img.jpg" onerror="alert(1)">',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('javascript:', $value['xss']);
    $this->assertStringNotContainsString('onclick', $value['xss']);
    $this->assertStringNotContainsString('<style>', $value['tracking']);
    $this->assertStringNotContainsString('evil.com', $value['tracking']);
    $this->assertStringContainsString('Content', $value['tracking']);
    $this->assertStringNotContainsString('onerror', $value['injection']);
    $this->assertStringNotContainsString('evil.com', $value['injection']);
  }

  /**
   * Test array with broken HTML in string values.
   */
  public function testArrayBrokenHtml(): void {
    $array = [
      'broken' => '< a href="https://evil.com/page">Click here</a>',
      'unclosed' => '<a href="https://evil.com/page">Click here',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $value['broken']);
    $this->assertStringNotContainsString('<a href="https://evil.com/page"', $value['unclosed']);
    $this->assertStringContainsString('Click here', $value['unclosed']);
  }

  /**
   * Test array with complex HTML containing mixed elements.
   */
  public function testArrayComplexHtmlMixed(): void {
    $array = [
      'content' => '<div><a href="https://example.com/page">Good</a> <a href="https://evil.com/page">Bad</a> <img src="https://cdn.example.com/img.jpg" alt="CDN"> <a href="/local">Local</a></div>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://example.com/page"', $value['content']);
    $this->assertStringNotContainsString('https://evil.com', $value['content']);
    $this->assertStringContainsString('src="https://cdn.example.com/img.jpg"', $value['content']);
    $this->assertStringContainsString('href="/local"', $value['content']);
  }

  /**
   * Test array with URLs containing port, query, and fragment.
   */
  public function testArrayUrlVariants(): void {
    $array = [
      'port' => '<a href="https://example.com:8080/page">Port</a>',
      'query' => '<a href="https://example.com/page?foo=bar">Query</a>',
      'fragment' => '<a href="https://example.com/page#section">Fragment</a>',
    ];
    $result = $this->createResultWithValue($array);
    $value = $result->getValue();
    $this->assertStringContainsString('href="https://example.com:8080/page"', $value['port']);
    $this->assertStringContainsString('href="https://example.com/page?foo=bar"', $value['query']);
    $this->assertStringContainsString('href="https://example.com/page#section"', $value['fragment']);
  }

  /**
   * Test setValue can be called multiple times.
   */
  public function testSetValueMultipleTimes(): void {
    $result = new ToolsPropertyResult();
    $result->setName('test');

    $result->setValue(42);
    $this->assertSame(42, $result->getValue());

    $result->setValue('<a href="https://evil.com">Bad</a>');
    $this->assertStringNotContainsString('href="https://evil.com"', $result->getValue());

    $result->setValue(TRUE);
    $this->assertTrue($result->getValue());
  }

}

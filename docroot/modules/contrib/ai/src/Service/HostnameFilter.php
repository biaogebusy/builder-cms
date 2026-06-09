<?php

namespace Drupal\ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\ai\Dto\HostnameFilterDto;
use Masterminds\HTML5;

/**
 * Service to filter hostnames and find them in images and links.
 */
class HostnameFilter {

  /**
   * The allowed domain names.
   *
   * @var array|null
   */
  protected ?array $allowedDomains = NULL;

  /**
   * Whether to rewrite disallowed links (NULL means use settings).
   *
   * @var bool|null
   */
  protected ?bool $rewriteLinks = NULL;

  /**
   * Whether full trust mode is enabled (NULL means use settings).
   *
   * @var bool|null
   */
  protected ?bool $fullTrust = NULL;

  /**
   * Whether to use plain text mode for URL filtering.
   *
   * When enabled, treats all input as plain text and filters URLs
   * based on white list, regardless of HTML/Markdown markup.
   *
   * @var bool|null
   */
  protected ?bool $plainTextMode = NULL;

  /**
   * HTML5 parser instance.
   *
   * @var \Masterminds\HTML5
   */
  private HTML5 $html5;

  /**
   * URL-bearing attributes by tag.
   *
   * @var array<string, string[]>
   */
  protected array $urlAttributes = [
    'a' => ['href', 'ping'],
    'area' => ['href', 'ping'],
    'img' => ['src', 'srcset', 'data-src', 'data-srcset'],
    'source' => ['src', 'srcset', 'data-src', 'data-srcset'],
    'video' => ['src', 'data-src', 'poster'],
    'audio' => ['src', 'data-src'],
    'track' => ['src', 'data-src'],
    'iframe' => ['src', 'data-src'],
    'embed' => ['src', 'data-src'],
    'object' => ['data'],
    'link' => ['href'],
    'script' => ['src', 'data-src'],
    'form' => ['action'],
    'button' => ['formaction'],
    'input' => ['src', 'formaction'],
    'use' => ['href', 'xlink:href'],
    'base' => ['href'],
    'meta' => ['content'],
  ];

  /**
   * Attributes that contain multiple space/comma-separated URLs.
   *
   * @var string[]
   */
  protected array $multiUrlAttributes = ['srcset', 'data-srcset', 'ping'];

  /**
   * Constructs a HostnameFilter object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->html5 = new HTML5([
      'disable_html_ns' => TRUE,
    ]);
  }

  /**
   * Filter text by removing links/images with disallowed hostnames.
   *
   * @param string $text
   *   The text to filter (can contain HTML and/or Markdown).
   *
   * @return string
   *   The filtered text.
   */
  public function filterText(string $text): string {
    // If full trust is enabled, return text as-is.
    if ($this->isFullTrustEnabled()) {
      return $text;
    }

    // If plain text mode is enabled, filter as plain text.
    if ($this->plainTextMode) {
      return $this->filterUrlsInPlainText($text);
    }

    // Filter Markdown syntax first.
    $text = $this->filterMarkdownLinks($text);

    // Filter HTML tags.
    $dom = $this->html5->loadHTMLFragment($text);
    $this->filterHtmlLinks($dom);
    $potentially_sanitized = $this->html5->saveHTML($dom);
    if ($potentially_sanitized !== $text) {
      // Only update if changes were made and HTML existed.
      $text = $potentially_sanitized;
    }

    // Return the filtered text.
    return $text;
  }

  /**
   * Set allowed domain names.
   *
   * @param array $domains
   *   Array of allowed domain names (can include wildcards like *.example.com).
   */
  public function setAllowedDomains(array $domains): void {
    $this->allowedDomains = $domains;
  }

  /**
   * Set whether to rewrite disallowed links.
   *
   * @param bool $rewrite
   *   TRUE to rewrite disallowed links to show the URL, FALSE to remove them.
   */
  public function setRewriteLinks(bool $rewrite): void {
    $this->rewriteLinks = $rewrite;
  }

  /**
   * Set whether full trust mode is enabled.
   *
   * @param bool $full_trust
   *   TRUE to bypass all filtering, FALSE to apply filtering.
   */
  public function setFullTrust(bool $full_trust): void {
    $this->fullTrust = $full_trust;
  }

  /**
   * Set whether to use plain text mode for URL filtering.
   *
   * @param bool $plain_text_mode
   *   TRUE to filter URLs as plain text (ignores markup),
   *   FALSE to apply format-aware filtering (HTML/Markdown).
   */
  public function setPlainTextMode(bool $plain_text_mode): void {
    $this->plainTextMode = $plain_text_mode;
  }

  /**
   * Apply settings from a HostnameFilterDto.
   *
   * This allows programmatic override of settings.php configuration.
   *
   * @param \Drupal\ai\Dto\HostnameFilterDto $dto
   *   The DTO containing filter settings.
   */
  public function applySettings(HostnameFilterDto $dto): void {
    if ($dto->allowedDomainNames !== NULL) {
      $this->setAllowedDomains($dto->allowedDomainNames);
    }
    if ($dto->rewriteLinks !== NULL) {
      $this->setRewriteLinks($dto->rewriteLinks);
    }
    if ($dto->fullTrust !== NULL) {
      $this->setFullTrust($dto->fullTrust);
    }
    if ($dto->plainTextMode !== NULL) {
      $this->setPlainTextMode($dto->plainTextMode);
    }
  }

  /**
   * Capture current programmatic overrides so they can later be restored.
   *
   * The HostnameFilter service is a singleton; callers that temporarily
   * override its state (e.g. to scope a HostnameFilterDto to a single
   * provider call) should snapshot before applying and restore afterwards
   * to avoid leaking settings into unrelated calls.
   *
   * @return array
   *   Opaque snapshot to pass to ::restoreSettings().
   */
  public function snapshotSettings(): array {
    return [
      'allowedDomains' => $this->allowedDomains,
      'rewriteLinks' => $this->rewriteLinks,
      'fullTrust' => $this->fullTrust,
      'plainTextMode' => $this->plainTextMode,
    ];
  }

  /**
   * Restore a snapshot taken with ::snapshotSettings().
   *
   * @param array $snapshot
   *   The snapshot returned by ::snapshotSettings().
   */
  public function restoreSettings(array $snapshot): void {
    $this->allowedDomains = $snapshot['allowedDomains'] ?? NULL;
    $this->rewriteLinks = $snapshot['rewriteLinks'] ?? NULL;
    $this->fullTrust = $snapshot['fullTrust'] ?? NULL;
    $this->plainTextMode = $snapshot['plainTextMode'] ?? NULL;
  }

  /**
   * Get allowed domain names.
   *
   * @return array
   *   Array of allowed domain names.
   */
  public function getAllowedDomains(): array {
    // Return cached value if available.
    if ($this->allowedDomains !== NULL) {
      return $this->allowedDomains;
    }

    // Fetch from config.
    $config = $this->configFactory->get('ai.settings');
    $allowed_domains = $config->get('allowed_hosts');

    if (is_string($allowed_domains)) {
      $allowed_domains = [$allowed_domains];
    }

    if (!is_array($allowed_domains)) {
      $this->allowedDomains = [];
      return $this->allowedDomains;
    }

    $this->allowedDomains = array_values(array_filter(
      array_map(
        static fn($host): string => trim((string) $host),
        $allowed_domains
      ),
      static fn(string $host): bool => $host !== ''
    ));

    return $this->allowedDomains;
  }

  /**
   * Check if full trust mode is enabled.
   *
   * @return bool
   *   TRUE if all domains are allowed, FALSE otherwise.
   */
  protected function isFullTrustEnabled(): bool {
    // Return cached value if available.
    if ($this->fullTrust !== NULL) {
      return $this->fullTrust;
    }

    $settings = Settings::get('ai_output', []);
    return !empty($settings['full_trust_mode']);
  }

  /**
   * Check if link rewriting mode is enabled.
   *
   * @return bool
   *   TRUE if disallowed links should be rewritten to show the URL,
   *   FALSE to remove them.
   */
  protected function shouldRewriteLinks(): bool {
    // Return cached value if available.
    if ($this->rewriteLinks !== NULL) {
      return $this->rewriteLinks;
    }

    // Fetch from config.
    $config = $this->configFactory->get('ai.settings');
    return !empty($config->get('allowed_hosts_rewrite_links'));
  }

  /**
   * Filter URLs in plain text mode.
   *
   * This treats all input as plain text and removes URLs that don't
   * match the white list, regardless of HTML or Markdown markup.
   *
   * @param string $input
   *   The text to filter.
   *
   * @return string
   *   The filtered text with disallowed URLs removed.
   */
  protected function filterUrlsInPlainText(string $input): string {
    // Regex to match URLs like:
    // https://example.com/path
    // http://example.com
    // www.example.com
    // example.com/path
    // Stop at whitespace, quotes, or angle brackets to avoid breaking HTML.
    $urlPattern = '/(https?:\/\/[^\s"\'<>]+|\/\/[^\s"\'<>]+|www\.[^\s"\'<>]+|(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}(?:\/[^\s"\'<>]*)?)/';
    // Find all URLs and check if they match the allowed domains.
    $output = preg_replace_callback($urlPattern, function ($matches) {
      $url = $matches[0];
      // Remove trailing punctuation that's not part of the URL.
      $url = rtrim($url, '.,!?;:\'")');
      if (!$this->isUrlAllowed($url)) {
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed URL: @url',
          ['@url' => $url]
        );
        return '';
      }
      return $url;
    }, $input);
    return $output;
  }

  /**
   * Filter HTML links and images.
   *
   * @param \DOMNode $node
   *   The DOM node to process.
   */
  protected function filterHtmlLinks(\DOMNode $node): void {
    if ($node instanceof \DOMElement) {
      $tag = strtolower($node->tagName);

      // Remove dangerous auto-loading elements entirely - if you are doing a
      // code assistant, you will have to opt out of filtering specifically.
      if (in_array($tag, ['script', 'iframe', 'embed', 'object', 'style'], TRUE)) {
        $node->parentNode?->removeChild($node);
        return;
      }

      // Strip event handler attributes (on* attributes).
      $attrsToRemove = [];
      foreach ($node->attributes as $attribute) {
        if (str_starts_with(strtolower($attribute->name), 'on')) {
          $attrsToRemove[] = $attribute->name;
        }
      }
      foreach ($attrsToRemove as $attrName) {
        $node->removeAttribute($attrName);
      }

      if (isset($this->urlAttributes[$tag])) {
        foreach ($this->urlAttributes[$tag] as $attr) {
          if (!$node->hasAttribute($attr)) {
            continue;
          }

          $url = $node->getAttribute($attr);

          // Handle multi-URL attributes (srcset, ping) separately.
          if (in_array($attr, $this->multiUrlAttributes, TRUE)) {
            if (!$this->isMultiUrlAttributeAllowed($url)) {
              $this->loggerFactory->get('ai')->warning(
                'Removed disallowed AI output multi-URL attribute @attr: @url',
                ['@attr' => $attr, '@url' => $url]
              );
              $node->removeAttribute($attr);
            }
            continue;
          }

          if (!$this->isUrlAllowed($url)) {
            if ($this->shouldRewriteLinks() && $tag === 'a') {
              $this->loggerFactory->get('ai')->warning(
                'Rewriting disallowed AI output URL: @url',
                ['@url' => $url]
              );
              $node->nodeValue = $url;
            }
            else {
              $this->loggerFactory->get('ai')->warning(
                'Removed disallowed AI output URL: @url',
                ['@url' => $url]
              );
              $node->removeAttribute($attr);
            }
          }
        }
      }

      // Check background attribute on any element.
      if ($node->hasAttribute('background')) {
        $bgUrl = $node->getAttribute('background');
        if (!$this->isUrlAllowed($bgUrl)) {
          $node->removeAttribute('background');
        }
      }

      // Strip inline CSS url().
      if ($node->hasAttribute('style') && stripos($node->getAttribute('style'), 'url(') !== FALSE) {
        $node->removeAttribute('style');
      }
    }

    // Avoid live NodeList mutation issues.
    $children = [];
    foreach ($node->childNodes as $child) {
      $children[] = $child;
    }

    foreach ($children as $child) {
      $this->filterHtmlLinks($child);
    }
  }

  /**
   * Process DOM elements and filter by hostname.
   *
   * @param \DOMNodeList $elements
   *   The elements to process.
   * @param string $attribute
   *   The attribute containing the URL (href or src).
   * @param \DOMDocument $doc
   *   The DOM document.
   */
  protected function processElements(\DOMNodeList $elements, string $attribute, \DOMDocument $doc): void {
    // Convert to array to avoid issues when removing nodes.
    $elementsArray = [];
    foreach ($elements as $element) {
      $elementsArray[] = $element;
    }

    foreach ($elementsArray as $element) {
      /** @var \DOMElement $element */
      $url = $element->getAttribute($attribute);

      if (empty($url)) {
        continue;
      }

      // Check if URL is allowed.
      if (!$this->isUrlAllowed($url)) {
        // Log the removal.
        $this->loggerFactory->get('ai')->warning(
          'AI Module removed the unsecure @type with URL from the output: @url',
          [
            '@type' => $element->nodeName,
            '@url' => $url,
          ]
        );

        // Remove the element or just the attribute.
        if ($element->nodeName === 'img') {
          // Remove entire img tag.
          if ($element->parentNode) {
            $element->parentNode->removeChild($element);
          }
        }
        else {
          // For links, either rewrite or remove based on settings.
          if ($this->shouldRewriteLinks()) {
            // Replace link text with the URL itself.
            $element->nodeValue = $url;
          }
          else {
            // Keep the text but remove the href.
            $element->removeAttribute($attribute);
          }
        }
      }
    }
  }

  /**
   * Filter Markdown links and images.
   *
   * @param string $markdown
   *   The Markdown content.
   *
   * @return string
   *   The filtered Markdown.
   */
  protected function filterMarkdownLinks(string $markdown): string {
    // Pattern for Markdown images: ![alt](url) or ![alt](url "title").
    // Process images FIRST before links, since the link pattern would also
    // match the [alt](url) part.
    $imagePattern = '/!\[([^\]]*)\]\(([^\s\)]+)(?:\s+"[^"]*")?\)/';

    $markdown = preg_replace_callback($imagePattern, function ($matches) {
      $url = $matches[2];

      if (!$this->isUrlAllowed($url)) {
        // Log the removal.
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed Markdown image with URL: @url',
          ['@url' => $url]
        );

        // Remove the entire image.
        return '';
      }

      // Return the original match.
      return $matches[0];
    }, $markdown);

    // Pattern for Markdown links: [text](url) or [text](url "title").
    $linkPattern = '/\[([^\]]+)\]\(([^\s\)]+)(?:\s+"[^"]*")?\)/';

    $markdown = preg_replace_callback($linkPattern, function ($matches) {
      $text = $matches[1];
      $url = $matches[2];

      if (!$this->isUrlAllowed($url)) {
        // Log the removal.
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed Markdown link with URL: @url',
          ['@url' => $url]
        );

        // Either rewrite the link or return just the text based on settings.
        if ($this->shouldRewriteLinks()) {
          // Rewrite to show the URL as both text and link.
          return '[' . $url . '](' . $url . ')';
        }
        else {
          // Return just the text without the link.
          return $text;
        }
      }

      // Return the original match.
      return $matches[0];
    }, $markdown);

    return $markdown;
  }

  /**
   * Check if a URL is allowed.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if allowed, FALSE otherwise.
   */
  protected function isUrlAllowed(string $url): bool {
    $trimmedUrl = trim($url);

    // Deny empty URLs.
    if ($trimmedUrl === '') {
      return FALSE;
    }

    // Allow anchor links.
    if (str_starts_with($trimmedUrl, '#')) {
      return TRUE;
    }

    // Handle protocol-relative URLs (e.g., //www.yahoo.com).
    if (str_starts_with($trimmedUrl, '//')) {
      $parsedUrl = parse_url($trimmedUrl);
      if (!empty($parsedUrl['host'])) {
        return $this->isHostAllowed($parsedUrl['host']);
      }
      return FALSE;
    }

    // Allow relative paths.
    if (str_starts_with($trimmedUrl, '/')) {
      return TRUE;
    }

    // Only allow http://, https://, and public:// schemes.
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $trimmedUrl)) {
      $scheme = strtolower(parse_url($trimmedUrl, PHP_URL_SCHEME) ?? '');
      if ($scheme === 'public') {
        return TRUE;
      }
      if (!in_array($scheme, ['http', 'https'], TRUE)) {
        return FALSE;
      }
    }
    // Block scheme-like patterns without double slash (javascript:, data:).
    elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $trimmedUrl)) {
      return FALSE;
    }

    // Parse the URL.
    $parsedUrl = parse_url($trimmedUrl);

    // If no host is present, it's not a recognizable URL (e.g. bare text
    // like "byte.test" or "www.example.com"). Allow it through — only
    // explicit protocols (http://, https://) and protocol-relative URLs
    // (//) should be filtered.
    if (empty($parsedUrl['host'])) {
      return TRUE;
    }

    return $this->isHostAllowed($parsedUrl['host']);
  }

  /**
   * Check if a hostname is in the allowed domains list.
   *
   * @param string $hostname
   *   The hostname to check.
   *
   * @return bool
   *   TRUE if allowed, FALSE otherwise.
   */
  protected function isHostAllowed(string $hostname): bool {
    $allowedDomains = $this->getAllowedDomains();

    if (empty($allowedDomains)) {
      return FALSE;
    }

    foreach ($allowedDomains as $allowedDomain) {
      if ($this->matchesWildcardDomain($hostname, $allowedDomain)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if a multi-URL attribute value has only allowed URLs.
   *
   * Handles srcset (comma-separated with optional descriptors) and
   * ping (space-separated URLs).
   *
   * @param string $value
   *   The attribute value.
   *
   * @return bool
   *   TRUE if all URLs are allowed, FALSE otherwise.
   */
  protected function isMultiUrlAttributeAllowed(string $value): bool {
    $entries = preg_split('/\s*,\s*/', trim($value));
    foreach ($entries as $entry) {
      $parts = preg_split('/\s+/', trim($entry), 2);
      $url = $parts[0] ?? '';
      if ($url !== '' && !$this->isUrlAllowed($url)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Check if a hostname matches a wildcard domain pattern.
   *
   * @param string $hostname
   *   The hostname to check.
   * @param string $pattern
   *   The domain pattern (can include * wildcard for subdomains).
   *
   * @return bool
   *   TRUE if matches, FALSE otherwise.
   */
  protected function matchesWildcardDomain(string $hostname, string $pattern): bool {
    // Normalize both to lowercase for case-insensitive comparison.
    $hostname = strtolower($hostname);
    $pattern = strtolower($pattern);

    // Exact match.
    if ($hostname === $pattern) {
      return TRUE;
    }

    // Check for wildcard pattern.
    if (strpos($pattern, '*') !== FALSE) {
      // Convert wildcard pattern to regex.
      // *.example.com should match test.example.com and stage.test.example.com
      // but NOT example.com itself.
      // First escape all special regex characters.
      $regexPattern = preg_quote($pattern, '/');
      // Then replace the escaped wildcard with the subdomain regex.
      $regexPattern = str_replace('\\*\\.', '([a-zA-Z0-9-]+\.)+', $regexPattern);
      $regexPattern = '/^' . $regexPattern . '$/i';

      return preg_match($regexPattern, $hostname) === 1;
    }

    return FALSE;
  }

}

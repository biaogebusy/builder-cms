<?php

namespace Drupal\ai\Dto;

/**
 * DTO for hostname filter configuration settings.
 *
 * This DTO allows users to override the default settings from settings.php
 * when filtering AI-generated output for disallowed links and images.
 */
class HostnameFilterDto {

  use DtoBaseMethodsTrait;

  /**
   * Constructs a HostnameFilterDto object.
   *
   * @param array|null $allowedDomainNames
   *   Array of allowed domain names (can include wildcards like *.example.com).
   *   Example: ['example.com', '*.cdn.example.com', 'trusted.org'].
   * @param bool|null $rewriteLinks
   *   Whether to rewrite disallowed links to show the actual URL instead of
   *   removing them. When TRUE, disallowed links like
   *   <a href="https://evil.com">Click</a> become
   *   <a href="https://evil.com">https://evil.com</a>.
   *   Default: FALSE (removes the href attribute but keeps the link text).
   * @param bool|null $fullTrust
   *   Whether to bypass all filtering and allow all domains.
   *   When TRUE, no filtering is performed.
   *   Default: FALSE.
   * @param bool|null $plainTextMode
   *   Whether to use plain text mode for URL filtering.
   *   When TRUE, treats all input as plain text and filters URLs based on
   *   white list, regardless of HTML/Markdown markup. This is more secure
   *   but may cause issues with formatting or remove features.
   *   Default: FALSE (format-aware filtering).
   */
  public function __construct(
    public ?array $allowedDomainNames = NULL,
    public ?bool $rewriteLinks = NULL,
    public ?bool $fullTrust = NULL,
    public ?bool $plainTextMode = NULL,
  ) {
  }

}

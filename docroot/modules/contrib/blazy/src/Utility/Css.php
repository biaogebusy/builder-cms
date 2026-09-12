<?php

namespace Drupal\blazy\Utility;

/**
 * Provides very few common CSS sanitization wrapper methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
final class Css {

  /**
   * Sanitizes user-supplied CSS for safe inline <style> usage.
   *
   * This is NOT an XSS sanitizer.
   * It prevents:
   * - </style> breakouts
   * - Remote / executable CSS features
   * - Legacy IE expressions.
   *
   * @param string $css
   *   Raw user CSS.
   *
   * @return string
   *   Sanitized CSS safe for <style>.
   */
  public static function sanitizeInline(string $css): string {
    // Normalize line endings.
    $css = str_replace("\r\n", "\n", $css);

    // 1. Prevent </style> breakouts (case-insensitive).
    // Browsers accept <\/style> inside CSS as text.
    $css = preg_replace('/<\/\s*style\s*>/i', '<\\/style>', $css);

    // 2. Remove @import rules entirely.
    // These allow remote CSS execution.
    $css = preg_replace('/@import\s+[^;]+;/i', '', $css);

    // 3. Kill legacy executable CSS (IE only, but scanners still flag).
    $css = preg_replace('/expression\s*\(/i', '', $css);

    // 4. Restrict url() to local paths only.
    // Allows: url(/foo), url(../foo), url(foo)
    // Blocks: http(s), data, javascript
    $css = preg_replace_callback(
      '/url\s*\(\s*([^)]+)\s*\)/i',
      static function ($matches) {
        $url = trim($matches[1], '\'"');

        // Absolute or protocol-based URLs are not allowed.
        if (preg_match('#^(?:[a-z][a-z0-9+\-.]*:|//)#i', $url)) {
          return 'url()';
        }

        return 'url(' . $url . ')';
      },
      $css
    );

    return trim($css);
  }

  /**
   * Limit the scope of user-supplied CSS for safe inline <style> usage.
   *
   * @param string $css
   *   Raw user CSS.
   * @param string $scope
   *   The parent selector to scope users' input.
   *
   * @return string
   *   The scoped CSS safe for UGC.
   */
  public static function scope(string $css, string $scope): string {
    return preg_replace(
      '/(^|})\s*([^@{}][^{]+)\s*{/m',
      '$1 ' . $scope . ' $2 {',
       $css
    );
  }

}

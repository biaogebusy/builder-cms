<?php

namespace Drupal\blazy\Utility;

use Drupal\Component\Utility\Xss;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use Michelf\MarkdownExtra;

/**
 * Provides markdown utilities only useful for the help text.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
final class Markdown {

  /**
   * Processes Markdown text, and convert into HTML suitable for the help text.
   *
   * @param string $text
   *   The text to apply the Markdown filter to.
   * @param bool $help
   *   True, if the text will be used for Help pages.
   * @param bool $sanitize
   *   True, if the text should be sanitized.
   *
   * @return string
   *   The filtered, or raw converted text.
   */
  public static function parse(string $text, $help = TRUE, $sanitize = TRUE): string {
    if (!self::isApplicable()) {
      $text = $sanitize ? Xss::filterAdmin($text) : $text;
      return $help ? '<pre>' . $text . '</pre>' : $text;
    }

    // Fixed for invisible characters and linebreaks.
    $text = preg_replace('/\x{00A0}/u', ' ', $text);
    $text = str_replace(["\r\n", "\r"], "\n", $text);

    if (class_exists(CommonMarkConverter::class)) {
      if (class_exists(Environment::class)
        && class_exists(CommonMarkCoreExtension::class)
        && class_exists(TableExtension::class)
        && class_exists(MarkdownConverter::class)) {
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());

        $converter = new MarkdownConverter($environment);
      }
      else {
        $converter = new CommonMarkConverter();
      }

      // @todo figure out the best solution, not crucial for being optional.
      /** @phpstan-ignore-next-line */
      if (method_exists($converter, 'convert')) {
        /** @phpstan-ignore-next-line */
        $text = (string) $converter->convert($text);
      }
    }
    elseif (class_exists(MarkdownExtra::class)) {
      $text = (string) MarkdownExtra::defaultTransform($text);
    }

    // We do not pass it to FilterProcessResult, as this is meant simple.
    return $sanitize ? Xss::filterAdmin($text) : $text;
  }

  /**
   * Checks if we have the needed classes.
   */
  private static function isApplicable(): bool {
    return class_exists(CommonMarkConverter::class)
      || class_exists(MarkdownExtra::class);
  }

}

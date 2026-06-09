<?php

// phpcs:ignoreFile

declare(strict_types=1);

namespace Drupal\ai_content_suggestions\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines ai_content_suggestions annotation object.
 *
 * @Annotation
 */
final class AiContentSuggestions extends Plugin {

  /**
   * The plugin ID.
   */
  public readonly string $id; // @phpstan-ignore-line

  /**
   * The human-readable name of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $title; // @phpstan-ignore-line

  /**
   * The description of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public readonly string $description; // @phpstan-ignore-line

  /**
   * The AI operation type for the plugin.
   */
  public readonly string $operation_type; // @phpstan-ignore-line

}

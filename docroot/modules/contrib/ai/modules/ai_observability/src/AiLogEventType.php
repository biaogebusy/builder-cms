<?php

declare(strict_types=1);

namespace Drupal\ai_observability;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\ProviderDisabledEvent;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Supported AI events that can be logged by the ai_logging module.
 */
enum AiLogEventType: string {
  case PreGenerateResponse = PreGenerateResponseEvent::class;
  case PostGenerateResponse = PostGenerateResponseEvent::class;
  case PostStreamingResponse = PostStreamingResponseEvent::class;
  case ProviderDisabled = ProviderDisabledEvent::class;

  /**
   * Human readable label for UI.
   */
  public function label(): TranslatableMarkup {
    return match ($this) {
      self::PreGenerateResponse => new TranslatableMarkup('Pre-generate response event'),
      self::PostGenerateResponse => new TranslatableMarkup('Post-generate response event'),
      self::PostStreamingResponse => new TranslatableMarkup('Post-streaming response event'),
      self::ProviderDisabled => new TranslatableMarkup('Provider disabled event'),
    };
  }

  /**
   * Human readable description for UI.
   */
  public function description(): TranslatableMarkup {
    return match ($this) {
      self::PreGenerateResponse => new TranslatableMarkup('Before sending a request to the AI provider.'),
      self::PostGenerateResponse => new TranslatableMarkup('When the response from a provider is received.'),
      self::PostStreamingResponse => new TranslatableMarkup('When the streaming response is finished.'),
      self::ProviderDisabled => new TranslatableMarkup('When the AI provider is disabled.'),
    };
  }

  /**
   * Convenience helper for EventSubscriber logic.
   *
   * @return string[]
   *   List of event class names.
   */
  public static function supportedEventClasses(): array {
    return array_map(
      static fn(self $case) => $case->value,
      self::cases(),
    );
  }

}

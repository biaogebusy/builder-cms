<?php

declare(strict_types=1);

namespace Drupal\ai\Utility;

use Drupal\ai\Enum\AiModelCapability;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Utility class for pseudo operation types.
 *
 * Pseudo operation types are filtered variants of real operation types
 * that apply capability filters. These are used to filter AI models
 * based on specific capabilities like image vision, JSON output, etc.
 */
final class PseudoOperationTypes {

  /**
   * Get default pseudo operation types.
   *
   * @return array<int, array{id: string, actual_type: string, label: \Drupal\Core\StringTranslation\TranslatableMarkup, filter: array<int, \Drupal\ai\Enum\AiModelCapability>, description: \Drupal\Core\StringTranslation\TranslatableMarkup}>
   *   Array of pseudo operation type definitions. Each definition contains:
   *   - id: The pseudo operation type ID (e.g., 'chat_with_image_vision')
   *   - actual_type: The underlying operation type (e.g., 'chat')
   *   - label: Translatable label for the pseudo operation type
   *   - filter: Array of capability filters to apply
   *   - description: Translatable description of the pseudo operation type
   */
  public static function getDefaultPseudoOperationTypes(): array {
    return [
      [
        'id'          => 'chat_with_image_vision',
        'actual_type' => 'chat',
        'label'       => new TranslatableMarkup('Chat with Image Vision'),
        'filter'      => [AiModelCapability::ChatWithImageVision],
        'description' => new TranslatableMarkup('Analyze and interpret images provided within a conversation to enrich responses.'),
      ],
      [
        'id'          => 'chat_with_complex_json',
        'actual_type' => 'chat',
        'label'       => new TranslatableMarkup('Chat with Complex JSON'),
        'filter'      => [AiModelCapability::ChatJsonOutput],
        'description' => new TranslatableMarkup('Produce structured and valid JSON outputs suitable for programmatic use.'),
      ],
      [
        'id'          => 'chat_with_structured_response',
        'actual_type' => 'chat',
        'label'       => new TranslatableMarkup('Chat with Structured Response'),
        'filter'      => [AiModelCapability::ChatStructuredResponse],
        'description' => new TranslatableMarkup('Format responses into predictable structures such as lists or tables to facilitate readability and integration.'),
      ],
      [
        'id'          => 'chat_with_tools',
        'actual_type' => 'chat',
        'label'       => new TranslatableMarkup('Chat with Tools/Function Calling'),
        'filter'      => [AiModelCapability::ChatTools],
        'description' => new TranslatableMarkup('Dynamically execute external functions or API calls during the conversation.'),
      ],
    ];
  }

}

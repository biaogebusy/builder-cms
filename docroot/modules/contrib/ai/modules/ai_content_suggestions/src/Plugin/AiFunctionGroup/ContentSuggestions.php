<?php

namespace Drupal\ai_content_suggestions\Plugin\AiFunctionGroup;

use Drupal\ai\Attribute\FunctionGroup;
use Drupal\ai\Service\FunctionCalling\FunctionGroupInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * The definition of "Content Suggestions" group.
 */
#[FunctionGroup(
  id: 'content_suggestions',
  group_name: new TranslatableMarkup('Content Suggestions'),
  description: new TranslatableMarkup('These exposes tools that can be used for the AI Content Suggestions module.'),
  weight: 0,
)]
class ContentSuggestions implements FunctionGroupInterface {}

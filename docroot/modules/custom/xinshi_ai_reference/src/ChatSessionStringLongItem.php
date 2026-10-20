<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Core\Field\Plugin\Field\FieldType\StringLongItem;

/** Keeps explicit empty v2 reasoning in the existing field schema. */
final class ChatSessionStringLongItem extends StringLongItem {
  use ChatSessionEmptyString;
}

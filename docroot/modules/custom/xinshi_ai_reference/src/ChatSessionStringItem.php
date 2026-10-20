<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;

/** Keeps explicit empty v2 short strings in the existing field schema. */
final class ChatSessionStringItem extends StringItem {
  use ChatSessionEmptyString;
}

<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference;

use Drupal\text\Plugin\Field\FieldType\TextLongItem;

/** Keeps v2 body emptiness distinct without changing text processing or format access. */
final class ChatSessionTextItem extends TextLongItem {
  use ChatSessionEmptyString;
}

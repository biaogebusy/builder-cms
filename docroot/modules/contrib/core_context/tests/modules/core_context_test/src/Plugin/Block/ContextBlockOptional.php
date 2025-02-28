<?php

namespace Drupal\core_context_test\Plugin\Block;

/**
 * @Block(
 *   id = "context_block_optional",
 *   admin_label = @Translation("Optional context block"),
 *   context_definitions = {
 *     "value" = @ContextDefinition("any", required = FALSE, default_value = ""),
 *     "letter" = @ContextDefinition("string", required = FALSE, default_value = ""),
 *   },
 * )
 */
class ContextBlockOptional extends ContextBlock {
}

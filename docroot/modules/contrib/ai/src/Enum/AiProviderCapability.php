<?php

namespace Drupal\ai\Enum;

/**
 * Enum of AI provider capabilities, which aren't shared across all of these.
 */
enum AiProviderCapability: string {
  case StreamChatOutput = 'stream_chat_output';
  case ChatFiberSupport = 'chat_fiber_support';
}

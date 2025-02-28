<?php

declare(strict_types = 1);

namespace Drupal\sms_test_gateway\Plugin\SmsGateway;

/**
 * Defines a gateway requiring chunked messages.
 *
 * @SmsGateway(
 *   id = "memory_chunked",
 *   label = @Translation("Memory Chunked"),
 *   incoming = TRUE,
 *   outgoing_message_max_recipients = 2,
 * )
 */
final class MemoryChunked extends Memory {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

}

<?php

namespace Drupal\ai\Event;

/**
 * Changes or Exceptions to the output of a AI request can be done here.
 */
class PostGenerateResponseEvent extends AiProviderResponseBaseEvent {

  // The event name.
  const EVENT_NAME = 'ai.post_generate_response';

}

<?php

namespace Drupal\ai\Event;

/**
 * For collecting the results post streaming.
 *
 * This event should be used in conjunction with the PostGenerateResponseEvent
 * using the request thread id to connect the two events. There is no
 * manipulation of the data in this event, it is just for collecting the final
 * results.
 */
class PostStreamingResponseEvent extends AiProviderResponseBaseEvent {

  // The event name.
  const EVENT_NAME = 'ai.post_streaming_response';

}

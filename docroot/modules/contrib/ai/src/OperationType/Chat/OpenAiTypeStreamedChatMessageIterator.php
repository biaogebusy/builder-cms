<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * OpenAI Streamed Chat Message Iterator implementation.
 *
 * This class is a copy of the OpenAiChatMessageIterator,
 * adapted to Drupal's AI module structure. It can be extended as needed for
 * OpenAI-based streaming responses.
 */
class OpenAiTypeStreamedChatMessageIterator extends StreamedChatMessageIterator {

  /**
   * {@inheritdoc}
   */
  public function doIterate(): \Generator {
    foreach ($this->iterator->getIterator() as $data) {
      $metadata = $data->usage ? $data->usage->toArray() : [];
      $message = $this->createStreamedChatMessage(
        $data->choices[0]->delta->role ?? '',
        $data->choices[0]->delta->content ?? '',
        $metadata,
        $data->choices[0]->delta->toolCalls ?? NULL,
        $data->toArray(),
      );
      if ($data->usage !== NULL) {
        $message->setInputTokenUsage($data->usage->promptTokens ?? 0);
        $message->setOutputTokenUsage($data->usage->completionTokens ?? 0);
        $message->setTotalTokenUsage($data->usage->totalTokens ?? 0);
        $message->setReasoningTokenUsage($data->usage->completionTokenDetails->reasoningTokens ?? 0);
        $message->setCachedTokenUsage($data->usage->completionTokenDetails->cachedTokens ?? 0);
      }
      if (isset($data->choices[0]->finishReason)) {
        $this->setFinishReason($data->choices[0]->finishReason);
      }
      yield $message;
    }
  }

}

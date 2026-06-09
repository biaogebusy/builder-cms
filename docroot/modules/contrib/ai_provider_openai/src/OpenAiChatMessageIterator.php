<?php

namespace Drupal\ai_provider_openai;

use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;

/**
 * OpenAI Chat message iterator.
 */
class OpenAiChatMessageIterator extends StreamedChatMessageIterator {

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

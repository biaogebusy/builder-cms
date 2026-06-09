<?php

declare(strict_types=1);

namespace Drupal\ai_test\Plugin\AiShortTermMemory;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiShortTermMemory;
use Drupal\ai\Base\AiShortTermMemoryPluginBase;

/**
 * Very simple plugin implementation of a short term memory.
 */
#[AiShortTermMemory(
  id: 'remove_bunny',
  label: new TranslatableMarkup('Remove Bunny'),
  description: new TranslatableMarkup('Removes the word bunny from system message, removes a tool and a chat history.'),
)]
final class RemoveBunny extends AiShortTermMemoryPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function doProcess(): void {
    // Get the last N messages from the original chat history.
    $this->setChatHistory(array_slice($this->getOriginalChatHistory(), -5));
    // Remove the word bunny from the system prompt.
    $this->setSystemPrompt(str_replace('bunny', '', $this->getSystemPrompt()));
    // Remove exactly one tool.
    $tools = $this->getTools();
    array_pop($tools);
    $this->setTools($tools);
  }

}

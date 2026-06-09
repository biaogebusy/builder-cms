<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiShortTermMemory;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiShortTermMemory;
use Drupal\ai\Base\AiShortTermMemoryPluginBase;

/**
 * Very simple plugin implementation of a short term memory.
 */
#[AiShortTermMemory(
  id: 'last_n',
  label: new TranslatableMarkup('Last N'),
  description: new TranslatableMarkup('Last N lets you remove messages in the history, over X amount of threads long.'),
)]
final class LastN extends AiShortTermMemoryPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'max_messages' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['max_messages'] = [
      '#type' => 'number',
      '#title' => $this->t('Max messages'),
      '#description' => $this->t('The maximum amount of messages to keep in the history.'),
      '#default_value' => $this->configuration['max_messages'] ?? 10,
      '#min' => 1,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['max_messages'] = $form_state->getValue('max_messages');
  }

  /**
   * {@inheritdoc}
   */
  public function doProcess(): void {
    $max_messages = $this->configuration['max_messages'] ?? 10;
    // Get the last N messages from the original chat history.
    $this->setChatHistory(array_slice($this->getOriginalChatHistory(), -$max_messages));
  }

}

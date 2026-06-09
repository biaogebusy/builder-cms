<?php

namespace Drupal\ai\Plugin\ChatProcessor;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * Defines an interface for ChatProcessor plugins.
 *
 * ChatProcessor plugins provide different ways to process chat input and
 * generate responses. They can be used to implement various chatbot
 * behaviors like RAG, tool use, or custom processing logic.
 */
interface ChatProcessorInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * Sets the input to use for execution.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input object.
   */
  public function setInput(ChatInput $input): void;

  /**
   * Gets the input to use for execution.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatInput|null
   *   The chat input object, or NULL if not set.
   */
  public function getInput(): ?ChatInput;

  /**
   * Sets the output to use for execution.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $output
   *   The chat output object.
   */
  public function setOutput(ChatOutput $output): void;

  /**
   * Gets the output to use for execution.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput|null
   *   The chat output object, or NULL if not set.
   */
  public function getOutput(): ?ChatOutput;

  /**
   * Executes the plugin.
   *
   * This is the main method that performs the actual processing logic.
   * Implementations should process the input and generate appropriate output.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The processed chat output.
   *
   * @throws \Exception
   *   If execution fails.
   */
  public function doExecute(): ChatOutput;

  /**
   * Executes the plugin with state management.
   *
   * Validates input, calls doExecute(), and stores the output.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The processed chat output.
   *
   * @throws \Exception
   *   If execution fails.
   */
  public function execute(): ChatOutput;

  /**
   * Sets the thread ID for the current process.
   *
   * @param string $threadId
   *   The thread ID.
   */
  public function setThreadId(string $threadId): void;

  /**
   * Gets the thread ID for the current process.
   *
   * @return string|null
   *   The thread ID, or NULL if not set.
   */
  public function getThreadId(): ?string;

  /**
   * Sets whether the execution is finished.
   *
   * @param bool $finished
   *   TRUE if execution is finished, FALSE if the chatbot should continue
   *   polling for progress. Defaults to TRUE. Only set to FALSE for looping
   *   agents where each step should be called via the chatbot to see progress.
   */
  public function setFinished(bool $finished): void;

  /**
   * Gets whether the execution is finished.
   *
   * @return bool
   *   TRUE if execution is finished, FALSE if the chatbot should continue
   *   polling for progress.
   */
  public function getFinished(): bool;

  /**
   * Sets the files that are not images to the input.
   *
   * Images should be set in ChatInput. This is for other file types like
   * PDFs, documents, etc.
   *
   * @param \Drupal\ai\OperationType\GenericType\GenericFile[] $files
   *   An array of file objects.
   */
  public function setInputFiles(array $files): void;

  /**
   * Gets the files that are not images from the input.
   *
   * @return array
   *   An array of file objects.
   */
  public function getInputFiles(): array;

  /**
   * Returns the allowed file extensions that this consumer can handle.
   *
   * @return array
   *   An array of file extensions (e.g., ['pdf', 'doc', 'txt']).
   *   Empty array means no non-image files are allowed.
   */
  public function allowedFileExtensions(): array;

  /**
   * Returns whether this consumer allows image files in ChatInput.
   *
   * @return bool
   *   TRUE if images are allowed, FALSE otherwise.
   */
  public function allowsImages(): bool;

}

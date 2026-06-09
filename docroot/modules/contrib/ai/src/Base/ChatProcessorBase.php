<?php

namespace Drupal\ai\Base;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\GenericType\GenericFile;
use Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface;

/**
 * Base class for ChatProcessor plugins.
 *
 * This base class provides common functionality for ChatProcessor plugins,
 * including the execute method that calls doExecute and manages input/output
 * state.
 */
abstract class ChatProcessorBase extends PluginBase implements ChatProcessorInterface {

  /**
   * The chat input.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatInput|null
   */
  protected ?ChatInput $input = NULL;

  /**
   * The chat output.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatOutput|null
   */
  protected ?ChatOutput $output = NULL;

  /**
   * The thread ID.
   *
   * @var string|null
   */
  protected ?string $threadId = NULL;

  /**
   * Whether execution is finished.
   *
   * @var bool
   */
  protected bool $finished = TRUE;

  /**
   * The input files (non-images).
   *
   * @var \Drupal\ai\OperationType\GenericType\GenericFile[]
   */
  protected array $inputFiles = [];

  /**
   * {@inheritdoc}
   */
  public function setInput(ChatInput $input): void {
    $this->input = $input;
  }

  /**
   * {@inheritdoc}
   */
  public function getInput(): ?ChatInput {
    return $this->input;
  }

  /**
   * {@inheritdoc}
   */
  public function setOutput(ChatOutput $output): void {
    $this->output = $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutput(): ?ChatOutput {
    return $this->output;
  }

  /**
   * {@inheritdoc}
   */
  public function setThreadId(string $threadId): void {
    $this->threadId = $threadId;
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadId(): ?string {
    return $this->threadId;
  }

  /**
   * {@inheritdoc}
   */
  public function setFinished(bool $finished): void {
    $this->finished = $finished;
  }

  /**
   * {@inheritdoc}
   */
  public function getFinished(): bool {
    return $this->finished;
  }

  /**
   * {@inheritdoc}
   */
  public function setInputFiles(array $files): void {
    foreach ($files as $file) {
      if (!$file instanceof GenericFile) {
        throw new \InvalidArgumentException('Input files must be instances of GenericFile.');
      }
    }
    $this->inputFiles = $files;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputFiles(): array {
    return $this->inputFiles;
  }

  /**
   * {@inheritdoc}
   */
  public function allowedFileExtensions(): array {
    // By default, no non-image files are allowed.
    // Override this method in your plugin to allow specific extensions.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function allowsImages(): bool {
    // By default, images are allowed.
    // Override this method in your plugin to disallow images.
    return TRUE;
  }

  /**
   * Executes the plugin.
   *
   * This method calls doExecute() and manages the execution state.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The processed chat output.
   *
   * @throws \Exception
   *   If execution fails.
   */
  public function execute(): ChatOutput {
    // Validate that input is set before execution.
    if (!$this->input) {
      throw new \InvalidArgumentException('Chat input must be set before execution.');
    }

    // Call the plugin-specific execution logic.
    $output = $this->doExecute();

    // Set the output for retrieval.
    $this->setOutput($output);

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
  }

}

<?php

namespace Drupal\dropai_provider;

/**
 * Mock DropAI Client.
 *
 * This class provides a mock implementation of the DropAI client for testing
 * purposes. It simulates the mock responses.
 */
class MockDropAiClient {

  /**
   * The currently selected model.
   *
   * @var string
   */
  private $selectedModel;

  /**
   * Loads the available mock models for the DropAI service.
   *
   * @return array
   *   An associative array of model IDs and their human-readable names.
   */
  public function loadModels() {
    return [
      'drop-ai-text-model-1' => 'DropAI Text Model 1',
      'drop-ai-text-model-2' => 'DropAI Text Model 2',
      'drop-ai-vision-model' => 'DropAI Vision Model',
      'drop-ai-voice-model' => 'DropAI Voice Model',
    ];
  }

  /**
   * Selects a model for generating responses.
   *
   * @param string $model_key
   *   The ID of the model to select.
   *
   * @return $this
   *   The current instance for method chaining.
   */
  public function model($model_key) {
    $this->selectedModel = $model_key;
    return $this;
  }

  /**
   * Generates a mock response based on the selected model.
   *
   * @return string
   *   A simulated response from the selected AI model.
   */
  public function generate() {
    switch ($this->selectedModel) {
      case 'drop-ai-text-model-1':
        return 'Text Model 1: "The quick brown fox jumps over the lazy dog."';

      case 'drop-ai-text-model-2':
        return 'Text Model 2: "In a world of endless possibilities, creativity reigns supreme."';

      case 'drop-ai-voice-model':
        return 'Voice Model: "Simulated audio transcription: Hello, this is DropAI voice model at your service!"';

      case 'drop-ai-vision-model':
        return 'Vision Model: "Analyzed image: Detected a sunset over the mountains with an accuracy of 97%."';

      default:
        return 'Error: No model selected or model not recognized.';
    }
  }

}

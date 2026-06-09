<?php

namespace Drupal\test_ai_provider_mysql\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use MHz\MysqlVector\Nlp\Embedder;

/**
 * Plugin implementation of the 'openai' provider.
 */
#[AiProvider(
  id: 'test_mysql_provider',
  label: new TranslatableMarkup('Test MySQL AI Embedding Provider'),
)]
class TestMySqlAiEmbeddingProvider extends AiProviderClientBase implements EmbeddingsInterface {

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'embeddings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if ($operation_type === 'embeddings') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('test_mysql_provider.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    // Normalize the input if needed.
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }

    $embedder = new Embedder();
    $embeddings = $embedder->embed([$input]);
    $embedding = reset($embeddings);

    return new EmbeddingsOutput($embedding, $embedding, [
      'model_id' => $model_id,
      'input' => $input,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    return 384;
  }

  /**
   * Obtains a list of models.
   *
   * @return array
   *   A filtered list of public models.
   */
  public function getModels(): array {
    return ['mysql' => 'Default MySQL Embedding Provider'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return $this->getModels();
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    return 384;
  }

}

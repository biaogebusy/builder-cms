<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInterface;
use Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * 自定义供应商 Provider(platform: custom)。
 *
 * 用户在前端单次提交 endpoint / api_key / model,后端透传调用(假定 OpenAI-compatible)。
 * 连接信息**不持久化**:由 ProviderResolver 在运行时经 setConfiguration() 注入,
 * 本类在 loadClient() 把 endpoint/api_key 从 configuration 取出并 unset,避免泄入上游 payload。
 */
#[AiProvider(
  id: 'custom',
  label: new TranslatableMarkup('自定义供应商'),
)]
final class CustomAiProvider extends OpenAiProvider implements ImageToImageInterface {

  use OpenAiCompatibleImageEditTrait;

  /**
   * {@inheritdoc}
   *
   * OpenAiProvider 不支持 image_to_image,这里用 OpenAiCompatibleImageEditTrait 补齐。
   */
  public function getSupportedOperationTypes(): array {
    return array_values(array_unique([...parent::getSupportedOperationTypes(), 'image_to_image']));
  }

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    $endpoint = trim((string) ($this->configuration['endpoint'] ?? ''));
    if ($endpoint !== '') {
      $this->setEndpoint(rtrim($endpoint, '/'));
    }
    $apiKey = trim((string) ($this->configuration['api_key'] ?? ''));
    if ($apiKey !== '') {
      $this->setAuthentication($apiKey);
    }
    // 连接信息不能进生成 payload(OpenAiProvider::textToImage 用 + $this->configuration)。
    unset($this->configuration['endpoint'], $this->configuration['api_key'], $this->configuration['model']);

    // 父类 host 逻辑(getConfig()->get('host'))在 xinshi_ai.settings 下为空,不会覆盖上面的 endpoint。
    parent::loadClient();
  }

  /**
   * {@inheritdoc}
   *
   * custom 不进注册中心,运行时也不向网关查询模型清单。
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   *
   * 看 runtime config 是否齐备(endpoint + api_key)。
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    if (empty($this->configuration['endpoint']) || empty($this->configuration['api_key'])) {
      return FALSE;
    }
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes(), TRUE);
    }
    return TRUE;
  }

}

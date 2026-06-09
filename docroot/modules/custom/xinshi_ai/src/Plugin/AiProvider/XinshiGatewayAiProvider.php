<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\ImageToImage\ImageToImageInterface;
use Drupal\ai_provider_openai\Plugin\AiProvider\OpenAiProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * 信使统一网关 Provider(platform: xinshi)。
 *
 * 网关 = New API(OpenAI-compatible),所以直接继承 ai_provider_openai 的
 * OpenAiProvider,只覆盖两处:base URL 与 API key 的来源。
 *
 * - base URL:从 xinshi_ai.settings:gateway.base_url 读取(默认 https://ai.builder.design),
 *   自动补 /v1 路径(New API 的 OpenAI-compatible endpoint 在 /v1 下)。
 * - API key:从 xinshi_ai.settings:gateway.api_key 读取(各项目独立,后台配置)。
 *
 * 协议契约见 docs/xinshi-ai-gateway-protocol.md。
 */
#[AiProvider(
  id: 'xinshi',
  label: new TranslatableMarkup('信使网关 (New API)'),
)]
final class XinshiGatewayAiProvider extends OpenAiProvider implements ImageToImageInterface {

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
   *
   * 父类的 getConfig() 已经指向 xinshi_ai.settings(plugin provider = xinshi_ai),
   * 但它读的是顶层 host;这里改用 gateway.base_url,并跳过父类的 host 逻辑。
   */
  protected function loadClient(): void {
    $baseUrl = trim((string) ($this->getConfig()->get('gateway.base_url') ?? ''));
    if ($baseUrl !== '') {
      $this->setEndpoint($this->normalizeBaseUrl($baseUrl));
    }
    // 父类 OpenAiProvider::loadClient() 只在 host 非空时覆盖 endpoint;
    // host 为空,所以这里设置的 endpoint 不会被覆盖。
    parent::loadClient();
  }

  /**
   * {@inheritdoc}
   *
   * 不走 key 模块,直接读 config 里的网关 key(选型 B:key 存配置,后台填)。
   */
  protected function loadApiKey(): string {
    $key = trim((string) ($this->getConfig()->get('gateway.api_key') ?? ''));
    if ($key === '') {
      throw new AiSetupFailureException('xinshi_ai.settings:gateway.api_key 未配置,请到 /admin/config/xinshi/ai 填写网关 API key。');
    }
    return $key;
  }

  /**
   * 补齐 New API 的 OpenAI-compatible /v1 路径。
   */
  private function normalizeBaseUrl(string $baseUrl): string {
    $baseUrl = rtrim($baseUrl, '/');
    if (!preg_match('#/v\d+$#', $baseUrl)) {
      $baseUrl .= '/v1';
    }
    return $baseUrl;
  }

}

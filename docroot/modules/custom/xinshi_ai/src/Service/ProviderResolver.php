<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\AiProviderPluginManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Exception\ProviderUnavailableException;

/**
 * 按 job 的 field_platform 解析出已配置好的 drupal/ai Provider。
 *
 * - xinshi:走信使网关(New API),网关根据 model id 自动转协议(含阿里通义千问)。
 * - custom:用户运行时提交的 endpoint/api_key 经 setConfiguration 注入,不持久化。
 */
final class ProviderResolver {

  /** Provider 的 HTTP 超时缺省值(秒),上游生图可能耗时近百秒。 */
  private const DEFAULT_TIMEOUT = 180;

  /** 平台 → AiProvider plugin id 映射。 */
  private const PLATFORM_TO_PROVIDER = [
    'xinshi' => 'xinshi',
    'custom' => 'custom',
  ];

  public function __construct(
    private readonly AiProviderPluginManager $aiProviderManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * @param \Drupal\node\NodeInterface $job
   *   job 节点。
   * @param array $genConfig
   *   生成参数(n / size / response_format / user 等);**不含** endpoint/api_key。
   * @param string $operationType
   *   drupal/ai operation type,如 'text_to_image',用于 isUsable 能力校验。
   *
   * @return object
   *   drupal/ai 的 ProviderProxy(行为等同 AiProviderInterface)。
   *
   * @throws \Drupal\xinshi_ai\Exception\ProviderUnavailableException
   */
  public function resolve(NodeInterface $job, array $genConfig, string $operationType): object {
    $platform = (string) $job->get('field_platform')->value;
    $providerId = self::PLATFORM_TO_PROVIDER[$platform] ?? $platform;
    // 超时必须在 createInstance 阶段注入:Provider 的 Guzzle client 在构造时就锁定
    // timeout,之后的 setConfiguration() 改不到它。缺省取 ai 模块的 request_timeout
    // 行为(60s)远低于上游生图耗时,故用本模块的 gateway.request_timeout 覆盖。
    $timeout = (int) ($this->configFactory->get('xinshi_ai.settings')->get('gateway.request_timeout') ?: self::DEFAULT_TIMEOUT);
    $provider = $this->aiProviderManager->createInstance($providerId, [
      'http_client_options' => ['timeout' => $timeout],
    ]);

    $config = $genConfig;
    if ($platform === 'custom') {
      $params = json_decode((string) ($job->get('field_params')->value ?? ''), TRUE) ?: [];
      $config['endpoint'] = (string) ($params['endpoint'] ?? '');
      $config['api_key'] = (string) ($params['api_key'] ?? '');
    }
    $provider->setConfiguration($config);

    if (!$provider->isUsable($operationType)) {
      throw new ProviderUnavailableException(sprintf("Provider '%s' is not usable (missing endpoint/api_key or unsupported operation).", $platform));
    }

    return $provider;
  }

}

<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Service\EventStreamServiceInterface;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Drupal\xinshi_ai\Service\MediaUploadServiceInterface;
use Drupal\xinshi_ai\Service\ModelRegistryServiceInterface;
use Drupal\xinshi_ai\Service\ProviderErrorMapper;
use Drupal\xinshi_ai\Service\ProviderResolver;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI 任务插件基类。
 *
 * 注入业务基础服务,任务实现只关注自身 validate/execute/cancel。
 * 任务不自己做 HTTP / 重试 —— 那是 drupal/ai Provider 的职责。
 */
abstract class AiTaskBase extends PluginBase implements AiTaskInterface, ContainerFactoryPluginInterface {

  protected ProviderResolver $providerResolver;
  protected JobLifecycleServiceInterface $lifecycle;
  protected MediaUploadServiceInterface $mediaUpload;
  protected EventStreamServiceInterface $eventStream;
  protected ModelRegistryServiceInterface $registry;
  protected ProviderErrorMapper $errorMapper;
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->providerResolver = $container->get('xinshi_ai.provider_resolver');
    $instance->lifecycle = $container->get('xinshi_ai.job_lifecycle');
    $instance->mediaUpload = $container->get('xinshi_ai.media_upload');
    $instance->eventStream = $container->get('xinshi_ai.event_stream');
    $instance->registry = $container->get('xinshi_ai.model_registry');
    $instance->errorMapper = $container->get('xinshi_ai.provider_error_mapper');
    $instance->logger = $container->get('logger.channel.xinshi_ai');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getJobBundle(): string {
    return (string) $this->pluginDefinition['jobBundle'];
  }

  /**
   * {@inheritdoc}
   */
  public function getJobKind(): string {
    return (string) $this->pluginDefinition['jobKind'];
  }

  /**
   * 解析并配置好 job 对应的 Provider(含 custom 平台的 runtime 注入)。
   *
   * @param array $genConfig
   *   生成参数(n / size / response_format / user 等),不含 endpoint/api_key。
   *
   * @return object
   *   ProviderProxy(行为等同 AiProviderInterface)。
   *
   * @throws \Drupal\xinshi_ai\Exception\ProviderUnavailableException
   */
  protected function getProvider(NodeInterface $job, array $genConfig, string $operationType): object {
    return $this->providerResolver->resolve($job, $genConfig, $operationType);
  }

  /**
   * 解析有效 model id:custom 平台用用户提交的 field_params.model,其余用 field_model。
   */
  protected function effectiveModel(NodeInterface $job): string {
    $model = (string) $job->get('field_model')->value;
    if ((string) $job->get('field_platform')->value === 'custom') {
      $params = json_decode((string) ($job->get('field_params')->value ?? ''), TRUE) ?: [];
      $model = (string) ($params['model'] ?? $model);
    }
    return $model;
  }

  /**
   * 统一的模型能力校验(从 ModelRegistryService 读 capabilities / max_n)。
   *
   * @return array
   *   错误数组;空表示通过。
   */
  protected function validateModelCapability(array $input, string $capability): array {
    $errors = [];
    $platform = (string) ($input['platform'] ?? '');
    $modelId = (string) ($input['model'] ?? '');

    if ($platform === '') {
      $errors['platform'] = 'Platform is required.';
    }
    if ($modelId === '') {
      $errors['model'] = 'Model is required.';
      return $errors;
    }

    // custom 平台用户自带 endpoint/model,不查 registry。
    if ($platform === 'custom') {
      return $errors;
    }

    $model = $this->registry->getModel($modelId);
    if (!$model || !in_array($capability, $model['capabilities'] ?? [], TRUE)) {
      $errors['model'] = sprintf("Model '%s' does not support %s.", $modelId, $capability);
      return $errors;
    }
    if ($platform !== '' && ($model['platform'] ?? NULL) !== $platform) {
      $errors['model'] = sprintf("Model '%s' does not belong to platform '%s'.", $modelId, $platform);
      return $errors;
    }

    $maxN = (int) ($model['max_n'] ?? 1);
    $n = (int) ($input['params']['n'] ?? $input['n'] ?? 1);
    if ($maxN > 0 && $n > $maxN) {
      $errors['params.n'] = sprintf("Model '%s' allows max n=%d.", $modelId, $maxN);
    }

    return $errors;
  }

}

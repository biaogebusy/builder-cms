<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\AiTaskManager;
use Drupal\xinshi_ai\Exception\TaskExecutionException;
use Drupal\xinshi_ai\Service\EventStreamServiceInterface;
use Drupal\xinshi_ai\Service\JobLifecycleServiceInterface;
use Drupal\xinshi_ai\Service\ProviderErrorMapper;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 异步执行 image_job(cron / drush queue:run xinshi_ai_image_job)。
 *
 * 重试策略:任务抛 TaskExecutionException(带归一错误码),worker 判定:
 * 可重试码且未超次数 → 重新入队(attempt+1);否则置终态 failed。
 * 注意:DatabaseQueue 不支持延迟入队,故重试为立即重投(无 Retry-After 退避);
 * 若需退避,改用 advancedqueue / queue_unique。
 */
#[QueueWorker(
  id: 'xinshi_ai_image_job',
  title: new TranslatableMarkup('Xinshi AI image job worker'),
  cron: ['time' => 60],
)]
final class ImageJobWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /** 最大重试次数(不含首次)。 */
  private const MAX_RETRIES = 2;

  private AiTaskManager $taskManager;
  private JobLifecycleServiceInterface $lifecycle;
  private ProviderErrorMapper $errorMapper;
  private QueueFactory $queueFactory;
  private EventStreamServiceInterface $eventStream;
  private LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->taskManager = $container->get('plugin.manager.xinshi_ai_task');
    $instance->lifecycle = $container->get('xinshi_ai.job_lifecycle');
    $instance->errorMapper = $container->get('xinshi_ai.provider_error_mapper');
    $instance->queueFactory = $container->get('queue');
    $instance->eventStream = $container->get('xinshi_ai.event_stream');
    $instance->logger = $container->get('logger.channel.xinshi_ai');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $job = $this->lifecycle->loadJobByUuid($data['jobUuid'] ?? '');
    if (!$job || $job->get('field_status')->value === 'cancelled') {
      // 任务已被软取消或已不存在,直接丢弃。
      return;
    }
    $uuid = $job->uuid();
    $attempt = (int) ($data['attempt'] ?? 0);

    try {
      $task = $this->taskManager->getByKind($job->get('field_job_kind')->value);
      $task->execute($job);
    }
    catch (TaskExecutionException $e) {
      $this->handleFailure($job, $uuid, $e->errorCode, $e->getMessage(), $attempt);
    }
    catch (\Throwable $e) {
      // 任务外的意外错误:不重试,直接终态。
      $this->handleFailure($job, $uuid, 'internal', $e->getMessage(), self::MAX_RETRIES);
    }
  }

  /**
   * 重试或置终态。
   */
  private function handleFailure(NodeInterface $job, string $uuid, string $code, string $message, int $attempt): void {
    if ($this->errorMapper->isRetryable($code) && $attempt < self::MAX_RETRIES) {
      $this->logger->warning('image_job 重试 job=@u code=@c attempt=@a: @m', [
        '@u' => $uuid,
        '@c' => $code,
        '@a' => $attempt + 1,
        '@m' => $message,
      ]);
      $this->queueFactory->get($this->getPluginId())->createItem([
        'jobUuid' => $uuid,
        'attempt' => $attempt + 1,
      ]);
      return;
    }

    $this->logger->error('image_job 失败(终态)job=@u code=@c attempt=@a: @m', [
      '@u' => $uuid,
      '@c' => $code,
      '@a' => $attempt,
      '@m' => $message,
    ]);
    $this->lifecycle->markFailed($job, $message, $code);
    $this->eventStream->publish($uuid, 'failed', [
      'statusReason' => $message,
      'errorCode' => $code,
    ]);
  }

}

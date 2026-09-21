<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\xinshi_ai\Service\ImageJobExecutor;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * 异步执行 image_job(cron / drush queue:run xinshi_ai_image_job)。
 *
 * 领取、去重、恢复与重试都在 ImageJobExecutor:同一任务同时只有一个 worker 在跑,
 * 终态任务的重复投递不再调模型,worker 中途死亡的任务由租约证据收口(UB2.5)。
 * 重试策略:可重试码且未超次数 → 立即重新入队(attempt+1);否则置终态 failed。
 * DatabaseQueue 不支持延迟入队,故没有 Retry-After 退避。
 */
#[QueueWorker(
  id: 'xinshi_ai_image_job',
  title: new TranslatableMarkup('Xinshi AI image job worker'),
  cron: ['time' => 60],
)]
final class ImageJobWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private ImageJobExecutor $executor;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->executor = $container->get('xinshi_ai.image_job_executor');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $this->executor->run(is_array($data) ? $data : []);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Psr\Log\LoggerInterface;

/**
 * 在 kernel.terminate 阶段即时消费 image_job 队列。
 *
 * 创建任务后若只依赖 cron(QueueWorker `cron: 60s`),从入队到首个 SSE 事件之间会有
 * 一个 cron 周期的空窗,卡片长期停在「排队中」。本服务让 POST 响应回客户端后
 * (fastcgi_finish_request)在同一 worker 内立即把队列跑掉,happy path 秒级出图;
 * 失败仍由 worker 内部重投 + cron 兜底。claimItem 走租约,与 cron 并发安全。
 */
final class ImmediateJobRunner {

  private const QUEUE = 'xinshi_ai_image_job';

  /**
   * 租约时长(秒):需大于单次生图上限,避免处理中租约到期被 cron 重复领取。
   */
  private const LEASE = 600;

  /**
   * 领取新条目的墙钟上限(秒):达到后不再领新条目,已在处理的条目跑完为止。
   */
  private const CLAIM_DEADLINE = 60;

  private bool $scheduled = FALSE;

  public function __construct(
    private readonly QueueFactory $queueFactory,
    private readonly QueueWorkerManagerInterface $queueManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * 标记本请求结束后需要即时跑队列(由 controller 入队后调用)。
   */
  public function schedule(): void {
    $this->scheduled = TRUE;
  }

  public function isScheduled(): bool {
    return $this->scheduled;
  }

  /**
   * 排空队列(有时间上限);单条处理失败由 worker 内部消化,这里只做租约收尾。
   */
  public function run(): void {
    $this->scheduled = FALSE;
    $queue = $this->queueFactory->get(self::QUEUE);
    $worker = $this->queueManager->createInstance(self::QUEUE);
    $deadline = time() + self::CLAIM_DEADLINE;

    while (time() < $deadline && ($item = $queue->claimItem(self::LEASE))) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (RequeueException) {
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Throwable $e) {
        // 交回租约,留给 cron 兜底;processItem 正常路径已自行消化异常,这里仅防御。
        $queue->releaseItem($item);
        $this->logger->error('即时消费 image_job 失败: @m', ['@m' => $e->getMessage()]);
        break;
      }
    }
  }

}

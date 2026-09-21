<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai\Service\ImageJobRun;

/**
 * 一类 AI 任务的契约(图片生成 / 图编辑 / 视频 / ...)。
 *
 * 实现类放在 Plugin/AiTask/ 下,用 #[AiTask] 属性声明。
 */
interface AiTaskInterface {

  /**
   * 与 #[AiTask(id: ...)] 一致。
   */
  public function getId(): string;

  /**
   * 任务持久化写入的 job bundle 机器名,如 'image_job'。
   */
  public function getJobBundle(): string;

  /**
   * field_job_kind 的取值,如 'text_to_image'。
   */
  public function getJobKind(): string;

  /**
   * 业务输入校验。
   *
   * @return array
   *   ['field' => 'error message', ...];空数组表示通过。
   */
  public function validate(array $input, AccountInterface $account): array;

  /**
   * 执行 pipeline(由 QueueWorker 调用,已脱离 HTTP 上下文)。
   *
   * @param \Drupal\xinshi_ai\Service\ImageJobRun|null $run
   *   本次执行持有的租约(UB2.5):传输层用它记录请求已发出并在长调用期间续租。
   */
  public function execute(NodeInterface $job, ?ImageJobRun $run = NULL): void;

  /**
   * 软取消(打 cancelled 标记;不中断上游)。
   *
   * 已经结束的任务不会被改回 cancelled;运行中的调用返回后,其结果按任务的存储状态
   * 处理:迟到的图片只留证据,不成为交付。
   */
  public function cancel(NodeInterface $job): void;

}

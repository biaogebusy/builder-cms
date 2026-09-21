<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * image_job / image_asset 的生命周期与状态机集中管理。
 *
 * 状态变更都作用于刚从存储重新加载的节点并在任务级锁内进行:worker 手里的节点对象
 * 可能已经过时几分钟,而取消随时可能从 Web 请求落下。已到终态的任务不再被覆盖,
 * 迟到的结果只能作为证据保留(UB2.5)。
 */
interface JobLifecycleServiceInterface {

  /**
   * 创建 queued 状态的 job 节点。
   *
   * @param array $input
   *   归一化输入:jobKind / platform / model / prompt / negativePrompt /
   *   params(array)/ nRequested / inputImage(media id)/ parentJob(node id)。
   */
  public function createQueued(array $input, AccountInterface $owner): NodeInterface;

  /**
   * queued → running。
   *
   * @return bool
   *   FALSE 表示任务已到终态(例如排队时被取消),什么都没写。
   */
  public function markStarted(NodeInterface $job): bool;

  /**
   * running / partial → succeeded(没有任何 asset 时为 failed)。
   *
   * @return bool
   *   FALSE 表示任务已到终态,什么都没写。
   */
  public function markCompleted(NodeInterface $job): bool;

  /**
   * → failed,记录原因与归一错误码。
   *
   * @return bool
   *   FALSE 表示任务已到终态,什么都没写。
   */
  public function markFailed(NodeInterface $job, string $reason, ?string $errorCode = NULL): bool;

  /**
   * → cancelled(软取消)。
   *
   * @return bool
   *   FALSE 表示任务已经结束(例如刚刚完成),取消没有生效。
   */
  public function markCancelled(NodeInterface $job): bool;

  /**
   * 收口一个 worker 在供应商应答后死亡的任务:已落库过素材则 succeeded,否则 failed,
   * 并记录原因与错误码。
   *
   * @return bool
   *   FALSE 表示任务已到终态,什么都没写。
   */
  public function finalizeInterrupted(NodeInterface $job, string $reason, string $errorCode): bool;

  /**
   * 回填供应商 request id 与改写后的提示词(观测字段,不受终态限制)。
   */
  public function recordProviderMeta(NodeInterface $job, ?string $requestId, ?string $revisedPrompt): void;

  /**
   * 为 job 追加一张成功的 asset,并把 job 推进到 partial。
   *
   * @param int $index
   *   该图在本次供应商响应中的位置,即 asset 的稳定输出序号。
   *
   * @throws \Drupal\xinshi_ai\Exception\JobAlreadyTerminalException
   *   任务在提交这张图之前已到终态(例如被取消)。
   */
  public function createAsset(NodeInterface $job, array $imageMeta, MediaInterface $media, int $index): NodeInterface;

  /**
   * job 是否处于终态(succeeded / failed / cancelled)。
   */
  public function isTerminal(string $jobUuid): bool;

  /**
   * 重新读取存储中的状态并同步到传入的节点对象。
   *
   * @return string|null
   *   当前状态;任务已被删除时为 NULL。
   */
  public function refreshStatus(NodeInterface $job): ?string;

  /**
   * 按 uuid 载入 job 节点。
   */
  public function loadJobByUuid(string $jobUuid): ?NodeInterface;

}

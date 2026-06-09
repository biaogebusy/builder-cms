<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;

/**
 * image_job / image_asset 的生命周期与状态机集中管理。
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

  public function markStarted(NodeInterface $job): void;

  public function markCompleted(NodeInterface $job): void;

  public function markFailed(NodeInterface $job, string $reason, ?string $errorCode = NULL): void;

  public function markCancelled(NodeInterface $job): void;

  /**
   * 为 job 追加一张成功的 asset,并把 job 推进到 partial。
   */
  public function createAsset(NodeInterface $job, array $imageMeta, MediaInterface $media): NodeInterface;

  /**
   * job 是否处于终态(succeeded / failed / cancelled)。
   */
  public function isTerminal(string $jobUuid): bool;

  /**
   * 按 uuid 载入 job 节点。
   */
  public function loadJobByUuid(string $jobUuid): ?NodeInterface;

}

<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Attribute;

use Drupal\Component\Plugin\Attribute\AttributeBase;

/**
 * AI 任务插件属性。
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AiTask extends AttributeBase {

  /**
   * @param string $id
   *   插件 id。
   * @param string $label
   *   人类可读名。
   * @param string $jobBundle
   *   job 节点 bundle,如 'image_job'。
   * @param string $jobKind
   *   field_job_kind 取值,如 'text_to_image'。
   * @param int $defaultN
   *   默认生成张数(单任务在 worker 内的最大并行)。
   * @param string[] $platforms
   *   支持的 platform 列表;空数组表示不限制。
   */
  public function __construct(
    public readonly string $id,
    public readonly string $label,
    public readonly string $jobBundle,
    public readonly string $jobKind,
    public readonly int $defaultN = 4,
    public readonly array $platforms = [],
  ) {}

}

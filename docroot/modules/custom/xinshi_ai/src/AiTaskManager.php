<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\xinshi_ai\Attribute\AiTask;
use Drupal\xinshi_ai\Exception\TaskNotFoundException;

/**
 * AI 任务插件管理器(发现 Plugin/AiTask 下的 #[AiTask] 类)。
 */
final class AiTaskManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache, ModuleHandlerInterface $moduleHandler) {
    parent::__construct(
      'Plugin/AiTask',
      $namespaces,
      $moduleHandler,
      AiTaskInterface::class,
      AiTask::class,
    );
    $this->alterInfo('xinshi_ai_task_info');
    $this->setCacheBackend($cache, 'xinshi_ai_task_plugins');
  }

  /**
   * 按 field_job_kind 取任务实例。
   *
   * @throws \Drupal\xinshi_ai\Exception\TaskNotFoundException
   */
  public function getByKind(string $kind): AiTaskInterface {
    foreach ($this->getDefinitions() as $id => $def) {
      if (($def['jobKind'] ?? NULL) === $kind) {
        $instance = $this->createInstance($id);
        assert($instance instanceof AiTaskInterface);
        return $instance;
      }
    }
    throw new TaskNotFoundException(sprintf("No AI task for kind '%s'", $kind));
  }

}

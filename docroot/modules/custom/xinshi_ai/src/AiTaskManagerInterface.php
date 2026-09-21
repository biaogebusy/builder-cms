<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai;

/**
 * Looks up the task plugin that executes a job kind.
 *
 * The executor depends on this interface rather than the final plugin manager
 * so the queue decisions can be tested without the plugin discovery machinery.
 */
interface AiTaskManagerInterface {

  /**
   * Returns the task plugin registered for a `field_job_kind` value.
   *
   * @throws \Drupal\xinshi_ai\Exception\TaskNotFoundException
   */
  public function getByKind(string $kind): AiTaskInterface;

}

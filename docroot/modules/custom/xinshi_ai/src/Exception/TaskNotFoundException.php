<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/**
 * 找不到匹配 jobKind 的 AI 任务插件时抛出。
 */
final class TaskNotFoundException extends \RuntimeException {}

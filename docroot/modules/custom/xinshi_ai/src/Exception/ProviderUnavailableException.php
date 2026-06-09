<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/**
 * Provider 不可用(API key 缺失 / endpoint 未配置等)。映射到错误码 provider_unavailable。
 */
final class ProviderUnavailableException extends \RuntimeException {}

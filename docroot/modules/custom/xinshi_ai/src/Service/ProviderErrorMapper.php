<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\Exception\AiUnsafePromptException;
use Drupal\xinshi_ai\Exception\ProviderUnavailableException;
use OpenAI\Exceptions\ErrorException as OpenAiErrorException;
use OpenAI\Exceptions\TransporterException;

/**
 * 把各类异常归一到 provider-contracts §4 的错误码 enum,并判定是否可重试。
 *
 * 对齐 gateway-protocol §5.1(HTTP / upstream code → 内部码)。
 */
final class ProviderErrorMapper {

  /** 可重试的错误码(provider-contracts §4)。 */
  private const RETRYABLE = ['rate_limit', 'provider_5xx', 'provider_unavailable'];

  /**
   * 异常 → 错误码。
   */
  public function mapToCode(\Throwable $e): string {
    // drupal/ai 已归类的异常(OpenAiProvider 会把部分错误重映射成这些)。
    if ($e instanceof AiUnsafePromptException) {
      return 'content_policy';
    }
    if ($e instanceof AiQuotaException) {
      return 'quota_exhausted';
    }
    if ($e instanceof AiRateLimitException) {
      return 'rate_limit';
    }
    if ($e instanceof ProviderUnavailableException) {
      return 'provider_unavailable';
    }
    if ($e instanceof AiSetupFailureException) {
      return 'unauthorized';
    }

    // openai-php 原始 API 错误:用 HTTP + 上游 code 映射。
    if ($e instanceof OpenAiErrorException) {
      return $this->mapHttp($e->getStatusCode(), (string) ($e->getErrorCode() ?? $e->getErrorType() ?? ''));
    }

    // 网络层:超时 vs 其它。
    if ($e instanceof TransporterException) {
      $m = strtolower($e->getMessage());
      if (str_contains($m, 'timed out') || str_contains($m, 'curl error 28')) {
        return 'timeout';
      }
      return 'provider_5xx';
    }

    // 解包被 drupal/ai 包装的底层异常。
    $prev = $e->getPrevious();
    if ($prev instanceof \Throwable && $prev !== $e) {
      return $this->mapToCode($prev);
    }

    return 'internal';
  }

  /**
   * 该错误码是否应重试。
   */
  public function isRetryable(string $code): bool {
    return in_array($code, self::RETRYABLE, TRUE);
  }

  private function mapHttp(int $http, string $upstreamCode): string {
    return match (TRUE) {
      $http === 400 => 'validation',
      $http === 401 => 'unauthorized',
      $http === 403 => 'forbidden',
      $http === 404 => 'not_found',
      $http === 429 && $upstreamCode === 'quota_exceeded' => 'quota_exhausted',
      $http === 429 => 'rate_limit',
      $upstreamCode === 'content_filter' => 'content_policy',
      $http >= 500 => 'provider_5xx',
      $http >= 400 => 'provider_4xx',
      default => 'internal',
    };
  }

}

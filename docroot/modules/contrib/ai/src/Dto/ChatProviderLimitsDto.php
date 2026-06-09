<?php

declare(strict_types=1);

namespace Drupal\ai\Dto;

/**
 * DTO for rate limit information from the AI provider.
 */
class ChatProviderLimitsDto {

  use DtoBaseMethodsTrait;

  /**
   * Constructs a ChatProviderLimitsDto object.
   *
   * @param int|null $rateLimitMaxRequests
   *   The maximum number of requests allowed.
   * @param int|null $rateLimitMaxTokens
   *   The maximum number of tokens allowed.
   * @param int|null $rateLimitRemainingRequests
   *   The remaining number of requests.
   * @param int|null $rateLimitRemainingTokens
   *   The remaining number of tokens.
   * @param int|null $rateLimitResetRequests
   *   The time in seconds until the request limit resets.
   * @param int|null $rateLimitResetTokens
   *   The time in seconds until the token limit resets.
   */
  public function __construct(
    public ?int $rateLimitMaxRequests = NULL,
    public ?int $rateLimitMaxTokens = NULL,
    public ?int $rateLimitRemainingRequests = NULL,
    public ?int $rateLimitRemainingTokens = NULL,
    public ?int $rateLimitResetRequests = NULL,
    public ?int $rateLimitResetTokens = NULL,
  ) {
  }

  /**
   * Indicates if the rate limit information is empty.
   *
   * @return bool
   *   TRUE if the rate limit information is empty, FALSE otherwise.
   */
  public function empty(): bool {
    return $this->rateLimitMaxRequests === NULL
      && $this->rateLimitMaxTokens === NULL
      && $this->rateLimitRemainingRequests === NULL
      && $this->rateLimitRemainingTokens === NULL
      && $this->rateLimitResetRequests === NULL
      && $this->rateLimitResetTokens === NULL;
  }

}

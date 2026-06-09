<?php

namespace Drupal\ai\Dto;

/**
 * DTO for token usage parameters from the AI provider.
 */
class TokenUsageDto {

  use DtoBaseMethodsTrait;

  /**
   * Constructs a TokenUsageDto object.
   *
   * @param int|null $input
   *   The number of input tokens.
   * @param int|null $output
   *   The number of output tokens.
   * @param int|null $total
   *   The total number of tokens.
   * @param int|null $reasoning
   *   The number of reasoning tokens.
   * @param int|null $cached
   *   The number of cached tokens.
   */
  public function __construct(
    public ?int $input = NULL,
    public ?int $output = NULL,
    public ?int $total = NULL,
    public ?int $reasoning = NULL,
    public ?int $cached = NULL,
  ) {
  }

}

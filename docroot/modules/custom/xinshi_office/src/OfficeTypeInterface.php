<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

/** Validates project content independently of browser editors and AI transport. */
interface OfficeTypeInterface {

  /** Returns the stable document kind owned by this adapter. */
  public function kind(): string;

  /** Returns the trusted native suffix, MIME type, main part and OOXML content type. */
  public function fileFormat(): array;

  /** Validates content and returns its unique image resource digests. */
  public function validate(mixed $content): array;

}


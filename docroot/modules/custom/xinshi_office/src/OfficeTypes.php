<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

/** Explicit project assembly keeps adding a type independent of document storage. */
final class OfficeTypes {

  private array $types = [];

  public function __construct(array $types) {
    foreach ($types as $type) {
      if (!$type instanceof OfficeTypeInterface || isset($this->types[$type->kind()])) {
        throw new \LogicException('Invalid Office type assembly.');
      }
      $this->types[$type->kind()] = $type;
    }
  }

  /** Returns registered kinds without loading a browser editor or granting access. */
  public function kinds(): array {
    return array_keys($this->types);
  }

  /** Rejects unregistered formats before a new write. */
  public function get(mixed $kind): OfficeTypeInterface {
    if (!is_string($kind) || !isset($this->types[$kind])) {
      throw new OfficeException('type_unavailable', 403);
    }
    return $this->types[$kind];
  }

}

